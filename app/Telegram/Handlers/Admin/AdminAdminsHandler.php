<?php

namespace App\Telegram\Handlers\Admin;

use App\Models\BotUser;
use App\Telegram\Conversations\AddAdminConversation;
use App\Telegram\Reply;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton as Btn;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

/**
 * Admin management submenu (callback: admin:admins): list current admins, add a
 * new one by numeric id, and remove DB-granted admins. Admins defined in the
 * server config (admin_ids) are "fixed" and cannot be removed from here.
 */
class AdminAdminsHandler
{
    public function __invoke(Nutgram $bot): void
    {
        Reply::toast($bot);

        $envIds = array_map('strval', config('v2raybot.bot.admin_ids', []));
        $selfId = (string) $bot->userId();
        $dbAdmins = BotUser::where('is_admin', true)->orderBy('id')->get();

        $kb = InlineKeyboardMarkup::make();
        $lines = [];

        foreach ($envIds as $id) {
            $lines[] = '🛡 <code>'.e($id).'</code> — ثابت (تنظیمات سرور)';
        }

        foreach ($dbAdmins as $admin) {
            $id = (string) $admin->telegram_id;
            if (in_array($id, $envIds, true)) {
                continue; // already shown as a fixed admin
            }

            $name = trim((string) ($admin->first_name ?? '').' '.(string) ($admin->last_name ?? ''));
            $you = $id === $selfId ? ' (شما)' : '';
            $lines[] = '👮 <code>'.e($id).'</code>'.($name !== '' ? ' — '.e($name) : '').$you;

            // Don't offer a remove button for yourself (avoid self lock-out).
            if ($id !== $selfId) {
                $kb->addRow(Btn::make('🗑 حذف ادمین '.$id, callback_data: 'admin:deladmin:'.$id));
            }
        }

        $kb->addRow(Btn::make('➕ افزودن ادمین', callback_data: 'admin:addadmin'));
        $kb->addRow(Btn::make('🔙 بازگشت', callback_data: 'admin:users'));

        $body = "👮 <b>مدیریت ادمین‌ها</b>\n\n"
            .($lines === [] ? 'ادمینی ثبت نشده.' : implode("\n", $lines))."\n\n"
            .'برای افزودن، روی «افزودن ادمین» بزنید و آیدی عددی را بفرستید. '
            .'ادمین‌های «ثابت» از تنظیمات سرور تعریف شده‌اند و از اینجا حذف نمی‌شوند.';

        Reply::screen($bot, $body, $kb);
    }

    /** Launch the add-admin conversation (callback: admin:addadmin). */
    public static function startAdd(Nutgram $bot): void
    {
        Reply::toast($bot);

        /** @var AddAdminConversation $conv */
        $conv = $bot->getContainer()->get(AddAdminConversation::class);
        $conv($bot);
    }

    /** Revoke a DB-granted admin (callback: admin:deladmin:{id}), then refresh. */
    public static function remove(Nutgram $bot, string $id): void
    {
        $envIds = array_map('strval', config('v2raybot.bot.admin_ids', []));

        if (in_array($id, $envIds, true)) {
            Reply::toast($bot, 'این ادمین ثابت است و از اینجا حذف نمی‌شود.', alert: true);
            (new self)($bot);

            return;
        }

        if ($id === (string) $bot->userId()) {
            Reply::toast($bot, 'نمی‌توانید خودتان را حذف کنید.', alert: true);
            (new self)($bot);

            return;
        }

        $user = BotUser::where('telegram_id', (int) $id)->first();
        if ($user) {
            $user->is_admin = false;
            $user->save();
        }

        Reply::toast($bot, '🗑 ادمین حذف شد');
        (new self)($bot);
    }
}
