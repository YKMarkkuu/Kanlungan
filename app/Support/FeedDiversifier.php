<?php

namespace App\Support;

/**
 * Pure re-ranking pass: keeps the score order, but defers a post when its author
 * already appeared in the last $lookback slots. Never drops anything, and when
 * only one author is left it simply keeps their order.
 *
 * $maxSkip stops a far-down post from jumping to the top just because the head of
 * the list is one author: only the next $maxSkip + 1 candidates are considered.
 */
final class FeedDiversifier
{
    /**
     * @param  array<int, array{id: int, author_id: int}>  $items  best first
     * @return array<int, array{id: int, author_id: int}>
     */
    public static function spread(array $items, int $lookback = 2, int $maxSkip = 10): array
    {
        $remaining = array_values($items);

        if ($lookback < 1 || count($remaining) < 2) {
            return $remaining;
        }

        $result = [];
        $recent = [];

        while ($remaining !== []) {
            $pick = 0;
            $window = min(count($remaining), $maxSkip + 1);

            for ($i = 0; $i < $window; $i++) {
                if (! in_array($remaining[$i]['author_id'], $recent, true)) {
                    $pick = $i;
                    break;
                }
            }

            $chosen = array_splice($remaining, $pick, 1)[0];
            $result[] = $chosen;
            $recent[] = $chosen['author_id'];

            if (count($recent) > $lookback) {
                array_shift($recent);
            }
        }

        return $result;
    }
}
