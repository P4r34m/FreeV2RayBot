<?php

namespace App\Filament\Resources\RequiredChannels\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class RequiredChannelForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('کانال')->columns(2)->schema([
                    TextInput::make('title')->label('عنوان')
                        ->required()->columnSpanFull(),
                    TextInput::make('chat_id')->label('شناسه چت')
                        ->required()
                        ->helperText('-100... یا @username'),
                    TextInput::make('username')->label('نام کاربری')
                        ->placeholder('@channel'),
                    TextInput::make('invite_link')->label('لینک دعوت')
                        ->url()->columnSpanFull(),
                    Toggle::make('is_private')->label('خصوصی'),
                    TextInput::make('invite_link_name')->label('نام لینک دعوت')
                        ->disabled(),
                ]),

                Section::make('وضعیت')->columns(2)->schema([
                    Toggle::make('is_active')->label('فعال')->default(true),
                    TextInput::make('sort_order')->label('ترتیب نمایش')
                        ->required()
                        ->numeric()
                        ->default(0),
                    TextInput::make('join_count')->label('ورود از لینک')
                        ->disabled(),
                    TextInput::make('member_count')->label('تعداد اعضا')
                        ->disabled(),
                ]),
            ]);
    }
}
