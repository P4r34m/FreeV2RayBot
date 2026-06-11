<?php

namespace App\Filament\Resources\BotUsers\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class BotUsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('telegram_id')->label('شناسه تلگرام')
                    ->searchable()
                    ->copyable()
                    ->sortable(),
                TextColumn::make('username')->label('نام کاربری')
                    ->searchable(),
                TextColumn::make('first_name')->label('نام')
                    ->searchable(),
                TextColumn::make('referral_count')->label('رفرال')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('bonus_traffic_bytes')->label('ترافیک هدیه (بایت)')
                    ->numeric()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('bonus_days')->label('روز هدیه')
                    ->numeric()
                    ->sortable()
                    ->toggleable(),
                IconColumn::make('is_admin')->label('ادمین')
                    ->boolean(),
                ToggleColumn::make('is_blocked')->label('مسدود'),
                TextColumn::make('created_at')->label('تاریخ عضویت')
                    ->dateTime()
                    ->sortable(),
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
