<?php

namespace App\Filament\Resources\RequiredChannels\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class RequiredChannelsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('title')
                    ->label('عنوان')
                    ->searchable(),
                TextColumn::make('chat_id')
                    ->label('شناسه چت')
                    ->searchable(),
                TextColumn::make('username')
                    ->label('نام کاربری')
                    ->searchable()
                    ->toggleable(),
                ToggleColumn::make('is_private')
                    ->label('خصوصی'),
                TextColumn::make('invite_link_name')
                    ->label('نام لینک دعوت')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('join_count')
                    ->label('ورود از لینک')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('member_count')
                    ->label('تعداد اعضا')
                    ->numeric()
                    ->sortable(),
                ToggleColumn::make('is_active')
                    ->label('فعال'),
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
