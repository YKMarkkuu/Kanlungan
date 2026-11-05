<?php

namespace App\Services;

use App\Models\Comment;
use App\Models\PointLog;
use App\Models\Post;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Points for eligible Helpful reactions and accepted answers; report outcomes
 * can also award or deduct points. The total never goes below 0.
 */
class PointService
{
    public const HELPFUL_RECEIVED = 3;

    public const ACCEPTED_RECEIVED = 5;

    public const HELPFUL_DAILY_CAP = 50;

    public const DAY_TIMEZONE = 'Asia/Manila';

    public const REPORT_UPHELD_AUTHOR = -10;

    public const REPORT_UPHELD_REPORTER = 3;

    public const FALSE_REPORT = -5;

    public const WRONG_TAG = -2;

    public const LABELS = [
        'helpful_received' => 'Someone marked your comment helpful',
        'helpful_removed' => 'A helpful mark was taken back',
        'accepted_received' => 'Your answer was accepted',
        'accepted_removed' => 'Your answer is no longer accepted',
        'report_upheld_author' => 'Your post or comment was removed after a report',
        'report_upheld_reporter' => 'Your report helped keep the community safe',
        'false_report' => 'A report you made was judged false',
        'wrong_tag' => 'A report you made had the wrong reason',
    ];

    /** Icon (resources/views/components/icon.blade.php) shown on each ledger row. */
    public const ICONS = [
        'helpful_received' => 'star',
        'helpful_removed' => 'star',
        'accepted_received' => 'check',
        'accepted_removed' => 'check',
        'report_upheld_author' => 'flag',
        'report_upheld_reporter' => 'shield',
        'false_report' => 'flag',
        'wrong_tag' => 'tag',
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

    /**
     * Turns raw point_logs rows into a banking-style ledger, grouped by day in
     * Manila time: Today / Yesterday / Earlier. Empty groups are dropped.
     *
     * @param  iterable<int, PointLog>  $logs  newest first
     * @return array<int, array{title: string, rows: array<int, array<string, string>>}>
     */
    public static function groupHistory(iterable $logs): array
    {
        $tz = self::DAY_TIMEZONE;
        $startOfToday = now($tz)->startOfDay();
        $startOfYesterday = $startOfToday->copy()->subDay();
        $buckets = ['Today' => [], 'Yesterday' => [], 'Earlier' => []];

        foreach ($logs as $log) {
            $at = $log->created_at?->copy()->setTimezone($tz);

            $bucket = match (true) {
                $at === null, $at->lt($startOfYesterday) => 'Earlier',
                $at->lt($startOfToday) => 'Yesterday',
                default => 'Today',
            };

            $amount = (int) $log->amount;
            $gain = $amount > 0;
            $size = abs($amount);

            $buckets[$bucket][] = [
                'label' => self::LABELS[$log->reason] ?? 'Points updated',
                'icon' => self::ICONS[$log->reason] ?? ($gain ? 'plus' : 'minus'),
                'tone' => $gain ? 'gain' : 'loss',
                'amount_text' => ($gain ? '+' : '−').$size,
                'spoken' => ($gain ? 'Plus ' : 'Minus ').$size.($size === 1 ? ' point' : ' points'),
                'time' => $at === null ? '' : ($bucket === 'Earlier' ? $at->format('M j') : $at->format('g:i A')),
            ];
        }

        $groups = [];

        foreach ($buckets as $title => $rows) {
            if ($rows !== []) {
                $groups[] = ['title' => $title, 'rows' => $rows];
            }
        }

        return $groups;
    }

    public function award(int $userId, int $amount, string $reason, ?Model $source = null): void
    {
        if ($amount === 0) {
            return;
        }

        DB::transaction(function () use ($userId, $amount, $reason, $source) {
            $row = DB::table('users')->where('id', $userId)->lockForUpdate()->first(['id', 'points', 'deleted_at']);

            if ($row === null || $row->deleted_at !== null) {
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

    public function awardsPoints(Model $source): bool
    {
        $post = $source instanceof Post
            ? $source
            : ($source instanceof Comment ? $source->post : null);

        return $post !== null && in_array($post->category, ['Ask', 'Help', 'Academic'], true);
    }

    public function awardHelpful(int $userId, Model $source): void
    {
        if (! $this->awardsPoints($source)) {
            return;
        }

        DB::transaction(function () use ($userId, $source) {
            $row = DB::table('users')->where('id', $userId)->lockForUpdate()->first(['id', 'deleted_at']);

            if ($row === null || $row->deleted_at !== null) {
                return;
            }

            $dayStart = now(self::DAY_TIMEZONE)->startOfDay()->setTimezone(config('app.timezone'));
            $dayEnd = $dayStart->copy()->addDay();
            $earnedToday = (int) PointLog::query()
                ->where('user_id', $userId)
                ->where('reason', 'helpful_received')
                ->where('amount', '>', 0)
                ->where('created_at', '>=', $dayStart)
                ->where('created_at', '<', $dayEnd)
                ->sum('amount');
            $amount = min(self::HELPFUL_RECEIVED, max(0, self::HELPFUL_DAILY_CAP - $earnedToday));

            if ($amount > 0) {
                $this->award($userId, $amount, 'helpful_received', $source);
            }
        });
    }

    public function reverse(int $userId, string $reason, Model $source): void
    {
        $receivedReason = str_replace('_removed', '_received', $reason);

        DB::transaction(function () use ($userId, $reason, $receivedReason, $source) {
            $row = DB::table('users')->where('id', $userId)->lockForUpdate()->first(['id', 'deleted_at']);

            if ($row === null || $row->deleted_at !== null) {
                return;
            }

            $sourceType = $source::class;
            $sourceId = $source->getKey();
            $paid = (int) PointLog::query()
                ->where('user_id', $userId)
                ->where('reason', $receivedReason)
                ->where('source_type', $sourceType)
                ->where('source_id', $sourceId)
                ->where('amount', '>', 0)
                ->sum('amount');
            $alreadyReversed = abs((int) PointLog::query()
                ->where('user_id', $userId)
                ->where('reason', $reason)
                ->where('source_type', $sourceType)
                ->where('source_id', $sourceId)
                ->where('amount', '<', 0)
                ->sum('amount'));
            $amount = max(0, $paid - $alreadyReversed);

            if ($amount > 0) {
                $this->award($userId, -$amount, $reason, $source);
            }
        });
    }
}
