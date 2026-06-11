<?php

namespace App\Filament\Resources\Tutorials\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class TutorialForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('آموزش')->columns(2)->schema([
                    TextInput::make('title')->label('عنوان')->required(),
                    TextInput::make('category')->label('دسته‌بندی'),
                    Textarea::make('content')->label('محتوا (HTML)')
                        ->required()
                        ->rows(8)
                        ->columnSpanFull(),
                ]),

                Section::make('رسانه')->columns(2)->schema([
                    Select::make('media_type')->label('نوع رسانه')
                        ->options([
                            'photo' => 'تصویر',
                            'video' => 'ویدیو',
                            'document' => 'فایل',
                        ])
                        ->native(false),
                    TextInput::make('media_file_id')->label('شناسه فایل تلگرام'),
                    TextInput::make('url')->label('لینک')
                        ->url()->columnSpanFull(),
                ]),

                Section::make('وضعیت')->columns(2)->schema([
                    Toggle::make('is_active')->label('فعال')->default(true),
                    TextInput::make('sort_order')->label('ترتیب نمایش')
                        ->required()
                        ->numeric()
                        ->default(0),
                ]),
            ]);
    }
}
