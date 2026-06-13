<?php

namespace App\Telegram\Handlers;

use App\Jobs\ApplyReferralRewardJob;
use App\Models\BotUser;
use App\Services\ReferralService;
use App\Services\ReportService;
use App\Telegram\ChannelGate;
use App\Telegram\Screens;
use Illuminate\Support\Str;
use SergiX44\Nutgram\Nutgram;

/**
 * /start [ref_<id>] — captures the referral payload, enforces the channel gate,
 * then shows the main menu.
 */
class StartHandler
{
    public function __construct(
        private readonly ReferralService $referrals,
        private readonly ReportService $reports,
    ) {}

    public function __invoke(Nutgram $bot): void
    {
        /** @var BotUser $user */
        $user = $bot->get('botUser');

        if ($user->wasRecentlyCreated) {
            $this->reports->send(
                ReportService::NEW_USER,
                "🆕 <b>کاربر جدید</b>\n{$user->displayHandle()} (<code>{$user->telegram_id}</code>)",
            );
        }

        // Referral processing must never block the welcome menu.
        try {
            $this->captureReferral($bot, $user);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Referral capture failed', [
                'telegram_id' => $user->telegram_id,
                'error' => $e->getMessage(),
            ]);
        }

        if (! ChannelGate::enforce($bot)) {
            return;
        }

        Screens::mainMenu($bot, $user);
    }

    protected function captureReferral(Nutgram $bot, BotUser $user): void
    {
        $payload = trim(Str::after($bot->message()?->text ?? '', '/start'));

        if (str_starts_with($payload, 'ref_')) {
            $referrerId = (int) Str::after($payload, 'ref_');
            if ($referrerId > 0) {
                $this->referrals->register($user, $referrerId);
            }
        }

        // If referrals are verified on join (not on first config), do it now.
        if ($this->referrals->qualifyEvent() === 'start') {
            $referrer = $this->referrals->verify($user);

            // Reward mode pushes the freshly-credited bonus wallet onto the
            // referrer's config; in coin mode the coins were already granted inside
            // verify(), so there is nothing to push.
            if ($referrer && $this->referrals->mode() !== 'coin') {
                ApplyReferralRewardJob::dispatch($referrer->id);
            }
        }
    }
}
