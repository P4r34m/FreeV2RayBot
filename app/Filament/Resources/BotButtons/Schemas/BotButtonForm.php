<?php

namespace App\Filament\Resources\BotButtons\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class BotButtonForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('key')
                    ->label('کلید')
                    ->required()
                    ->disabled(fn (string $operation): bool => $operation === 'edit')
                    ->helperText('کلید کد؛ پس از ساخت قابل تغییر نیست'),
                TextInput::make('label')
                    ->label('برچسب دکمه')
                    ->required(),
                TextInput::make('icon_custom_emoji_id')
                    ->label('آیدی ایموجی پریمیوم (اختیاری)')
                    ->helperText('Bot API 9.4 — آیکون پریمیوم کنار دکمه'),
                TextInput::make('description')
                    ->label('توضیح'),
            ]);
    }
}
