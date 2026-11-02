<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Block extends Model
{
    protected $fillable = ['blocker_id', 'blocked_id'];

    public static function existsBetween(int $firstUserId, int $secondUserId): bool
    {
        return static::query()
            ->where(function ($query) use ($firstUserId, $secondUserId) {
                $query->where('blocker_id', $firstUserId)->where('blocked_id', $secondUserId);
            })
            ->orWhere(function ($query) use ($firstUserId, $secondUserId) {
                $query->where('blocker_id', $secondUserId)->where('blocked_id', $firstUserId);
            })
            ->exists();
    }

    public function blocker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'blocker_id');
    }

    public function blocked(): BelongsTo
    {
        return $this->belongsTo(User::class, 'blocked_id');
    }
}
