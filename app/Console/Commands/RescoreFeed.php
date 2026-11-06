<?php

namespace App\Console\Commands;

use App\Services\FeedRankingService;
use Illuminate\Console\Command;

class RescoreFeed extends Command
{
    protected $signature = 'feed:rescore {--rebuild : Recompute engagement points for every post in the ranked window first}';

    protected $description = 'Apply time decay to the feed scores (and optionally rebuild engagement from scratch)';

    public function handle(FeedRankingService $ranking): int
    {
        if ($this->option('rebuild')) {
            $this->info('Rebuilt engagement for '.$ranking->rebuildEngagement().' post(s).');
        }

        $this->info('Rescored '.$ranking->rescore().' post(s).');

        return self::SUCCESS;
    }
}
