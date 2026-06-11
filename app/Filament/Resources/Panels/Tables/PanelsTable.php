<?php

namespace App\Filament\Resources\Panels\Tables;

use App\Models\Panel;
use App\Panels\PanelManager;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Throwable;

class PanelsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('نام')->searchable(),
                TextColumn::make('type')->label('نوع')->badge()
                    ->formatStateUsing(fn ($state) => $state?->label() ?? $state),
                TextColumn::make('base_url')->label('آدرس')->limit(40)->searchable(),
                ToggleColumn::make('is_active')->label('فعال'),
                TextColumn::make('active_config_count')->label('کانفیگ فعال')->numeric()->sortable(),
                TextColumn::make('capacity')->label('ظرفیت')->numeric()->sortable()
                    ->placeholder('نامحدود'),
                TextColumn::make('health_status')->label('سلامت')->badge()
                    ->color(fn ($state) => match ($state) {
                        'ok' => 'success',
                        'failed' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'ok' => 'سالم',
                        'failed' => 'خطا',
                        default => 'نامشخص',
                    }),
                TextColumn::make('last_health_check_at')->label('آخرین بررسی')->since()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                self::testConnectionAction(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('priority', 'desc');
    }

    /** One-click "test connection" that probes the panel via its driver. */
    protected static function testConnectionAction(): Action
    {
        return Action::make('test')
            ->label('تست اتصال')
            ->icon(Heroicon::OutlinedSignal)
            ->color('gray')
            ->action(function (Panel $record) {
                try {
                    $ok = app(PanelManager::class)->driver($record)->testConnection();

                    $record->update([
                        'health_status' => $ok ? 'ok' : 'failed',
                        'health_message' => $ok ? null : 'تست اتصال ناموفق بود',
                        'last_health_check_at' => now(),
                    ]);

                    $ok
                        ? Notification::make()->title('✅ اتصال موفق بود')->success()->send()
                        : Notification::make()->title('❌ اتصال ناموفق بود')->danger()->send();
                } catch (Throwable $e) {
                    $record->update([
                        'health_status' => 'failed',
                        'health_message' => mb_substr($e->getMessage(), 0, 250),
                        'last_health_check_at' => now(),
                    ]);

                    Notification::make()->title('❌ خطا در اتصال')
                        ->body(mb_substr($e->getMessage(), 0, 200))->danger()->send();
                }
            });
    }
}
