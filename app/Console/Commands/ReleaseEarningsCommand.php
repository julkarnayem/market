<?php
namespace App\Console\Commands;

use App\Services\EarningReleaseService;
use Illuminate\Console\Command;

class ReleaseEarningsCommand extends Command
{
    protected $signature   = 'earnings:release';
    protected $description = 'Credit seller wallets for completed orders past the 8-hour earning lock.';

    public function handle(EarningReleaseService $service): void
    {
        $count = $service->releaseEligible();
        $this->info("Released earnings for {$count} order(s).");
    }
}
