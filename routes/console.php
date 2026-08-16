<?php
use App\Console\Commands\AutoCompleteOrdersCommand;
use App\Console\Commands\ExpireOffersCommand;
use App\Console\Commands\ExpirePromotionsCommand;
use App\Console\Commands\ReleaseEarningsCommand;
use App\Console\Commands\WarnExpiringPromotionsCommand;
use Illuminate\Support\Facades\Schedule;

// Prevent task overlap for all financial/expiry jobs
Schedule::command(ExpireOffersCommand::class)->hourly()->withoutOverlapping();
Schedule::command(AutoCompleteOrdersCommand::class)->everyFifteenMinutes()->withoutOverlapping();
Schedule::command(ReleaseEarningsCommand::class)->everyFifteenMinutes()->withoutOverlapping();
Schedule::command(ExpirePromotionsCommand::class)->everyFiveMinutes()->withoutOverlapping();
Schedule::command(WarnExpiringPromotionsCommand::class)->hourly()->withoutOverlapping();

// Daily: prune old read notifications (keep last 90 days)
Schedule::call(function () {
    \App\Models\User::query()->each(fn($u) =>
        $u->notifications()->whereNotNull('read_at')
          ->where('created_at', '<', now()->subDays(90))->delete()
    );
})->dailyAt('03:00');
