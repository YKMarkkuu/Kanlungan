<?php

namespace App\Support;

/**
 * Pure feed-ranking maths (no database, easy to test).
 *
 *   score = (base + engagement) / (hours_old + offset) ^ gravity
 *
 * FeedRankingService::rescore() repeats this same formula in one SQL UPDATE.
 * If you change it here, change it there too.
 */
final class FeedScore
{
    /**
     * @param  array{like: int|float, comment: int|float, helpful: int|float, accepted: int|float}  $weights
     * @param  array{likes?: int, commenters?: int, helpful?: int}  $caps
     */
    public static function engagementPoints(
        int $likes,
        int $commenters,
        int $helpful,
        bool $accepted,
        array $weights,
        array $caps = [],
    ): int {
        $likes = min(max(0, $likes), $caps['likes'] ?? PHP_INT_MAX);
        $commenters = min(max(0, $commenters), $caps['commenters'] ?? PHP_INT_MAX);
        $helpful = min(max(0, $helpful), $caps['helpful'] ?? PHP_INT_MAX);

        return (int) round(
            $likes * $weights['like']
            + $commenters * $weights['comment']
            + $helpful * $weights['helpful']
            + ($accepted ? $weights['accepted'] : 0)
        );
    }

    public static function score(
        float $base,
        float $engagement,
        float $hoursOld,
        float $gravity = 1.5,
        float $offsetHours = 2.0,
    ): float {
        return ($base + $engagement) / ((max(0.0, $hoursOld) + $offsetHours) ** $gravity);
    }
}
