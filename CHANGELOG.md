# Changelog

## Parts 1A–9 → Complete (2026)

### Part 1A — Foundation
- Laravel 11 project structure, Tailwind CSS design system
- RBAC: roles, permissions, role_user, permission_role tables
- User model with HasRolesAndPermissions trait
- Money class (integer poisha, no floats), FeeCalculator, SettingsService
- Database migrations: users, roles, permissions, settings
- Admin seeder with default credentials

### Part 1B — Verification & Listings
- Seller verification flow (NID + selfie, encrypted at rest, private disk)
- Asset/listing model with full lifecycle (draft→review→published→paused→sold_out)
- AssetPolicy, ListingService (create/approve/reject/edit workflow)
- Category system with nested subcategories and 8 attribute types
- Image upload (multiple images, cover image, ordering)
- Dashboard listing CRUD with fee preview

### Part 2 — Marketplace & Search
- MarketplaceController with dynamic filters (price range, category, attributes)
- Sort whitelist (6 allowed values; no injection)
- Parameterized attribute filters (no dynamic SQL)
- ViewTrackingService (1 unique view/visitor/day)
- Asset detail page with SEO (Part 9 enhancement)
- Seller public profile

### Part 3 — Offers, Favorites
- OfferService: create/accept/reject/expireStale/enforceExpiry
- 8-hour offer expiry, active offer locks listing price
- FavoriteController with AJAX toggle
- `offers:expire` scheduled command (hourly)

### Part 4 — Payments & Orders
- UddoktaPay integration: initiate/verify (server-side only; never trust redirect)
- OrderService: initiate/confirmPayment/deliver/complete/autoComplete/openDispute
- Oversell prevention via `lockForUpdate()` + atomic decrement
- Payment never confirmed from browser redirect
- Order conversation auto-created after payment
- `orders:auto-complete` scheduled command (every 15 min)

### Part 5 — Wallet, Earnings, Withdrawals, Disputes
- WalletService: creditPending/releasePending/debitAvailable/creditAvailable/adminAdjust
- All wallet operations: `lockForUpdate()` + ledger entry (append-only)
- Earning release idempotent (`earning_released` flag)
- WithdrawalService: request/approve/reject/complete (MFS, manual admin payout)
- DisputeService: fullRefund/partialRefund/sellerRelease (all idempotent)
- `earnings:release` scheduled command (every 15 min)

### Part 6 — Promotions, SMS, Notifications
- PromotionService: paid purchase (wallet debit), admin manual feature
- Server-side promotion price whitelist
- BulkSMSBD: SmsServiceInterface, BulkSmsBdService, 16 SMS templates
- SendSmsJob: queued, 3 retries, idempotency key
- NotificationService (in-app + SMS together)
- `promotions:expire` (every 5 min), `promotions:warn-expiring` (hourly)

### Part 7 — Admin, RBAC, Support, Reports
- 5 roles: super_admin, admin, moderator, support, finance
- 37+ permissions with fine-grained access control
- StaffController: create/suspend/restore + privilege escalation protection
- RoleController: permission editor with audit logging
- TicketService: create/reply/assign/changeStatus + notifications
- Admin TicketController: full queue, assign, status, priority, internal notes
- ReportController: 15 financial metrics + CSV export
- Enhanced admin dashboard with all action queues

### Part 8 — Order Chat, Communication Center
- MessageService: idempotent send, MIME-validated attachments, soft delete
- MessageReport: one per user per message; admin review queue
- SupportResponseTemplate: safe variable substitution
- Full two-panel chat UI with unread count, reply-to, file attachment
- 5-second polling fallback when WebSocket not configured
- Broadcasting config scaffold (Reverb + Pusher + null)
- TicketStatus: WaitingForStaff added
- Ticket context linking (order, asset, withdrawal)

### Part 9 — Security, Anti-Fraud, SEO, Performance
- SetSecurityHeaders middleware: X-Content-Type-Options, X-Frame-Options, Referrer-Policy, HSTS, CSP
- Named rate limiters: login (10/min), register (5/min), password-reset (5/min), api (60/min)
- FraudService: 9 signals, rolling 30-day risk score, admin review queue
- robots.txt + XML sitemap (chunked, memory-safe)
- JSON-LD structured data on listing pages (Product + Offer schema)
- Full OG/Twitter Card meta in public layout
- Database performance indexes
- 503 error page, noindex on all private pages
- XSS audit: `{!! !!}` converted to `{{ }}` except hardcoded server strings

### Part 10 — Testing, Bug Fixes, Production Documentation
- phpunit.xml + TestCase base class
- MoneyTest (8 assertions — financial rules)
- FeeCalculatorTest (5 assertions — fee logic + business rules)
- AuthTest (6 tests — login, register, suspended, admin gate)
- SecurityTest (5 tests — IDOR, privilege escalation, rate limiting)
- WithdrawalRulesTest (4 tests — minimum, fee, insufficient balance)
- Bug fix: `admin/index.blade.php` — moved `User::where()` from view to controller
- Bug fix: `FraudController` — corrected permission names to `fraud.view`/`fraud.manage`
- Bug fix: `TicketService` — WaitingForStaff status on user replies
- `php artisan admin:create-super` command
- `.env.example` with all variables documented
- `DEPLOYMENT.md`, `ADMIN_SETUP.md`, `PRODUCTION_CHECKLIST.md`, `FINAL_QA_REPORT.md`
