<?php

namespace App\Jobs;

use App\Models\BotUser;
use App\Services\ConfigIssuanceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use SergiX44\Nutgram\Nutgram;
use Throwable;

/**
 * Pushes a referrer's freshly-credited bonus wallet onto their active config
 * (a panel call), off the webhook path. Used when referrals are verified on
 * join. Wallet credit itself already happened synchronously in ReferralService.
 */
class ApplyReferralRewardJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $referrerId) {}

    public function handle(Nutgram $bot, ConfigIssuanceService $issuer): void
    {
        $referrer = BotUser::find($this->referrerId);
        if (! $referrer) {
            return;
        }

        $issuer->applyWalletToActiveConfig($referrer);

        try {
            $bot->sendMessage(
                text: "🎉 یک زیرمجموعه‌ی جدید تأیید شد و هدیه‌ی شما اعمال شد!",
                chat_id: $referrer->telegram_id,
            );
        } catch (Throwable) {
            // ignore (blocked bot, etc.)
        }
    }
}
