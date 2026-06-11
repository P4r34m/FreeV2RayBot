<?php

namespace App\Filament\Widgets;

use App\Enums\ConfigStatus;
use App\Models\BotUser;
use App\Models\Config;
use App\Models\Panel;
use App\Models\Referral;
use App\Support\Bytes;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Top-line KPIs for the admin dashboard. */
class StatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $usedBytes = (int) Config::sum('used_bytes');

        return [
            Stat::make('کل کاربران', number_format(BotUser::count()))
                ->description('امروز: '.BotUser::whereDate('created_at', today())->count())
                ->color('primary'),

            Stat::make('کانفیگ فعال', number_format(Config::where('status', ConfigStatus::Active->value)->count()))
                ->description('کل: '.number_format(Config::count()))
                ->color('success'),

            Stat::make('کانفیگ امروز', number_format(Config::whereDate('created_at', today())->count()))
                ->color('info'),

            Stat::make('رفرال تأییدشده', number_format(Referral::where('status', Referral::STATUS_VERIFIED)->count()))
                ->color('warning'),

            Stat::make('مصرف کل ترافیک', Bytes::human($usedBytes))
                ->color('gray'),

            Stat::make('پنل‌های فعال', Panel::where('is_active', true)->count().' / '.Panel::count())
                ->color('gray'),
        ];
    }
}
