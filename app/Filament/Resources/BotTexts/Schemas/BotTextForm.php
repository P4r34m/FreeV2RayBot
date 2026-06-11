<?php

namespace App\Filament\Resources\BotTexts\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class BotTextForm
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
                TextInput::make('group')
                    ->label('گروه'),
                TextInput::make('description')
                    ->label('توضیح')
                    ->disabled()
                    ->helperText('توضیح داخلی برای شناسایی این متن'),
                Textarea::make('content')
                    ->label('متن (HTML — می‌توانید <tg-emoji> پریمیوم بگذارید)')
                    ->required()
                    ->rows(6)
                    ->columnSpanFull(),
            ]);
    }
}
