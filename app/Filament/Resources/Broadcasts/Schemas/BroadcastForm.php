<?php

namespace App\Filament\Resources\Broadcasts\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class BroadcastForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('پیام')->columns(2)->schema([
                Select::make('admin_id')->label('ارسال‌کننده')
                    ->relationship('admin', 'name')
                    ->disabled()
                    ->dehydrated(false),
                Select::make('media_type')->label('نوع رسانه')
                    ->options([
                        'photo' => 'تصویر',
                        'video' => 'ویدیو',
                        'document' => 'فایل',
                        'animation' => 'گیف',
                    ])
                    ->disabled()
                    ->dehydrated(false),
                Textarea::make('message')->label('متن پیام')
                    ->columnSpanFull()
                    ->disabled()
                    ->dehydrated(false),
            ]),

            Section::make('وضعیت ارسال')->columns(2)->schema([
                Select::make('status')->label('وضعیت')
                    ->options([
                        'pending' => 'در انتظار',
                        'running' => 'در حال ارسال',
                        'done' => 'انجام‌شده',
                        'failed' => 'ناموفق',
                    ])
                    ->disabled()
                    ->dehydrated(false),
                TextInput::make('total')->label('کل گیرندگان')
                    ->numeric()
                    ->disabled()
                    ->dehydrated(false),
                TextInput::make('sent')->label('ارسال‌شده')
                    ->numeric()
                    ->disabled()
                    ->dehydrated(false),
                TextInput::make('failed')->label('ناموفق')
                    ->numeric()
                    ->disabled()
                    ->dehydrated(false),
            ]),
        ]);
    }
}
