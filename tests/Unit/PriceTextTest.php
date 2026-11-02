<?php

namespace Tests\Unit;

use App\Services\CommunityService;
use PHPUnit\Framework\TestCase;

class PriceTextTest extends TestCase
{
    public function test_it_formats_single_ranges_equal_and_free_prices(): void
    {
        $this->assertNull(CommunityService::priceText([]));
        $this->assertSame('₱350', CommunityService::priceText([350]));
        $this->assertSame('₱1,200', CommunityService::priceText([1200]));
        $this->assertSame('₱250 – ₱300', CommunityService::priceText([300, 250]));
        $this->assertSame('₱250 each', CommunityService::priceText([250, 250]));
        $this->assertSame('Free – ₱300', CommunityService::priceText([0, 300]));
        $this->assertSame('Free', CommunityService::priceText([0]));
        $this->assertSame('Free', CommunityService::priceText([0, 0]));
    }
}
