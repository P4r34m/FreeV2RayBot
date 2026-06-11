<?php

/** @var SergiX44\Nutgram\Nutgram $bot */

use App\Telegram\Handlers\Admin\AdminAddChannelHandler;
use App\Telegram\Handlers\Admin\AdminBlockHandler;
use App\Telegram\Handlers\Admin\AdminBotPowerHandler;
use App\Telegram\Handlers\Admin\AdminBroadcastHandler;
use App\Telegram\Handlers\Admin\AdminChannelsHandler;
use App\Telegram\Handlers\Admin\AdminDeliveryHandler;
use App\Telegram\Handlers\Admin\AdminMenuHandler;
use App\Telegram\Handlers\Admin\AdminSetGroupHandler;
use App\Telegram\Handlers\Admin\AdminSettingsHandler;
use App\Telegram\Handlers\Admin\AdminStatsHandler;
use App\Telegram\Handlers\Admin\AdminToggleHandler;
use App\Telegram\Handlers\Admin\AdminUnblockHandler;
use App\Telegram\Handlers\Admin\AdminUsersHandler;
use App\Telegram\Handlers\ChatMemberHandler;
use App\Telegram\Handlers\CheckJoinHandler;
use App\Telegram\Handlers\ConfigStatusHandler;
use App\Telegram\Handlers\GetConfigHandler;
use App\Telegram\Handlers\IssueNewHandler;
use App\Telegram\Handlers\MenuHandler;
use App\Telegram\Handlers\ProfileHandler;
use App\Telegram\Handlers\ProfileHistoryHandler;
use App\Telegram\Handlers\ReferralHandler;
use App\Telegram\Handlers\RenewHandler;
use App\Telegram\Handlers\StartHandler;
use App\Telegram\Handlers\TutorialShowHandler;
use App\Telegram\Handlers\TutorialsHandler;
use App\Telegram\Keyboards;
use App\Telegram\Middleware\AntiSpamMiddleware;
use App\Telegram\Middleware\BotEnabledGuard;
use App\Telegram\Middleware\EnsureAdmin;
use App\Telegram\Middleware\MaintenanceGuard;
use App\Telegram\Middleware\ResolveBotUser;
use SergiX44\Nutgram\Nutgram;

/*
|--------------------------------------------------------------------------
| Channel join analytics — OUTSIDE the user-middleware group.
| chat_member updates are about channel joiners, not bot users, so they must
| not be provisioned/rate-limited. Requires 'chat_member' in allowed_updates
| (see: php artisan bot:set-webhook).
|--------------------------------------------------------------------------
*/
$bot->onChatMember(ChatMemberHandler::class);

/*
|--------------------------------------------------------------------------
| User + admin handlers, behind the moderation middleware group.
| Nutgram middleware is LIFO, so ResolveBotUser is added LAST to run FIRST.
|--------------------------------------------------------------------------
*/
$bot->group(function (Nutgram $bot) {
    /* ---------------------------- Commands ---------------------------- */
    $bot->onCommand('start', StartHandler::class)->description('شروع و دریافت منو');
    $bot->onCommand('admin', AdminMenuHandler::class)->middleware(EnsureAdmin::class)->description('پنل مدیریت');

    /* ------------------------- User callbacks ------------------------- */
    $bot->onCallbackQueryData(Keyboards::CB_MENU, MenuHandler::class);
    $bot->onCallbackQueryData(Keyboards::CB_GET_CONFIG, GetConfigHandler::class);
    $bot->onCallbackQueryData(Keyboards::CB_CONFIG_NEW, IssueNewHandler::class);
    $bot->onCallbackQueryData(Keyboards::CB_CONFIG_RENEW, RenewHandler::class);
    $bot->onCallbackQueryData(Keyboards::CB_CONFIG_STATUS, ConfigStatusHandler::class);
    $bot->onCallbackQueryData(Keyboards::CB_TUTORIALS, TutorialsHandler::class);
    $bot->onCallbackQueryData('tutorial:show:{id}', TutorialShowHandler::class);
    $bot->onCallbackQueryData(Keyboards::CB_REFERRAL, ReferralHandler::class);
    $bot->onCallbackQueryData(Keyboards::CB_PROFILE_HISTORY, ProfileHistoryHandler::class);
    $bot->onCallbackQueryData(Keyboards::CB_PROFILE, ProfileHandler::class);
    $bot->onCallbackQueryData(Keyboards::CB_CHECK_JOIN, CheckJoinHandler::class);

    /* ------------------------- Admin callbacks ------------------------ */
    $bot->onCallbackQueryData('admin:botpower', AdminBotPowerHandler::class)->middleware(EnsureAdmin::class);
    $bot->onCallbackQueryData('admin:stats', AdminStatsHandler::class)->middleware(EnsureAdmin::class);
    $bot->onCallbackQueryData('admin:settings', AdminSettingsHandler::class)->middleware(EnsureAdmin::class);
    $bot->onCallbackQueryData('admin:toggle:{key}', AdminToggleHandler::class)->middleware(EnsureAdmin::class);
    $bot->onCallbackQueryData('admin:delivery', AdminDeliveryHandler::class)->middleware(EnsureAdmin::class);
    $bot->onCallbackQueryData('admin:users', AdminUsersHandler::class)->middleware(EnsureAdmin::class);
    $bot->onCallbackQueryData('admin:block', AdminBlockHandler::class)->middleware(EnsureAdmin::class);
    $bot->onCallbackQueryData('admin:unblock', AdminUnblockHandler::class)->middleware(EnsureAdmin::class);
    $bot->onCallbackQueryData('admin:addchannel', AdminAddChannelHandler::class)->middleware(EnsureAdmin::class);
    $bot->onCallbackQueryData('admin:channels', AdminChannelsHandler::class)->middleware(EnsureAdmin::class);
    $bot->onCallbackQueryData('admin:setgroup', AdminSetGroupHandler::class)->middleware(EnsureAdmin::class);
    $bot->onCallbackQueryData('admin:broadcast', AdminBroadcastHandler::class)->middleware(EnsureAdmin::class);
    $bot->onCallbackQueryData(Keyboards::CB_ADMIN, AdminMenuHandler::class)->middleware(EnsureAdmin::class);
})
    ->middleware(AntiSpamMiddleware::class)
    ->middleware(MaintenanceGuard::class)
    ->middleware(BotEnabledGuard::class)
    ->middleware(ResolveBotUser::class); // added last => runs first
