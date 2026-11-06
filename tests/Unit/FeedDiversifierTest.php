<?php

namespace Tests\Unit;

use App\Support\FeedCursor;
use App\Support\FeedDiversifier;
use PHPUnit\Framework\TestCase;

class FeedDiversifierTest extends TestCase
{
    /** @param array<int, int> $authors author id per post, post ids start at 1 */
    private function items(array $authors): array
    {
        $items = [];

        foreach (array_values($authors) as $i => $author) {
            $items[] = ['id' => $i + 1, 'author_id' => $author];
        }

        return $items;
    }

    private function ids(array $items): array
    {
        return array_column($items, 'id');
    }

    public function test_the_same_author_is_spread_out(): void
    {
        $result = FeedDiversifier::spread($this->items([1, 1, 1, 1, 2, 3]));

        $this->assertSame([1, 5, 6, 2, 3, 4], $this->ids($result));
    }

    public function test_nothing_is_dropped_and_a_single_author_keeps_their_order(): void
    {
        $this->assertSame([1, 2, 3, 4], $this->ids(FeedDiversifier::spread($this->items([7, 7, 7, 7]))));
        $this->assertSame([], FeedDiversifier::spread([]));
    }

    public function test_lookback_zero_changes_nothing(): void
    {
        $this->assertSame([1, 2, 3], $this->ids(FeedDiversifier::spread($this->items([1, 1, 2]), 0)));
    }

    public function test_a_far_down_post_cannot_jump_more_than_the_lookahead(): void
    {
        $authors = array_fill(0, 12, 1);
        $authors[] = 2; // post 13

        $ids = $this->ids(FeedDiversifier::spread($this->items($authors)));

        $this->assertSame(2, array_search(13, $ids, true));
        $this->assertCount(13, array_unique($ids));
    }

    public function test_cursor_round_trips_and_rejects_garbage(): void
    {
        $encoded = FeedCursor::encode('AbCdEfGh12345678', 30);

        $this->assertSame(['token' => 'AbCdEfGh12345678', 'offset' => 30], FeedCursor::decode($encoded));
        $this->assertNull(FeedCursor::decode(null));
        $this->assertNull(FeedCursor::decode('not-a-cursor'));
        $this->assertNull(FeedCursor::decode(base64_encode('short.5')));
    }
}
