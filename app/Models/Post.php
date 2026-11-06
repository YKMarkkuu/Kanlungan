<?php

namespace App\Models;

use App\Support\FeedScore;
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

    public const MAX_ITEMS = 5;

    public const MAX_ITEM_LABEL = 40;

    public const MAX_PHOTOS = 3;

    protected $fillable = ['author_id', 'category', 'body', 'price', 'condition', 'is_anonymous', 'removed_at', 'removed_by'];

    protected $casts = [
        'is_anonymous' => 'boolean',
        'removed_at' => 'datetime',
        'price' => 'integer',
        'feed_score' => 'float',
        'engagement_points' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (Post $post) {
            if ($post->getAttribute('feed_score') === null) {
                $post->setAttribute('feed_score', FeedScore::score(
                    (float) config('feed.base_score'),
                    0,
                    0,
                    (float) config('feed.gravity'),
                    (float) config('feed.age_offset_hours'),
                ));
            }
        });
    }

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

    public function items(): HasMany
    {
        return $this->hasMany(PostItem::class)->orderBy('position');
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
