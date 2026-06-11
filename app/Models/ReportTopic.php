<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReportTopic extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'thread_id' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
