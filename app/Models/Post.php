<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Post extends Model
{
    public const CATEGORIES = ['Ask', 'Share', 'Help', 'Vent', 'Academic'];

    protected $fillable = ['author_id', 'category', 'body', 'is_anonymous', 'image_path', 'removed_at', 'removed_by'];

    protected $casts = ['is_anonymous' => 'boolean', 'removed_at' => 'datetime'];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function reactions(): MorphMany
    {
        return $this->morphMany(Reaction::class, 'reactable');
    }
}
