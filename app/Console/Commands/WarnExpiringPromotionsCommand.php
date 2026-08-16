<?php
namespace App\Console\Commands;

use App\Services\PromotionService;
use Illuminate\Console\Command;

class WarnExpiringPromotionsCommand extends Command
{
    protected $signature   = 'promotions:warn-expiring';
    protected $description = 'Send 24-hour expiry warnings for active promotions.';

    public function handle(PromotionService $service): void
    {
        $count = $service->sendExpiryWarnings();
        $this->info("Sent {$count} expiry warning(s).");
    }
}
