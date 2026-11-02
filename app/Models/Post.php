<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Post extends Model
{
    public const CATEGORIES = ['Ask', 'Share', 'Help', 'Vent', 'Academic', 'Marketplace', 'Meme'];

    public const MARKETPLACE = 'Marketplace';

    public const MEME = 'Meme';

    public const CONDITIONS = ['Brand New', 'Like New', 'Used', 'For Parts'];

    public const MAX_PRICE = 1000000;

    public const MAX_PHOTOS = 3;

    protected $fillable = ['author_id', 'category', 'body', 'price', 'condition', 'is_anonymous', 'removed_at', 'removed_by'];

    protected $casts = ['is_anonymous' => 'boolean', 'removed_at' => 'datetime', 'price' => 'integer'];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(PostImage::class)->orderBy('position');
    }

    public function bookmarks(): HasMany
    {
        return $this->hasMany(Bookmark::class);
    }

    public function reactions(): MorphMany
    {
        return $this->morphMany(Reaction::class, 'reactable');
    }
}
