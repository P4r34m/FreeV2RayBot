<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class BotText extends Model
{
    protected $guarded = ['id'];

    protected static function booted(): void
    {
        $bust = fn () => Cache::forget('bot_texts.all');
        static::saved($bust);
        static::deleted($bust);
    }
}
