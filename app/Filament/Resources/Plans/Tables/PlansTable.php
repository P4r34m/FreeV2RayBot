<?php

namespace App\Filament\Resources\Plans\Tables;

use App\Support\Bytes;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class PlansTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('name')
                    ->label('نام')
                    ->searchable(),
                TextColumn::make('data_limit_bytes')
                    ->label('حجم')
                    ->formatStateUsing(fn (int $state): string => $state > 0 ? Bytes::human($state) : 'نامحدود')
                    ->sortable(),
                TextColumn::make('duration_days')
                    ->label('مدت')
                    ->formatStateUsing(fn (int $state): string => $state > 0 ? $state.' روز' : 'نامحدود')
                    ->sortable(),
                IconColumn::make('is_default')
                    ->label('پیش‌فرض')
                    ->boolean(),
                ToggleColumn::make('is_active')
                    ->label('فعال'),
                TextColumn::make('panel.name')
                    ->label('سرور')
                    ->placeholder('هر سرور')
                    ->searchable(),
                TextColumn::make('sort_order')
                    ->label('ترتیب')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('تاریخ ایجاد')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('آخرین بروزرسانی')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
