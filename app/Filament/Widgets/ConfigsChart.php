<?php

namespace App\Filament\Widgets;

use App\Models\Config;
use Filament\Widgets\ChartWidget;

/** Configs created per day over the last two weeks. */
class ConfigsChart extends ChartWidget
{
    protected static ?int $sort = 2;

    public function getHeading(): ?string
    {
        return 'کانفیگ‌های ساخته‌شده (۱۴ روز اخیر)';
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $days = collect(range(13, 0))->map(fn (int $i) => today()->subDays($i));

        $counts = $days->map(
            fn ($day) => Config::whereDate('created_at', $day)->count()
        );

        return [
            'datasets' => [[
                'label' => 'کانفیگ',
                'data' => $counts->all(),
                'borderColor' => '#22c55e',
                'backgroundColor' => 'rgba(34,197,94,0.2)',
                'fill' => true,
                'tension' => 0.3,
            ]],
            'labels' => $days->map(fn ($day) => $day->format('m/d'))->all(),
        ];
    }
}
