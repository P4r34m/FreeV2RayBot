<?php

namespace App\Filament\Resources\Tutorials\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class TutorialsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('title')
                    ->label('عنوان')
                    ->searchable(),
                TextColumn::make('category')
                    ->label('دسته‌بندی')
                    ->badge()
                    ->searchable(),
                TextColumn::make('media_type')
                    ->label('نوع رسانه')
                    ->badge()
                    ->toggleable(),
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
