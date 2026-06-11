<?php

namespace App\Filament\Resources\ReportTopics\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ReportTopicForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('event')
                    ->label('رویداد')
                    ->required()
                    ->disabled(fn (string $operation): bool => $operation === 'edit'),
                TextInput::make('title')
                    ->label('عنوان'),
                TextInput::make('thread_id')
                    ->label('آیدی تاپیک (message_thread_id)')
                    ->numeric()
                    ->helperText('خالی = تاپیک General'),
                Toggle::make('is_active')
                    ->label('فعال')
                    ->default(true),
            ]);
    }
}
