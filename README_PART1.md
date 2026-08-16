# Digital Asset Marketplace — Part 1 (1a + 1b)

> Bangladesh-focused digital asset marketplace (social accounts, websites, domains, software).
> Built in Laravel 11 + Tailwind CSS 3. **No Breeze, no Bootstrap.**

---

## Three confirmed decisions applied throughout

| # | Decision | Applied |
|---|----------|---------|
| 1 | Fresh Laravel 11 app | All files assume a fresh `laravel/laravel` install |
| 2 | Tailwind CSS, mobile-first | Custom design tokens, no Bootstrap anywhere |
| 3 | Integer minor units (poisha) | 1 BDT = 100 poisha everywhere; rates in basis points |

---

## Setup (run once after extracting)

```bash
# 1. Create fresh Laravel app and copy all files on top
composer create-project laravel/laravel marketplace-app
cd marketplace-app
# Copy every file from this archive into the project root

# 2. Install JS dependencies and build assets
npm install
npm run build       # or: npm run dev (for watch mode)

# 3. Configure .env
cp .env.example .env
php artisan key:generate
# Set DB_*, MAIL_*, APP_NAME, APP_URL

# 4. Add the private filesystem disk (for verification documents)
# In config/filesystems.php → 'disks' array, add:
#   'private' => [
#       'driver' => 'local',
#       'root'   => storage_path('app/private'),
#       'visibility' => 'private',
#   ],

# 5. Run migrations and seed
php artisan migrate
php artisan db:seed

# 6. Serve
php artisan serve
```

Default super-admin: **admin@marketplace.test** / **password** — change immediately.

---

## Money convention (Decision 3)

| Concept | Storage | Example |
|---------|---------|---------|
| Prices, balances, fees | `unsignedBigInteger` poisha | ৳1 000 → 100000 |
| Fee rates | `unsignedInteger` basis points | 10% → 1000 bp |
| Display | `Money::format(int $poisha)` | → `৳1,000.00` |
| Math | `Money::percentOf($p, $bp)` | round-half-up, no floats |

Never store BDT as `decimal`. Never pass floats to fee calculations.

---

## Design system (Tailwind tokens)

- **Brand indigo** — primary UI, CTAs, links
- **Mint teal** — *reserved exclusively* for money amounts, earnings, verified badges
- **Amber** — promotions, pending states (sparingly)
- **Fonts** — Sora (display headings), Inter (body), JetBrains Mono (all ৳ amounts — the ledger signature)
- **Components** — `.btn-*`, `.input`, `.card`, `.badge-*`, `.nav-link` in `resources/css/app.css`
- **Blade components** — `<x-money>`, `<x-button>`, `<x-input>`, `<x-card>`, `<x-status-badge>`, `<x-alert>`, `<x-empty-state>`, `<x-section-scaffold>`

---

## What Part 1 delivers

### Backend (1a)
- 17 migrations, ~30 tables, all money columns integer poisha, rates basis points
- 24 Eloquent models with enum casts, soft deletes, relations
- 9 PHP enums (UserStatus, VerificationStatus, AssetStatus, OrderStatus, OfferStatus, WithdrawalStatus, TicketStatus, DisputeStatus, TransactionType)
- `Money::format()` / `Money::percentOf()` — integer math, no float drift
- `FeeCalculator::forOrder()` — returns fee snapshot array (poisha + bp) for order creation
- `SettingsService` — DB-first / config-fallback, cached, all return int
- `AuditLogger` — polymorphic audit trail
- RBAC: roles/permissions seeded, `HasRolesAndPermissions` trait, `Gate::before` for admin
- Seeders: PermissionRoleSeeder, SettingsSeeder (poisha/bp), CategorySeeder, DatabaseSeeder (super-admin + wallet)

### UI / routes (1b)
- Tailwind design system + Alpine.js
- Layouts: public, auth (2-col brand panel), dashboard (sidebar), admin (dark sidebar + mobile drawer)
- Pages: home (hero + categories + how-it-works), marketplace index (filters + grid), category, asset detail (buy/offer disabled — Part 2), auth (login/register/forgot/reset/verify), dashboard overview, verification form, admin overview, legal×7, contact, FAQ, public profile
- All named routes wired — no 404s from nav; unbuilt sections render a `<x-section-scaffold>` placeholder
- Middleware: `active` (EnsureUserIsActive), `can_sell` (EnsureUserCanSell), `admin` (EnsureAdmin)
- Auth controllers: session, register (creates wallet in transaction), password reset, email verification

---

## Explicitly deferred to Part 2+

- UddoktaPay / payment gateway checkout
- Order delivery, auto-complete scheduler, earning release
- Offer creation / acceptance / expiry
- Withdrawal request + admin approval flow
- Dispute / refund workflow
- Messaging UI (conversations are modelled; UI comes next)
- Promotion purchase
- Review system
- SMS (BulkSMSBD abstraction exists; sending not wired)
- Secure verification document upload to private disk + admin review UI
- Listing create/edit form

---

## File tree (key files)

```
app/
  Enums/            9 enums
  Http/
    Controllers/    Auth/ Dashboard/ Admin/ + Page/Marketplace/Profile
    Middleware/     EnsureUserIsActive  EnsureUserCanSell  EnsureAdmin
  Models/           24 models
  Providers/        AppServiceProvider  AuthServiceProvider
  Services/         FeeCalculator  SettingsService  AuditLogger
  Support/
    Money.php       poisha ↔ BDT, percentOf()
    Traits/         HasRolesAndPermissions

bootstrap/
  app.php           middleware aliases
  providers.php     provider registration

config/marketplace.php   structural fallbacks (poisha + bp)

database/
  migrations/       17 files (000100–001700)
  seeders/          DatabaseSeeder  PermissionRoleSeeder  SettingsSeeder  CategorySeeder

resources/
  css/app.css       @layer base/components (design tokens)
  js/app.js         Alpine.js bootstrap
  views/
    components/     layouts/  money  button  input  card  status-badge  alert  empty-state  section-scaffold
    partials/       header  footer  flash  mobile-nav  dashboard-sidebar  admin-sidebar
    pages/          home  contact  faq
    marketplace/    index  category  show  partials/filters  partials/asset-card
    auth/           login  register  forgot-password  reset-password  verify-email
    dashboard/      index  section  verification
    admin/          index  section
    legal/          show
    profile/        show

routes/web.php      all named routes (public + guest + auth + dashboard.* + admin.*)
```
