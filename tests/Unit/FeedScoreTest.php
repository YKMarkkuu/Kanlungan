<?php

namespace Tests\Unit;

use App\Support\FeedScore;
use PHPUnit\Framework\TestCase;

class FeedScoreTest extends TestCase
{
    private const WEIGHTS = ['like' => 1, 'comment' => 3, 'helpful' => 5, 'accepted' => 50];

    public function test_score_matches_the_formula(): void
    {
        // 10 / (0 + 2)^1.5
        $this->assertEqualsWithDelta(3.5355339, FeedScore::score(10, 0, 0), 0.0001);
        // (10 + 53) / (1 + 2)^1.5
        $this->assertEqualsWithDelta(12.1244, FeedScore::score(10, 53, 1), 0.001);
    }

    public function test_older_posts_score_lower_and_a_future_timestamp_is_treated_as_now(): void
    {
        $this->assertGreaterThan(FeedScore::score(10, 5, 10), FeedScore::score(10, 5, 1));
        $this->assertGreaterThan(FeedScore::score(10, 5, 100), FeedScore::score(10, 5, 10));
        $this->assertSame(FeedScore::score(10, 5, 0), FeedScore::score(10, 5, -3));
    }

    public function test_weights_add_up(): void
    {
        $this->assertSame(0, FeedScore::engagementPoints(0, 0, 0, false, self::WEIGHTS));
        $this->assertSame(2 + 3 + 5 + 50, FeedScore::engagementPoints(2, 1, 1, true, self::WEIGHTS));
    }

    public function test_ordering_of_signals(): void
    {
        $like = FeedScore::engagementPoints(1, 0, 0, false, self::WEIGHTS);
        $comment = FeedScore::engagementPoints(0, 1, 0, false, self::WEIGHTS);
        $helpful = FeedScore::engagementPoints(0, 0, 1, false, self::WEIGHTS);
        $accepted = FeedScore::engagementPoints(0, 0, 0, true, self::WEIGHTS);

        $this->assertTrue($like < $comment && $comment < $helpful && $helpful < $accepted);
        $this->assertGreaterThan(FeedScore::engagementPoints(49, 0, 0, false, self::WEIGHTS), $accepted);
    }

    public function test_caps_limit_runaway_signals(): void
    {
        $caps = ['likes' => 50, 'commenters' => 20, 'helpful' => 20];

        $this->assertSame(50, FeedScore::engagementPoints(500, 0, 0, false, self::WEIGHTS, $caps));
        $this->assertSame(60, FeedScore::engagementPoints(0, 99, 0, false, self::WEIGHTS, $caps));
        $this->assertSame(100, FeedScore::engagementPoints(0, 0, 99, false, self::WEIGHTS, $caps));
    }
}
