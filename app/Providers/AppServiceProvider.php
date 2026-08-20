<?php
namespace App\Providers;

use App\Contracts\SmsServiceInterface;
use App\Models\Asset;
use App\Models\Bid;
use App\Models\Dispute;
use App\Models\DisputeResolution;
use App\Models\Offer;
use App\Policies\AssetPolicy;
use App\Policies\BidPolicy;
use App\Policies\DisputePolicy;
use App\Policies\OfferPolicy;
use App\Services\AuditLogger;
use App\Services\BidService;
use App\Services\DisputeService;
use App\Services\EarningReleaseService;
use App\Services\FeeCalculator;
use App\Services\ListingService;
use App\Services\NotificationService;
use App\Services\OfferService;
use App\Services\OrderService;
use App\Services\PromotionService;
use App\Services\SettingsService;
use App\Services\Sms\BulkSmsBdService;
use App\Services\UddoktaPayService;
use App\Services\VerificationService;
use App\Services\ViewTrackingService;
use App\Services\WalletService;
use App\Services\FraudService;
use App\Services\TelegramService;
use App\Services\MessageService;
use App\Services\TicketService;
use App\Services\WithdrawalService;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // SMS — interface binding so provider can be swapped
        $this->app->singleton(SmsServiceInterface::class, BulkSmsBdService::class);

        $this->app->singleton(SettingsService::class);
        $this->app->singleton(AuditLogger::class);
        $this->app->singleton(VerificationService::class);
        $this->app->singleton(ViewTrackingService::class);
        $this->app->singleton(UddoktaPayService::class);
        $this->app->singleton(WalletService::class);
        $this->app->singleton(NotificationService::class, fn($a) => new NotificationService(
            $a->make(SmsServiceInterface::class),
        ));

        $this->app->singleton(FeeCalculator::class,
            fn($a) => new FeeCalculator($a->make(SettingsService::class)));
        $this->app->singleton(ListingService::class,
            fn($a) => new ListingService($a->make(SettingsService::class)));
        $this->app->singleton(OfferService::class, fn($a) => new OfferService(
            $a->make(SettingsService::class),
            $a->make(MessageService::class),
            $a->make(NotificationService::class),
        ));
        $this->app->singleton(BidService::class,
            fn($a) => new BidService($a->make(NotificationService::class)));
        $this->app->singleton(OrderService::class, fn($a) => new OrderService(
            $a->make(FeeCalculator::class),
            $a->make(SettingsService::class),
            $a->make(UddoktaPayService::class),
            $a->make(AuditLogger::class),
            $a->make(WalletService::class),
            $a->make(BidService::class),
        ));
        $this->app->singleton(WithdrawalService::class, fn($a) => new WithdrawalService(
            $a->make(SettingsService::class),
            $a->make(WalletService::class),
            $a->make(AuditLogger::class),
        ));
        $this->app->singleton(DisputeService::class, fn($a) => new DisputeService(
            $a->make(WalletService::class),
            $a->make(AuditLogger::class),
            $a->make(NotificationService::class),
        ));
        $this->app->singleton(EarningReleaseService::class, fn($a) => new EarningReleaseService(
            $a->make(WalletService::class),
            $a->make(AuditLogger::class),
        ));
        $this->app->singleton(FraudService::class);
        $this->app->singleton(TelegramService::class);
        $this->app->singleton(MessageService::class);
        $this->app->singleton(TicketService::class, fn($a) => new TicketService(
            $a->make(AuditLogger::class),
            $a->make(NotificationService::class),
            $a->make(TelegramService::class),
        ));
        $this->app->singleton(PromotionService::class, fn($a) => new PromotionService(
            $a->make(WalletService::class),
            $a->make(NotificationService::class),
            $a->make(AuditLogger::class),
        ));
    }

    public function boot(): void
    {
        Gate::policy(Asset::class, AssetPolicy::class);
        Gate::policy(Bid::class, BidPolicy::class);
        Gate::policy(Offer::class, OfferPolicy::class);
        Gate::policy(Dispute::class, DisputePolicy::class);
        // Accept/decline/withdraw authorize against the proposal itself, which
        // resolves its own dispute — so the resolution needs the policy too.
        Gate::policy(DisputeResolution::class, DisputePolicy::class);

        // Super-admin bypass
        Gate::before(fn($user) => $user->hasRole('admin') ? true : null);

        $permissions = [
            // existing
            'users.view','users.edit','users.suspend',
            'listings.view','listings.approve','listings.edit','listings.suspend',
            'orders.view','payments.view','refunds.manage',
            'disputes.manage','withdrawals.view','withdrawals.approve',
            'verification.review','categories.manage','settings.manage',
            'reports.view','tickets.manage',
            // Part 6
            'promotions.view','promotions.manage','promotions.feature','promotions.refund',
            'notifications.view','notifications.manage','notifications.broadcast',
            'sms.view','sms.manage','sms.resend',
            'settings.notifications','settings.sms',
            // Part 7
            'users.delete','listings.restore','offers.view',
            'disputes.view','disputes.resolve',
            'refunds.view','refunds.create','refunds.approve',
            'withdrawals.reject','withdrawals.process','withdrawals.complete',
            'wallets.view','wallets.reconcile','wallets.adjust',
            'orders.manage','payments.view',
            'staff.view','staff.manage','roles.view','roles.manage',
            'audit.view','reports.export','tickets.view','tickets.assign',
            // settings.view was missing from this list, so the read side of the
            // settings screen only resolved through AuthServiceProvider's
            // DB-driven registration — which silently registers nothing when the
            // permissions table is unreadable at boot (a fresh install, or a test
            // that seeds after the container is built).
            'settings.view',
        ];

        foreach ($permissions as $perm) {
            Gate::define($perm, fn($user) => $user->hasPermission($perm));
        }

        Blade::directive('money', fn($e) => "<?php echo \\App\\Support\\Money::format((int)({$e})); ?>");
    }
}
