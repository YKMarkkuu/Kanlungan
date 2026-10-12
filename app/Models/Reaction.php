<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Reaction extends Model
{
    public const LIKE = 'like';

    public const HELPFUL = 'helpful';

    protected $fillable = ['user_id', 'type'];

    public function reactable(): MorphTo
    {
        return $this->morphTo();
    }
}
