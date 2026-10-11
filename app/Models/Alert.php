<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Alert extends Model
{
    protected $fillable = ['user_id', 'kind', 'title', 'body', 'url', 'read_at'];

    protected $casts = ['read_at' => 'datetime'];

    public static function unreadCount(int $userId): int
    {
        return static::where('user_id', $userId)->whereNull('read_at')->count();
    }
}
