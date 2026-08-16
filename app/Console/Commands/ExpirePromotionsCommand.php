<?php
namespace App\Console\Commands;

use App\Services\PromotionService;
use Illuminate\Console\Command;

class ExpirePromotionsCommand extends Command
{
    protected $signature   = 'promotions:expire';
    protected $description = 'Expire active promotions past their end date.';

    public function handle(PromotionService $service): void
    {
        $count = $service->expireStale();
        $this->info("Expired {$count} promotion(s).");
    }
}
