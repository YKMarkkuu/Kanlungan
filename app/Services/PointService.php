<?php

namespace App\Services;

use App\Models\PointLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Points. Earned when someone rates your post/comment; lost when a report
 * against you is upheld, or when your own report is judged false / mis-tagged.
 * The total never goes below 0.
 */
class PointService
{
    public const LIKE_RECEIVED = 1;

    public const HELPFUL_RECEIVED = 3;

    public const ACCEPTED_RECEIVED = 5;

    public const REPORT_UPHELD_AUTHOR = -10;

    public const REPORT_UPHELD_REPORTER = 3;

    public const FALSE_REPORT = -5;

    public const WRONG_TAG = -2;

    public const LABELS = [
        'like_received' => 'Someone liked your post/comment',
        'like_removed' => 'A like was removed',
        'helpful_received' => 'Someone marked your comment helpful',
        'helpful_removed' => 'A "Helpful" was removed',
        'accepted_received' => 'Your answer was marked helpful',
        'accepted_removed' => 'An accepted answer was un-marked',
        'report_upheld_author' => 'Your post/comment was removed after a report',
        'report_upheld_reporter' => 'Your report was upheld',
        'false_report' => 'Your report was judged false',
        'wrong_tag' => 'Your report had the wrong tag',
    ];

    /** Badge thresholds (design screen 8): Newcomer -> Helper -> Trusted. */
    public const BADGES = ['Newcomer' => 0, 'Helper' => 50, 'Trusted' => 200];

    /**
     * @return array{badge: string, next: ?string, to_next: int, percent: int, badges: array<string, bool>}
     */
    public static function standing(int $points): array
    {
        $badge = 'Newcomer';
        $next = null;

        foreach (self::BADGES as $name => $min) {
            if ($points >= $min) {
                $badge = $name;
            } elseif ($next === null) {
                $next = $name;
            }
        }

        $toNext = $next === null ? 0 : self::BADGES[$next] - $points;
        $top = max(self::BADGES);

        return [
            'badge' => $badge,
            'next' => $next,
            'to_next' => $toNext,
            'percent' => (int) min(100, round($points / $top * 100)),
            'badges' => array_map(fn (int $min) => $points >= $min, self::BADGES),
        ];
    }

    public function award(int $userId, int $amount, string $reason, ?Model $source = null): void
    {
        if ($amount === 0) {
            return;
        }

        DB::transaction(function () use ($userId, $amount, $reason, $source) {
            $row = DB::table('users')->where('id', $userId)->lockForUpdate()->first(['id', 'points']);

            if ($row === null) {
                return;
            }

            DB::table('users')->where('id', $userId)->update(['points' => max(0, (int) $row->points + $amount)]);

            PointLog::create([
                'user_id' => $userId,
                'amount' => $amount,
                'reason' => $reason,
                'source_type' => $source ? $source::class : null,
                'source_id' => $source?->getKey(),
            ]);
        });
    }
}
