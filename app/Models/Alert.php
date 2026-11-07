<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Alert extends Model
{
    /** Icon (components/icon.blade.php) per alert kind. */
    public const ICONS = [
        'comment' => 'comment',
        'like' => 'like',
        'helpful' => 'star',
        'report' => 'flag',
        'notice' => 'shield',
    ];

    protected $fillable = ['user_id', 'kind', 'title', 'body', 'snippet', 'url', 'read_at'];

    protected $casts = ['read_at' => 'datetime'];

    public static function unreadCount(int $userId): int
    {
        return static::where('user_id', $userId)->whereNull('read_at')->count();
    }

    public function iconName(): string
    {
        return self::ICONS[$this->kind] ?? 'bell';
    }
}
