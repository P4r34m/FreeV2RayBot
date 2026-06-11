<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RequiredChannel extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_private' => 'boolean',
            'sort_order' => 'integer',
            'join_count' => 'integer',
            'member_count' => 'integer',
        ];
    }

    /** URL used for the join button. */
    public function joinUrl(): string
    {
        if ($this->invite_link) {
            return $this->invite_link;
        }

        $handle = ltrim((string) $this->username, '@');

        return $handle !== '' ? "https://t.me/{$handle}" : '';
    }
}
