<?php

use App\Http\Controllers\Auth\{AuthenticatedSessionController,EmailVerificationController,NewPasswordController,PasswordResetLinkController,RegisteredUserController};
use App\Http\Controllers\Admin\{DashboardController as AdminDashboard,FraudController,
UserController as AdminUser,VerificationController as AdminVerification,ListingController as AdminListing,CategoryController as AdminCategory,WithdrawalController as AdminWithdrawal,SettingsController as AdminSettings,AuditController as AdminAudit,OfferController as AdminOffer,OrderController as AdminOrder,PaymentController as AdminPayment,DisputeController as AdminDispute,WalletController as AdminWallet,PromotionController as AdminPromotion,NotificationController as AdminNotification,TicketController as AdminTicket,StaffController,RoleController,ReportController,MessageReportController,SupportTemplateController,WithdrawalMethodController as AdminWithdrawalMethod};
use App\Http\Controllers\Dashboard\{CustomOfferController,DashboardController,DisputeController,FavoriteController,ListingController,MessageController,NotificationController,OrderController,ProfileController as DashProfile,PromotionController,ReviewController,TicketController,WalletController,WithdrawalController};
use App\Http\Controllers\{BidController,CheckoutController,ListingContactController,MarketplaceController,PageController,ProfileController,SeoController};
use Illuminate\Support\Facades\Route;

// ── Public ─────────────────────────────────────────────────────────
Route::get('/', [PageController::class, 'home'])->name('home');
Route::get('/marketplace', [MarketplaceController::class, 'index'])->name('marketplace.index');
Route::get('/asset/{slug}', [MarketplaceController::class, 'show'])->name('marketplace.show');
Route::get('/users/{identifier}', [ProfileController::class, 'show'])->name('profile.show');
Route::get('/legal/{slug}', [PageController::class, 'legal'])->name('legal');
Route::get('/contact', [PageController::class, 'contact'])->name('contact');
Route::post('/contact', [PageController::class, 'contactSubmit'])->name('contact.submit');
Route::get('/faq', [PageController::class, 'faq'])->name('faq');

// ── SEO ──────────────────────────────────────────────────────────
Route::get('/robots.txt', [SeoController::class, 'robots'])->name('robots');
Route::get('/sitemap.xml', [SeoController::class, 'sitemap'])->name('sitemap');

// ── Guest-only auth ──────────────────────────────────────────────────
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:10,1');
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register/send-otp', [RegisteredUserController::class, 'sendOtp'])->name('register.send-otp')->middleware('throttle:5,1');
    Route::get('/register/verify', [RegisteredUserController::class, 'showVerify'])->name('register.verify');
    Route::post('/register/verify-otp', [RegisteredUserController::class, 'verifyOtp'])->name('register.verify-otp')->middleware('throttle:10,1');
    Route::get('/register/details', [RegisteredUserController::class, 'showDetails'])->name('register.details');
    Route::post('/register', [RegisteredUserController::class, 'store'])->name('register.store')->middleware('throttle:5,1');
    Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])->name('password.email')->middleware('throttle:5,1');
    Route::get('/forgot-password/verify', [NewPasswordController::class, 'create'])->name('password.verify');
    Route::post('/forgot-password/verify', [NewPasswordController::class, 'verifyOtp'])->name('password.verify-otp')->middleware('throttle:10,1');
    Route::get('/forgot-password/reset', [NewPasswordController::class, 'showReset'])->name('password.reset-form');
    Route::post('/forgot-password/reset', [NewPasswordController::class, 'store'])->name('password.update')->middleware('throttle:5,1');
});

// ── Payment callbacks ────────────────────────────────────────────────
// These belong to the gateway, not to the browser: UddoktaPay decides which
// method it returns the buyer with, so the return endpoints accept both rather
// than 405-ing on a POST. They stay unauthenticated on purpose — an expired
// session must not stop a completed payment from being confirmed — and they
// only ever redirect, never render. The buyer-facing page is checkout.success.
Route::post('/checkout/webhook', [CheckoutController::class, 'webhook'])->name('checkout.callback.webhook')->withoutMiddleware(['web']);
Route::match(['get', 'post'], '/checkout/callback', [CheckoutController::class, 'callback'])->name('checkout.callback.return');
Route::match(['get', 'post'], '/checkout/cancel', [CheckoutController::class, 'cancel'])->name('checkout.callback.cancel');

