<?php

namespace App\Console\Commands;

use App\Services\ReportService;
use Illuminate\Console\Command;

class ResolveDueReports extends Command
{
    protected $signature = 'reports:resolve-due';

    protected $description = 'Close report votes whose 24-hour window is over';

    public function handle(ReportService $reports): int
    {
        $this->info('Closed '.$reports->sweepDue().' vote(s).');

        return self::SUCCESS;
    }
}
