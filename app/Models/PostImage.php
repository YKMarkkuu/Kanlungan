<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostImage extends Model
{
    public $timestamps = false;

    protected $fillable = ['post_id', 'path', 'position', 'width', 'height'];

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }
}
