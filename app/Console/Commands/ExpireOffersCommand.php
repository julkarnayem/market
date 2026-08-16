<?php
namespace App\Console\Commands;

use App\Services\OfferService;
use Illuminate\Console\Command;

class ExpireOffersCommand extends Command
{
    protected $signature   = 'offers:expire';
    protected $description = 'Mark all pending offers past their expiry time as expired.';

    public function handle(OfferService $service): void
    {
        $count = $service->expireStale();
        $this->info("Expired {$count} offer(s).");
    }
}