// ── Authenticated ────────────────────────────────────────────────────
Route::middleware(['auth', 'active'])->group(function () {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    // Email verification
    Route::get('/verify-email', [EmailVerificationController::class, 'notice'])->name('verification.notice');
    Route::get('/verify-email/{id}/{hash}', [EmailVerificationController::class, 'verify'])->middleware(['signed','throttle:6,1'])->name('verification.verify');
    Route::post('/verify-email/send', [EmailVerificationController::class, 'send'])->middleware('throttle:6,1')->name('verification.send');

    // Checkout. The success page is declared before /checkout/{slug} or the
    // wildcard would swallow it and look for a listing slugged "success".
    Route::get('/checkout/success', [CheckoutController::class, 'success'])->name('checkout.success');
    Route::get('/checkout/{slug}', [CheckoutController::class, 'show'])->name('checkout.show');
    Route::post('/checkout', [CheckoutController::class, 'initiate'])->middleware('throttle:10,1')->name('checkout.initiate');

    // Favorites
    Route::post('/favorites/toggle', [FavoriteController::class, 'toggle'])->middleware('throttle:30,1')->name('favorites.toggle');
    Route::delete('/favorites/{favorite}', [FavoriteController::class, 'remove'])->name('favorites.remove');

    // Bids — single-item listings only; the policy and BidService enforce that.
    Route::post('/listings/{asset:slug}/bids', [BidController::class, 'store'])->middleware('throttle:20,1')->name('bids.store');
    Route::post('/bids/{bid}/accept', [BidController::class, 'accept'])->name('bids.accept');
    Route::post('/bids/{bid}/reject', [BidController::class, 'reject'])->name('bids.reject');
    Route::post('/bids/{bid}/cancel', [BidController::class, 'cancel'])->name('bids.cancel');

    // Contact Seller — opens (or reuses) the buyer↔seller thread for a listing.
    Route::post('/listings/{asset:slug}/contact', ListingContactController::class)->middleware('throttle:20,1')->name('listings.contact');

    // Custom offers — chat-scoped. Creation lives under the conversation route
    // below; responding is keyed on the offer itself.
    Route::post('/offers/{offer}/accept', [CustomOfferController::class, 'accept'])->name('offers.accept');
    Route::post('/offers/{offer}/reject', [CustomOfferController::class, 'reject'])->name('offers.reject');
    Route::post('/offers/{offer}/cancel', [CustomOfferController::class, 'cancel'])->name('offers.cancel');

    // Delivery attachment
    Route::get('/orders/{order}/delivery-attachment', [OrderController::class, 'deliveryAttachment'])->name('orders.delivery.attachment');

    // Message attachment + report (Part 8)
    Route::get('/messages/{message}/attachment', [MessageController::class, 'attachment'])->name('messages.attachment');
    Route::post('/messages/{message}/report', [MessageController::class, 'reportMessage'])->middleware('throttle:10,1')->name('messages.report');

    // AJAX polling endpoint (Part 8 — polling fallback)
    Route::get('/api/conversations/{conversation}/poll', [MessageController::class, 'poll'])->name('api.conversations.poll');
    Route::get('/api/messages/unread-count', [MessageController::class, 'unreadCount'])->name('api.messages.unread');

    // ── Dashboard ────────────────────────────────────────────────────
    Route::prefix('dashboard')->name('dashboard')->group(function () {
        Route::get('/', [DashboardController::class, 'index']);

        Route::get('/verification', [DashboardController::class, 'verification'])->name('.verification');
        Route::post('/verification', [DashboardController::class, 'submitVerification'])->name('.verification.submit');

        // Listings
        Route::get('/listings', [ListingController::class, 'index'])->name('.listings');
        Route::get('/listings/create', [ListingController::class, 'create'])->middleware('can_sell')->name('.listings.create');
        Route::post('/listings', [ListingController::class, 'store'])->middleware('can_sell')->name('.listings.store');
        Route::get('/listings/fee-preview', [ListingController::class, 'feePreview'])->name('.listings.fee-preview');
        Route::get('/listings/{listing}', [ListingController::class, 'show'])->name('.listings.show');
        Route::get('/listings/{listing}/edit', [ListingController::class, 'edit'])->name('.listings.edit');
        Route::patch('/listings/{listing}', [ListingController::class, 'update'])->name('.listings.update');
        Route::post('/listings/{listing}/submit', [ListingController::class, 'submitDraft'])->name('.listings.submit');
        Route::post('/listings/{listing}/pause', [ListingController::class, 'togglePause'])->name('.listings.pause');
        Route::delete('/listings/{listing}/images/{image}', [ListingController::class, 'deleteImage'])->name('.listings.image-delete');

        // Orders
        Route::get('/orders', [OrderController::class, 'index'])->name('.orders');
        Route::get('/orders/{order}', [OrderController::class, 'show'])->name('.orders.show');
        Route::get('/orders/{order}/deliver', [OrderController::class, 'deliverForm'])->name('.orders.deliver');
        Route::post('/orders/{order}/deliver', [OrderController::class, 'deliver'])->name('.orders.deliver.submit');
        Route::post('/orders/{order}/complete', [OrderController::class, 'complete'])->name('.orders.complete');
        Route::get('/orders/{order}/dispute', [OrderController::class, 'openDisputeForm'])->name('.orders.dispute');
        Route::post('/orders/{order}/dispute', [OrderController::class, 'openDispute'])->name('.orders.dispute.submit');
        Route::get('/orders/{order}/delivery-file', [OrderController::class, 'deliveryAttachment'])->name('.orders.delivery-file');

        // Disputes — the buyer's and seller's side of one. Every action here is
        // authorized by DisputePolicy; the admin decisions live under /admin.
        Route::get('/disputes', [DisputeController::class, 'index'])->name('.disputes');
        Route::get('/disputes/{dispute}', [DisputeController::class, 'show'])->name('.disputes.show');
        Route::post('/disputes/{dispute}/messages', [DisputeController::class, 'message'])->name('.disputes.message');
        Route::post('/disputes/{dispute}/evidence', [DisputeController::class, 'storeEvidence'])->name('.disputes.evidence.store');
        // The only route to a private evidence file — it authorizes the reader.
        Route::get('/disputes/{dispute}/evidence/{evidence}', [DisputeController::class, 'evidence'])->name('.disputes.evidence');
        Route::post('/disputes/{dispute}/escalate', [DisputeController::class, 'escalate'])->name('.disputes.escalate');
        Route::post('/disputes/{dispute}/cancel', [DisputeController::class, 'cancel'])->name('.disputes.cancel');
        Route::post('/disputes/{dispute}/proposals', [DisputeController::class, 'propose'])->name('.disputes.propose');

        // Answering a proposal keys on the proposal, not the dispute — the policy
        // resolves the dispute from it.
        Route::post('/dispute-proposals/{resolution}/accept', [DisputeController::class, 'acceptProposal'])->name('.disputes.proposal.accept');
        Route::post('/dispute-proposals/{resolution}/decline', [DisputeController::class, 'declineProposal'])->name('.disputes.proposal.decline');
        Route::post('/dispute-proposals/{resolution}/withdraw', [DisputeController::class, 'withdrawProposal'])->name('.disputes.proposal.withdraw');

        // Reviews
        Route::get('/orders/{order}/review', [ReviewController::class, 'create'])->name('.orders.review');
        Route::post('/orders/{order}/review', [ReviewController::class, 'store'])->name('.orders.review.store');

        // Wallet + Withdrawals
        Route::get('/wallet', [WalletController::class, 'index'])->name('.wallet');
        Route::get('/withdrawals', [WithdrawalController::class, 'index'])->name('.withdrawals');
        Route::post('/withdrawals', [WithdrawalController::class, 'store'])->middleware('throttle:5,1')->name('.withdrawals.store');
        // The user takes back their own pending request; the service re-checks
        // ownership and the status under a lock before returning the funds.
        Route::post('/withdrawals/{withdrawal}/cancel', [WithdrawalController::class, 'cancel'])->name('.withdrawals.cancel');

        // Promotions
        Route::get('/promotions', [PromotionController::class, 'index'])->name('.promotions');
        Route::get('/promotions/buy', [PromotionController::class, 'create'])->name('.promotions.create');
        Route::post('/promotions', [PromotionController::class, 'store'])->middleware('throttle:5,1')->name('.promotions.store');

        // Messages (Part 8 — enhanced)
        Route::get('/messages', [MessageController::class, 'index'])->name('.messages');
        Route::post('/messages/{conversation}/send', [MessageController::class, 'send'])->middleware('throttle:30,1')->name('.messages.send');
        Route::post('/messages/{conversation}/offers', [CustomOfferController::class, 'store'])->middleware('throttle:10,1')->name('.messages.offers.store');

        // Notifications
        Route::get('/notifications', [NotificationController::class, 'index'])->name('.notifications');
        Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead'])->name('.notifications.read');
        Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('.notifications.read-all');
        Route::delete('/notifications/{id}', [NotificationController::class, 'destroy'])->name('.notifications.destroy');

        // Favorites
        Route::get('/favorites', [FavoriteController::class, 'index'])->name('.favorites');

        // Tickets (Part 7 + 8 enhanced)
        Route::get('/tickets', [TicketController::class, 'index'])->name('.tickets');
        Route::get('/tickets/create', [TicketController::class, 'create'])->name('.tickets.create');
        Route::post('/tickets', [TicketController::class, 'store'])->name('.tickets.store');
        Route::get('/tickets/{ticket}', [TicketController::class, 'show'])->name('.tickets.show');
        Route::post('/tickets/{ticket}/reply', [TicketController::class, 'reply'])->name('.tickets.reply');

        // Profile + Security
        Route::get('/profile', [DashProfile::class, 'show'])->name('.profile');
        Route::patch('/profile', [DashProfile::class, 'update'])->name('.profile.update');
        Route::get('/security', [DashProfile::class, 'security'])->name('.security');
        Route::patch('/security/password', [DashProfile::class, 'updatePassword'])->name('.security.password');

        Route::get('/purchases', [DashboardController::class, 'purchases'])->name('.purchases');
        Route::get('/settings', [DashboardController::class, 'settings'])->name('.settings');
    });

    // ── Admin ────────────────────────────────────────────────────────
    Route::prefix('admin')->name('admin')->middleware('admin')->group(function () {
        Route::get('/', [AdminDashboard::class, 'index'])->name('.dashboard');

        // Users
        Route::get('/users', [AdminUser::class, 'index'])->name('.users');
        Route::get('/users/{user}', [AdminUser::class, 'show'])->name('.users.show');
        Route::post('/users/{user}/suspend', [AdminUser::class, 'suspend'])->name('.users.suspend');
        Route::post('/users/{user}/restore', [AdminUser::class, 'restore'])->name('.users.restore');
        Route::post('/users/{user}/note', [AdminUser::class, 'note'])->name('.users.note');

        // Verification
        Route::get('/verification', [AdminVerification::class, 'index'])->name('.verification');
        Route::get('/verification/{verification}', [AdminVerification::class, 'show'])->name('.verification.show');
        Route::post('/verification/{verification}/approve', [AdminVerification::class, 'approve'])->name('.verification.approve');
        Route::post('/verification/{verification}/reject', [AdminVerification::class, 'reject'])->name('.verification.reject');
        Route::get('/verification/{verification}/document/{type}', [AdminVerification::class, 'serveDocument'])->name('.verification.document');

        // Listings
        Route::get('/listings', [AdminListing::class, 'index'])->name('.listings');
        Route::get('/listings/{listing}', [AdminListing::class, 'show'])->name('.listings.show');
        Route::post('/listings/{listing}/approve', [AdminListing::class, 'approve'])->name('.listings.approve');
        Route::post('/listings/{listing}/reject', [AdminListing::class, 'reject'])->name('.listings.reject');
        Route::post('/listings/{listing}/request-changes', [AdminListing::class, 'requestChanges'])->name('.listings.request-changes');
        Route::post('/listings/{listing}/suspend', [AdminListing::class, 'suspend'])->name('.listings.suspend');
        Route::post('/edits/{edit}/approve', [AdminListing::class, 'approveEdit'])->name('.listings.approve-edit');
        Route::post('/edits/{edit}/reject', [AdminListing::class, 'rejectEdit'])->name('.listings.reject-edit');
        Route::post('/listings/{asset}/feature', [AdminPromotion::class, 'feature'])->name('.listings.feature');

        // Categories
        Route::get('/categories', [AdminCategory::class, 'index'])->name('.categories');
        Route::get('/categories/create', [AdminCategory::class, 'create'])->name('.categories.create');
        Route::post('/categories', [AdminCategory::class, 'store'])->name('.categories.store');
        Route::get('/categories/{category}/edit', [AdminCategory::class, 'edit'])->name('.categories.edit');
        Route::patch('/categories/{category}', [AdminCategory::class, 'update'])->name('.categories.update');
        Route::patch('/categories/{category}/deactivate', [AdminCategory::class, 'deactivate'])->name('.categories.deactivate');
        Route::post('/categories/{category}/attributes', [AdminCategory::class, 'storeAttribute'])->name('.categories.attributes.store');
        Route::patch('/categories/{category}/attributes/{attribute}', [AdminCategory::class, 'updateAttribute'])->name('.categories.attributes.update');

        // Orders + Payments + Offers
        Route::get('/orders', [AdminOrder::class, 'index'])->name('.orders');
        Route::get('/orders/{order}', [AdminOrder::class, 'show'])->name('.orders.show');
        Route::get('/payments', [AdminPayment::class, 'index'])->name('.payments');
        Route::get('/offers', [AdminOffer::class, 'index'])->name('.offers');

        // Disputes
        Route::get('/disputes', [AdminDispute::class, 'index'])->name('.disputes');
        Route::get('/disputes/{dispute}', [AdminDispute::class, 'show'])->name('.disputes.show');
        Route::post('/disputes/{dispute}/full-refund', [AdminDispute::class, 'fullRefund'])->name('.disputes.full-refund');
        Route::post('/disputes/{dispute}/partial-refund', [AdminDispute::class, 'partialRefund'])->name('.disputes.partial-refund');
        Route::post('/disputes/{dispute}/release-seller', [AdminDispute::class, 'releaseToSeller'])->name('.disputes.release-seller');
        Route::post('/disputes/{dispute}/replacement', [AdminDispute::class, 'replacement'])->name('.disputes.replacement');
        Route::post('/disputes/{dispute}/close', [AdminDispute::class, 'close'])->name('.disputes.close');
        // Staff take ownership of a dispute the parties will not settle themselves.
        Route::post('/disputes/{dispute}/escalate', [AdminDispute::class, 'escalate'])->name('.disputes.escalate');
        Route::post('/disputes/{dispute}/message', [AdminDispute::class, 'message'])->name('.disputes.message');
        Route::post('/disputes/{dispute}/internal-note', [AdminDispute::class, 'internalNote'])->name('.disputes.note');
        Route::post('/disputes/{dispute}/request-evidence', [AdminDispute::class, 'requestEvidence'])->name('.disputes.request-evidence');

        // Finance
        Route::get('/wallets', [AdminWallet::class, 'index'])->name('.wallets');
        Route::get('/wallets/{wallet}', [AdminWallet::class, 'show'])->name('.wallets.show');
        Route::post('/wallets/{wallet}/adjust', [AdminWallet::class, 'adjust'])->name('.wallets.adjust');
        Route::get('/withdrawals', [AdminWithdrawal::class, 'index'])->name('.withdrawals');
        Route::get('/withdrawals/{withdrawal}', [AdminWithdrawal::class, 'show'])->name('.withdrawals.show');
        Route::post('/withdrawals/{withdrawal}/reject', [AdminWithdrawal::class, 'reject'])->name('.withdrawals.reject');
        Route::post('/withdrawals/{withdrawal}/complete', [AdminWithdrawal::class, 'complete'])->name('.withdrawals.complete');

        // Promotions
        Route::get('/promotions', [AdminPromotion::class, 'index'])->name('.promotions');
        Route::post('/promotions/{promotion}/unfeature', [AdminPromotion::class, 'unfeature'])->name('.promotions.unfeature');

        // Notifications + SMS
        Route::get('/notifications', [AdminNotification::class, 'index'])->name('.notifications');
        Route::get('/notifications/sms', [AdminNotification::class, 'smsLogs'])->name('.sms-logs');

        // Support Tickets
        Route::get('/tickets', [AdminTicket::class, 'index'])->name('.tickets');
        Route::get('/tickets/{ticket}', [AdminTicket::class, 'show'])->name('.tickets.show');
        Route::post('/tickets/{ticket}/reply', [AdminTicket::class, 'reply'])->name('.tickets.reply');
        Route::post('/tickets/{ticket}/note', [AdminTicket::class, 'internalNote'])->name('.tickets.note');
        Route::post('/tickets/{ticket}/assign', [AdminTicket::class, 'assign'])->name('.tickets.assign');
        Route::patch('/tickets/{ticket}/status', [AdminTicket::class, 'status'])->name('.tickets.status');
        Route::patch('/tickets/{ticket}/priority', [AdminTicket::class, 'priority'])->name('.tickets.priority');

        // Staff Management
        Route::get('/staff', [StaffController::class, 'index'])->name('.staff');
        Route::get('/staff/create', [StaffController::class, 'create'])->name('.staff.create');
        Route::post('/staff', [StaffController::class, 'store'])->name('.staff.store');
        Route::get('/staff/{user}', [StaffController::class, 'show'])->name('.staff.show');
        Route::post('/staff/{user}/role', [StaffController::class, 'assignRole'])->name('.staff.role');
        Route::post('/staff/{user}/suspend', [StaffController::class, 'suspend'])->name('.staff.suspend');
        Route::post('/staff/{user}/restore', [StaffController::class, 'restore'])->name('.staff.restore');

        // Role Management
        Route::get('/roles', [RoleController::class, 'index'])->name('.roles');
        Route::post('/roles', [RoleController::class, 'store'])->name('.roles.store');
        Route::get('/roles/{role}/edit', [RoleController::class, 'edit'])->name('.roles.edit');
        Route::patch('/roles/{role}', [RoleController::class, 'update'])->name('.roles.update');

        // Reports
        Route::get('/reports', [ReportController::class, 'index'])->name('.reports');

        // Message Reports (Part 8)
        Route::get('/message-reports', [MessageReportController::class, 'index'])->name('.message-reports');
        Route::post('/message-reports/{report}/review', [MessageReportController::class, 'review'])->name('.message-reports.review');

        // Support Templates (Part 8)
        Route::get('/support-templates', [SupportTemplateController::class, 'index'])->name('.support-templates');
        Route::post('/support-templates', [SupportTemplateController::class, 'store'])->name('.support-templates.store');
        Route::patch('/support-templates/{template}', [SupportTemplateController::class, 'update'])->name('.support-templates.update');
        Route::get('/support-templates/{template}', [SupportTemplateController::class, 'get'])->name('.support-templates.get');

        // Settings + Audit
        // Anti-Fraud (Part 9)
        Route::get('/fraud', [FraudController::class, 'index'])->name('.fraud');
        Route::get('/fraud/{user}', [FraudController::class, 'show'])->name('.fraud.show');
        Route::post('/fraud/{user}/clear', [FraudController::class, 'clear'])->name('.fraud.clear');
        Route::post('/fraud/{user}/restrict', [FraudController::class, 'restrict'])->name('.fraud.restrict');

                Route::get('/settings', [AdminSettings::class, 'index'])->name('.settings');
        Route::patch('/settings', [AdminSettings::class, 'update'])->name('.settings.update');
        // Admin-editable theme colors (Brand/Money/Featured/Danger).
        Route::patch('/settings/theme', [AdminSettings::class, 'updateTheme'])->name('.settings.theme.update');
        // Admin-managed payout methods, edited from the Settings page.
        Route::post('/settings/withdrawal-methods', [AdminWithdrawalMethod::class, 'store'])->name('.settings.methods.store');
        Route::patch('/settings/withdrawal-methods/{method}', [AdminWithdrawalMethod::class, 'update'])->name('.settings.methods.update');
        Route::delete('/settings/withdrawal-methods/{method}', [AdminWithdrawalMethod::class, 'destroy'])->name('.settings.methods.destroy');
        Route::get('/audit-logs', [AdminAudit::class, 'index'])->name('.audit');
        Route::get('/activity-logs', [AdminAudit::class, 'index'])->name('.activity');
    });
});
