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
use App\Telegram\Handlers\Admin\AdminSetPathHandler;
use App\Telegram\Handlers\Admin\AdminSettingsHandler;
use App\Telegram\Handlers\Admin\AdminStatsHandler;
use App\Telegram\Handlers\Admin\AdminToggleHandler;
use App\Telegram\Handlers\Admin\AdminUnblockHandler;
use App\Telegram\Handlers\Admin\AdminUsersHandler;
// In-bot CRUD: panels
use App\Telegram\Handlers\Admin\AdminPanelsHandler;
use App\Telegram\Handlers\Admin\AdminPanelAddHandler;
use App\Telegram\Handlers\Admin\AdminPanelViewHandler;
use App\Telegram\Handlers\Admin\AdminPanelTestHandler;
use App\Telegram\Handlers\Admin\AdminPanelToggleHandler;
use App\Telegram\Handlers\Admin\AdminPanelDeleteHandler;
// In-bot CRUD: plans
use App\Telegram\Handlers\Admin\AdminPlansHandler;
use App\Telegram\Handlers\Admin\AdminPlanAddHandler;
use App\Telegram\Handlers\Admin\AdminPlanViewHandler;
use App\Telegram\Handlers\Admin\AdminPlanDefaultHandler;
use App\Telegram\Handlers\Admin\AdminPlanToggleHandler;
use App\Telegram\Handlers\Admin\AdminPlanDeleteHandler;
// In-bot CRUD: referral rules
use App\Telegram\Handlers\Admin\AdminRulesHandler;
use App\Telegram\Handlers\Admin\AdminRuleViewHandler;
use App\Telegram\Handlers\Admin\AdminRuleToggleHandler;
use App\Telegram\Handlers\Admin\AdminRuleDeleteHandler;
// In-bot CRUD: tutorials
use App\Telegram\Handlers\Admin\AdminTutorialsHandler;
use App\Telegram\Handlers\Admin\AdminTutorialViewHandler;
use App\Telegram\Handlers\Admin\AdminTutorialToggleHandler;
use App\Telegram\Handlers\Admin\AdminTutorialDeleteHandler;
// In-bot CRUD: content
use App\Telegram\Handlers\Admin\AdminContentHandler;
use App\Telegram\Handlers\Admin\AdminContentKeysHandler;
use App\Telegram\Handlers\Admin\AdminEditTextHandler;
use App\Telegram\Handlers\Admin\AdminEditButtonHandler;
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
use App\Models\Setting;
use App\Support\SettingKey;
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

    // Quick power commands — work for admins even when the bot is switched off.
    $bot->onCommand('on', function (Nutgram $bot) {
        Setting::put(SettingKey::BOT_ENABLED, true);
        $bot->sendMessage('🟢 ربات روشن شد.');
    })->middleware(EnsureAdmin::class)->description('روشن‌کردن ربات');
    $bot->onCommand('off', function (Nutgram $bot) {
        Setting::put(SettingKey::BOT_ENABLED, false);
        $bot->sendMessage('🔴 ربات خاموش شد. برای روشن‌کردن: /on');
    })->middleware(EnsureAdmin::class)->description('خاموش‌کردن ربات');

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
    $bot->onCallbackQueryData('admin:setpath', AdminSetPathHandler::class)->middleware(EnsureAdmin::class);
    $bot->onCallbackQueryData('admin:users', AdminUsersHandler::class)->middleware(EnsureAdmin::class);
    $bot->onCallbackQueryData('admin:block', AdminBlockHandler::class)->middleware(EnsureAdmin::class);
    $bot->onCallbackQueryData('admin:unblock', AdminUnblockHandler::class)->middleware(EnsureAdmin::class);
    $bot->onCallbackQueryData('admin:addchannel', AdminAddChannelHandler::class)->middleware(EnsureAdmin::class);
    $bot->onCallbackQueryData('admin:channels', AdminChannelsHandler::class)->middleware(EnsureAdmin::class);
    $bot->onCallbackQueryData('admin:setgroup', AdminSetGroupHandler::class)->middleware(EnsureAdmin::class);
    $bot->onCallbackQueryData('admin:broadcast', AdminBroadcastHandler::class)->middleware(EnsureAdmin::class);

    /* ---- In-bot CRUD: panels ---- */
    $bot->onCallbackQueryData('admin:panels', AdminPanelsHandler::class)->middleware(EnsureAdmin::class);
    $bot->onCallbackQueryData('admin:panels:add', AdminPanelAddHandler::class)->middleware(EnsureAdmin::class);
    $bot->onCallbackQueryData('admin:panels:view:{id}', AdminPanelViewHandler::class)->middleware(EnsureAdmin::class);
    $bot->onCallbackQueryData('admin:panels:test:{id}', AdminPanelTestHandler::class)->middleware(EnsureAdmin::class);
    $bot->onCallbackQueryData('admin:panels:toggle:{id}', AdminPanelToggleHandler::class)->middleware(EnsureAdmin::class);
    $bot->onCallbackQueryData('admin:panels:del:{id}', AdminPanelDeleteHandler::class)->middleware(EnsureAdmin::class);

    /* ---- In-bot CRUD: plans ---- */
    $bot->onCallbackQueryData('admin:plans', AdminPlansHandler::class)->middleware(EnsureAdmin::class);
    $bot->onCallbackQueryData('admin:plans:add', AdminPlanAddHandler::class)->middleware(EnsureAdmin::class);
    $bot->onCallbackQueryData('admin:plans:view:{id}', AdminPlanViewHandler::class)->middleware(EnsureAdmin::class);
    $bot->onCallbackQueryData('admin:plans:default:{id}', AdminPlanDefaultHandler::class)->middleware(EnsureAdmin::class);
    $bot->onCallbackQueryData('admin:plans:toggle:{id}', AdminPlanToggleHandler::class)->middleware(EnsureAdmin::class);
    $bot->onCallbackQueryData('admin:plans:del:{id}', AdminPlanDeleteHandler::class)->middleware(EnsureAdmin::class);

    /* ---- In-bot CRUD: referral rules ---- */
    $bot->onCallbackQueryData('admin:rules', AdminRulesHandler::class)->middleware(EnsureAdmin::class);
    $bot->onCallbackQueryData('admin:rules:add', fn (Nutgram $bot) => AdminRulesHandler::startAdd($bot))->middleware(EnsureAdmin::class);
    $bot->onCallbackQueryData('admin:rules:view:{id}', AdminRuleViewHandler::class)->middleware(EnsureAdmin::class);
    $bot->onCallbackQueryData('admin:rules:toggle:{id}', AdminRuleToggleHandler::class)->middleware(EnsureAdmin::class);
    $bot->onCallbackQueryData('admin:rules:del:{id}', AdminRuleDeleteHandler::class)->middleware(EnsureAdmin::class);

    /* ---- In-bot CRUD: tutorials ---- */
    $bot->onCallbackQueryData('admin:tutorials', AdminTutorialsHandler::class)->middleware(EnsureAdmin::class);
    $bot->onCallbackQueryData('admin:tutorials:add', fn (Nutgram $bot) => AdminTutorialsHandler::startAdd($bot))->middleware(EnsureAdmin::class);
    $bot->onCallbackQueryData('admin:tutorials:view:{id}', AdminTutorialViewHandler::class)->middleware(EnsureAdmin::class);
    $bot->onCallbackQueryData('admin:tutorials:toggle:{id}', AdminTutorialToggleHandler::class)->middleware(EnsureAdmin::class);
    $bot->onCallbackQueryData('admin:tutorials:del:{id}', AdminTutorialDeleteHandler::class)->middleware(EnsureAdmin::class);

    /* ---- In-bot CRUD: content (texts/buttons) ---- */
    $bot->onCallbackQueryData('admin:content', AdminContentHandler::class)->middleware(EnsureAdmin::class);
    $bot->onCallbackQueryData('admin:content:keys', AdminContentKeysHandler::class)->middleware(EnsureAdmin::class);
    $bot->onCallbackQueryData('admin:content:edittext', AdminEditTextHandler::class)->middleware(EnsureAdmin::class);
    $bot->onCallbackQueryData('admin:content:editbtn', AdminEditButtonHandler::class)->middleware(EnsureAdmin::class);

    $bot->onCallbackQueryData(Keyboards::CB_ADMIN, AdminMenuHandler::class)->middleware(EnsureAdmin::class);
})
    ->middleware(AntiSpamMiddleware::class)
    ->middleware(MaintenanceGuard::class)
    ->middleware(BotEnabledGuard::class)
    ->middleware(ResolveBotUser::class); // added last => runs first
