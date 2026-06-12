<?php

namespace App\Telegram\Handlers\Admin;

use App\Models\Tutorial;
use App\Telegram\Reply;
use Illuminate\Support\Str;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton as Btn;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

/** Show a single tutorial with manage actions (callback: admin:tutorials:view:{id}). */
class AdminTutorialViewHandler
{
    public function __invoke(Nutgram $bot, string $id): void
    {
        Reply::toast($bot);

        $tutorial = Tutorial::find((int) $id);

        if (! $tutorial) {
            Reply::toast($bot, 'آموزش یافت نشد.', true);
            (new AdminTutorialsHandler)($bot);

            return;
        }

        $cat = $tutorial->category ? ' ['.$tutorial->category.']' : '';
        $state = $tutorial->is_active ? '🟢 فعال' : '🔴 غیرفعال';
        $preview = Str::limit(strip_tags((string) $tutorial->content), 600);

        $lines = [
            '📚 <b>'.e($tutorial->title).'</b>'.e($cat),
            "وضعیت: {$state}",
            '',
            $preview === '' ? '—' : e($preview),
        ];

        $toggleLabel = $tutorial->is_active ? '🔴 غیرفعال‌کردن' : '🟢 فعال‌کردن';

        $kb = InlineKeyboardMarkup::make()
            ->addRow(
                Btn::make($toggleLabel, callback_data: 'admin:tutorials:toggle:'.$tutorial->id),
                Btn::make('🗑 حذف', callback_data: 'admin:tutorials:del:'.$tutorial->id),
            )
            ->addRow(Btn::make('🔙 بازگشت', callback_data: 'admin:tutorials'));

        Reply::screen($bot, implode("\n", $lines), $kb);
    }
}
