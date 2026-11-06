<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Report extends Model
{
    /** The reasons students can pick in the report sheet (design screen 6, "Report this post"). */
    public const CATEGORIES = ['Harassment', 'Hate speech', 'Sexual content', 'Profanity', 'Threats', 'Self-harm', 'Doxxing', 'Spam', 'Misinformation', 'Other'];

    /** Severity shown in the moderator queue (design screen 9). */
    public const CRITICAL = ['Self-harm', 'Threats'];

    public const HIGH = ['Harassment', 'Hate speech', 'Sexual content', 'Profanity', 'Doxxing'];

    public const MEDIUM = ['Spam', 'Misinformation', 'Other'];

    public static function severityFor(string $category): string
    {
        return match (true) {
            in_array($category, self::CRITICAL, true) => 'critical',
            in_array($category, self::HIGH, true) => 'high',
            default => 'medium',
        };
    }

    public const OPEN = 'open';

    public const VOTING = 'voting';

    public const RESOLVED = 'resolved';

    protected $fillable = [
        'reporter_id', 'reportable_type', 'reportable_id', 'category', 'details',
        'status', 'outcome', 'mod_status', 'mod_subject', 'referred_guidance_at', 'guidance_handled_at',
        'voting_started_at', 'voting_ends_at', 'resolved_at',
    ];

    protected $casts = [
        'voting_started_at' => 'datetime',
        'voting_ends_at' => 'datetime',
        'resolved_at' => 'datetime',
        'referred_guidance_at' => 'datetime',
        'guidance_handled_at' => 'datetime',
    ];

    public function reportable(): MorphTo
    {
        return $this->morphTo();
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function votes(): HasMany
    {
        return $this->hasMany(ReportVote::class);
    }
}
