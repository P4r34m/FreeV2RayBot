<?php

namespace App\Jobs;

use App\Models\BotUser;
use App\Models\Config;
use App\Models\Plan;
use App\Services\ConfigDeliveryService;
use App\Services\ConfigIssuanceService;
use App\Services\Exceptions\NoPanelAvailableException;
use App\Services\ReferralService;
use App\Services\ReportService;
use App\Telegram\Content;
use App\Telegram\Keyboards;
use App\Telegram\Presenter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use SergiX44\Nutgram\Nutgram;
use Throwable;

/**
 * Creates or renews a config on a panel off the webhook request path, then
 * delivers the subscription link to the user. Runs on the queue so the bot can
 * answer Telegram immediately and slow panel calls never block the webhook.
 */
class IssueConfigJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $backoff = 5;

    public function __construct(
        public int $telegramId,
        public int $chatId,
        public string $mode = 'new', // 'new' | 'renew'
        public ?int $configId = null,
        public ?int $planId = null,
        public ?int $panelId = null, // forced panel (user server picker)
    ) {}

    public function handle(
        Nutgram $bot,
        ConfigIssuanceService $issuer,
        ReferralService $referrals,
        ConfigDeliveryService $delivery,
        ReportService $reports,
    ): void {
        $user = BotUser::where('telegram_id', $this->telegramId)->first();
        if (! $user) {
            return;
        }

        // Only honor an EXPLICIT plan id. When none is set, pass null so the issuer
        // resolves the plan from the chosen panel (panel-specific plan, else the
        // global default) — passing Plan::default() here would override a panel's
        // own plan whenever the user picks a server.
        $plan = $this->planId ? Plan::find($this->planId) : null;

        try {
            $config = $this->mode === 'renew'
                ? $this->renew($user, $issuer, $plan)
                : $this->issueNew($user, $issuer, $referrals, $bot, $plan);

            $bot->sendMessage(
                text: $this->buildCaption($config, $delivery),
                chat_id: $this->chatId,
                parse_mode: 'HTML',
                disable_web_page_preview: true,
                reply_markup: Keyboards::backMenu(),
            );

            $this->report($reports, $user, $config);
        } catch (NoPanelAvailableException $e) {
            $bot->sendMessage(
                text: Content::text('config.no_panel', ['message' => $e->getMessage()]),
                chat_id: $this->chatId,
            );
        } catch (Throwable $e) {
            $logContext = [
                'telegram_id' => $this->telegramId,
                'mode' => $this->mode,
                'error' => $e->getMessage(),
            ];

            // Surface the panel's actual HTTP status/body (otherwise lost) so a
            // failed issuance is diagnosable from the logs, not just a generic msg.
            if ($e instanceof \App\Panels\Exceptions\PanelException) {
                $logContext['panel'] = $e->context;
            }

            Log::error('Config issuance failed', $logContext);
            $bot->sendMessage(text: Content::text('config.error'), chat_id: $this->chatId);
            $reports->send(ReportService::ERROR, "❌ <b>خطای ساخت کانفیگ</b>\nکاربر: <code>{$this->telegramId}</code>\n".htmlspecialchars($e->getMessage()));
        }
    }

    /** Build the delivery message per the configured mode (sub link vs links). */
    protected function buildCaption(Config $config, ConfigDeliveryService $delivery): string
    {
        if ($delivery->mode() === ConfigDeliveryService::MODE_CONFIGS) {
            $links = $delivery->fetchLinks($config);

            if ($links !== []) {
                $rendered = collect($links)
                    ->map(fn (string $l) => '<code>'.htmlspecialchars($l, ENT_QUOTES).'</code>')
                    ->implode("\n\n");

                return Content::text('config.caption_links', [
                    'limit' => $config->limitHuman(),
                    'expiry' => $config->expiryHuman(),
                    'links' => $rendered,
                ]);
            }
        }

        return Presenter::configCaption($config);
    }

    protected function report(ReportService $reports, BotUser $user, Config $config): void
    {
        $event = $this->mode === 'renew' ? ReportService::RENEW : ReportService::NEW_CONFIG;
        $title = $this->mode === 'renew' ? '♻️ تمدید کانفیگ' : '🆕 کانفیگ جدید';

        $reports->send($event, implode("\n", [
            "<b>{$title}</b>",
            "کاربر: {$user->displayHandle()} (<code>{$user->telegram_id}</code>)",
            'پنل: '.($config->panel?->name ?? '—'),
            'حجم: '.$config->limitHuman(),
        ]));
    }

    protected function issueNew(
        BotUser $user,
        ConfigIssuanceService $issuer,
        ReferralService $referrals,
        Nutgram $bot,
        ?Plan $plan,
    ): Config {
        $isFirstConfig = $user->configs()->count() === 0;

        $panel = $this->panelId ? \App\Models\Panel::find($this->panelId) : null;
        $config = $issuer->issueNew($user, $plan, $panel);

        // Verify the referral when the invitee's first config is the trigger.
        if ($isFirstConfig && $referrals->qualifyEvent() === 'first_config') {
            $referrer = $referrals->verify($user);

            if ($referrer) {
                $issuer->applyWalletToActiveConfig($referrer);
                $this->notifyReferrer($bot, $referrer);
            }
        }

        return $config;
    }

    protected function renew(BotUser $user, ConfigIssuanceService $issuer, ?Plan $plan): Config
    {
        $config = $this->configId
            ? $user->configs()->whereKey($this->configId)->first()
            : $user->configs()->where('status', \App\Enums\ConfigStatus::Active->value)->latest()->first();

        if (! $config) {
            throw new NoPanelAvailableException('کانفیگی برای تمدید پیدا نشد.');
        }

        return $issuer->renew($config, $plan);
    }

    protected function notifyReferrer(Nutgram $bot, BotUser $referrer): void
    {
        app(ReportService::class)->send(
            ReportService::REFERRAL,
            "👥 <b>زیرمجموعه‌ی جدید تأیید شد</b>\nمعرف: {$referrer->displayHandle()} (<code>{$referrer->telegram_id}</code>)\nمجموع: {$referrer->referral_count}",
        );

        try {
            $bot->sendMessage(
                text: Content::text('referral.notify'),
                chat_id: $referrer->telegram_id,
            );
        } catch (Throwable) {
            // Referrer may have blocked the bot; ignore.
        }
    }
}
