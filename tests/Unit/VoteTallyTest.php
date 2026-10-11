<?php

namespace Tests\Unit;

use App\Support\VoteTally;
use PHPUnit\Framework\TestCase;

class VoteTallyTest extends TestCase
{
    public function test_agree_wins(): void
    {
        $this->assertSame('upheld', VoteTally::decide(['agree' => 3, 'false_report' => 1, 'wrong_tag' => 1], 3));
    }

    public function test_false_report_wins(): void
    {
        $this->assertSame('false_report', VoteTally::decide(['agree' => 1, 'false_report' => 3], 3));
    }

    public function test_wrong_tag_wins(): void
    {
        $this->assertSame('wrong_tag', VoteTally::decide(['agree' => 1, 'wrong_tag' => 2], 3));
    }

    public function test_tie_is_no_consensus(): void
    {
        $this->assertSame('no_consensus', VoteTally::decide(['agree' => 2, 'false_report' => 2], 3));
        $this->assertSame('no_consensus', VoteTally::decide(['agree' => 1, 'false_report' => 1, 'wrong_tag' => 1], 3));
    }

    public function test_too_few_votes_is_no_consensus(): void
    {
        $this->assertSame('no_consensus', VoteTally::decide(['agree' => 2], 3));
        $this->assertSame('no_consensus', VoteTally::decide([], 3));
    }
}
