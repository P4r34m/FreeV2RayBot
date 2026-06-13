<?php

namespace App\Telegram\Handlers;

use App\Models\BotUser;
use App\Services\ConfigIssuanceService;
use App\Telegram\Reply;
use Illuminate\Support\Facades\Log;
use SergiX44\Nutgram\Nutgram;
use Throwable;

/** Rotate a config's subscription link, then re-show its details (config:rotate:{id}). */
class ConfigRotateHandler
{
    public function __invoke(Nutgram $bot, string $id): void
    {
        /** @var BotUser $user */
        $user = $bot->get('botUser');

        $config = $user->configs()->whereKey((int) $id)->with(['panel', 'plan'])->first();

        if (! $config) {
            Reply::toast($bot, 'اشتراک نامعتبر', alert: true);

            return;
        }

        try {
            app(ConfigIssuanceService::class)->rotateSubscription($config);
            Reply::toast($bot, '✅ لینک عوض شد');
        } catch (Throwable $e) {
            Reply::toast($bot, '⚠️ تعویض لینک ناموفق بود', alert: true);
            Log::error('Subscription rotate failed', ['config_id' => $config->id, 'error' => $e->getMessage()]);
        }

        // Re-render the detail view with the (possibly) new link.
        ConfigViewHandler::render($bot, $config);
    }
}
