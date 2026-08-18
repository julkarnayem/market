# Digital Asset Marketplace

[![CI](https://github.com/julkarnayem/market/actions/workflows/ci.yml/badge.svg)](https://github.com/julkarnayem/market/actions/workflows/ci.yml)

A Bangladesh-focused marketplace for **digital assets** — social media accounts, websites, domains and software — with escrow-style buyer protection, seller payouts to bKash/Nagad/Rocket, and a full admin back office.

**Laravel 11** · **Inertia.js v3** · **Vue 3 + TypeScript** · **Tailwind CSS 3** · **MySQL 8**

---

## Table of contents

- [What it does](#what-it-does)
- [Stack](#stack)
- [Money is never a float](#money-is-never-a-float)
- [Front end](#front-end)
- [Getting started](#getting-started)
- [Day-to-day development](#day-to-day-development)
- [Testing](#testing)
- [Scheduled work](#scheduled-work)
- [Configuration](#configuration)
- [Repository layout](#repository-layout)
- [Further documentation](#further-documentation)

---

## What it does

**Buyers** browse and filter listings, make offers, pay through UddoktaPay, and get a
configurable buyer-protection window (default 72 h) during which an order can be disputed
rather than cancelled. Funds are held until delivery is confirmed.

**Sellers** submit identity verification, create and promote listings, negotiate offers,
track earnings in a wallet ledger, and request withdrawals to a mobile financial service
account. Earnings unlock on an 8 h release timer after an order completes.

**Staff** work through an admin area covering listing and verification review, orders,
disputes and refunds, withdrawals, promotions, categories and attributes, support tickets
with response templates, message reports, a fraud-review queue, notifications (in-app +
SMS), platform settings, staff and role management, and an audit log. Access is
role-and-permission based, with `Gate::before` letting the `admin` role through.

Scale of the codebase: **175 named routes**, **38 Eloquent models**, **47 migrations**,
**19 service classes**, **9 PHP enums**, **30 Vue pages** and **22 Vue components/layouts**.

---

## Stack

| Layer | Choice |
|---|---|
| Runtime | PHP 8.2+ |
| Framework | Laravel 11 (`^11.31`) |
| Database | MySQL 8.0+ / MariaDB 10.6+ (tests run on SQLite `:memory:`) |
| Server-driven SPA | `inertiajs/inertia-laravel ^3.3` |
| Client | Vue 3.5 `<script setup lang="ts">`, TypeScript `strict: true` |
| Routing in JS | Ziggy 2.6 — named Laravel routes available to Vue as `route()` |
| Styling | Tailwind CSS 3.4 + shadcn-vue primitives; no Bootstrap |
| Build | Vite 5 |
| Payments | UddoktaPay (BDT) |
| SMS | BulkSMSBD |

---

## Money is never a float

Every monetary value in the database, in transit, and in calculations is an **integer
number of poisha** (1 BDT = 100 poisha). Fee rates are **basis points** (10% = 1000 bp).
Nothing is ever stored as `decimal` or multiplied as a float.

| Concept | Storage | Example |
|---|---|---|
| Prices, balances, fees | `unsignedBigInteger` poisha | ৳1,000 → `100000` |
| Fee rates | `unsignedInteger` basis points | 10% → `1000` |
| Display | `Money::format(int $poisha)` | → `৳1,000.00` |
| Arithmetic | `Money::percentOf($poisha, $bp)` | round-half-up, integer only |

`app/Support/Money.php` owns all formatting, so the browser never does arithmetic on
money — controllers ship pre-formatted strings to Vue.

Fee snapshots are **locked at order creation** by `FeeCalculator::forOrder()`. Changing
the platform fee in admin settings never re-prices an existing order.

---

## Front end

The app is a **server-driven SPA**. Controllers return `Inertia::render('Page/Name', [...])`
with an explicitly whitelisted prop payload; Vue renders it. There is no REST API for the
UI and no client-side router.

Blade is essentially gone: `resources/views/` contains only `app.blade.php` (the Inertia
root) and `errors/{403,404,419,429,500,503}.blade.php` — standalone documents, because an
Inertia page cannot render when the failure *is* the request. `grep -rn 'return view(' app/`
returns nothing. A test asserts that listing so Blade cannot creep back.

That state was reached over 47 incremental checkpoints, each one page: port the controller
to `Inertia::render`, rebuild the view in Vue, restyle Bootstrap → Tailwind, fix whatever
was genuinely broken in the old page, delete the Blade file, add feature tests. `git log`
reads as that history.

**Design system** — tokens and component classes live in `resources/css/app.css`
(`.btn-*`, `.input`, `.select`, `.card`, `.badge-*`, `.table`, `.stat-card`, `.money`):

- **Brand emerald** (`#10B981`) — primary UI, CTAs, links
- **Mint** — reserved for money amounts, earnings and verified badges
- **Amber** — promotions and pending states, sparingly
- **Sora** display / **Inter** body / **JetBrains Mono** for every ৳ amount

`npx vue-tsc --noEmit` is a hard gate — it must exit 0.

---

## Getting started

**Prerequisites** — PHP 8.2+ with `bcmath ctype curl dom fileinfo gd json mbstring openssl
pdo tokenizer xml zip`, Composer 2, Node.js 20+, MySQL 8 (or MariaDB 10.6+).

```bash
git clone https://github.com/julkarnayem/market.git
cd market

composer install
npm install

cp .env.example .env
php artisan key:generate
# Edit .env: DB_*, APP_URL, MAIL_*, and the gateway keys below.

php artisan migrate
php artisan db:seed          # permissions + roles, platform settings, categories, super admin
php artisan storage:link

npm run build
php artisan serve
```

The seeder creates **`admin@marketplace.test` / `password`** — change it immediately, or
create your own with `php artisan admin:create-super`.

The `private` filesystem disk used for verification documents is already defined in
`config/filesystems.php`; older setup notes that tell you to add it by hand are stale.

---

## Day-to-day development

```bash
composer dev   # serve + queue:listen + pail (logs) + vite, all in one terminal
```

Or run the pieces yourself:

```bash
npm run dev              # Vite dev server with HMR
php artisan serve
php artisan queue:listen # notifications, SMS and emails are queued
```

```bash
npm run typecheck        # vue-tsc --noEmit — must exit 0
npm run build            # production bundle
```

> **Careful with SMS.** `BULKSMSBD_ENABLED=true` sends **real, paid** messages. Keep it
> `false` locally. `phpunit.xml` pins it to `false` so the suite can never send.

---

## Testing

```bash
php artisan test                                  # full suite
php artisan test tests/Feature/InertiaMigrationTest.php
```

Tests run against SQLite `:memory:` (pinned in `phpunit.xml`), so no database service is
needed and no test can reach the live SMS or payment gateways. `tests/TestCase.php` also
stubs Vite via `withoutVite()`, so a fresh clone can run the suite before ever running
`npm run build` — `public/build/` is gitignored, and without the stub every test that
renders the Inertia root fails on the missing manifest.

**240 tests.** `InertiaMigrationTest` alone carries 196 of them — one or more per migrated
page, asserting the component name and the exact prop shape.

**13 failures are known and expected.** Twelve are `BadMethodCallException` from model
factories that were never wired up (`Wallet`, `Order`, `SupportTicket` import `HasFactory`
but never apply the trait; `Category` has neither trait nor factory); the thirteenth is a
`users`-table assertion in `AuthTest`. They predate the Inertia work. CI pins the exact set
in `.github/known-test-failures.txt`, so a **14th** failure — or one of these quietly
getting fixed — fails the build and asks you to update the list.

---

## Scheduled work

`routes/console.php`; needs `* * * * * php artisan schedule:run` in cron.

| Command | Frequency | Purpose |
|---|---|---|
| `offers:expire` | hourly | expire stale 8 h offers |
| `orders:auto-complete` | every 15 min | close orders past the buyer-protection window |
| `earnings:release` | every 15 min | release the 8 h earning lock |
| `promotions:expire` | every 5 min | expire listing promotions |
| `promotions:warn-expiring` | hourly | 24 h expiry warning |

A queue worker is required for notifications, SMS and email:

```bash
php artisan queue:work --queue=critical,notifications,sms,emails,default
```

---

## Configuration

Platform economics are **database-backed and editable in Admin → Settings**, with
`config/marketplace.php` as the fallback: seller fee (default 1000 bp = 10%), buyer fee
(default off), minimum withdrawal (৳50), withdrawal fee (৳5), buyer-protection window,
promotion prices per day. `SettingsService` reads them cached and always returns integers.

Environment keys beyond the Laravel defaults:

| Key | Notes |
|---|---|
| `UDDOKTAPAY_API_KEY` | from the UddoktaPay dashboard |
| `UDDOKTAPAY_BASE_URL` | `https://sandbox.uddoktapay.com/api` for tests, `https://uddoktapay.com/api` live |
| `BULKSMSBD_API_KEY`, `BULKSMSBD_SENDER_ID` | SMS credentials |
| `BULKSMSBD_ENABLED` | **`true` sends real paid SMS** |

Gateway callbacks to register with UddoktaPay: `/checkout/success`, `/checkout/cancel`,
`/checkout/webhook` (the webhook authenticates by API key, not session).

---

## Repository layout

```
app/
  Console/Commands/    6 scheduled commands
  Enums/               9 enums (UserStatus, AssetStatus, OrderStatus, …)
  Http/
    Controllers/       Auth/ Dashboard/ Admin/ + Marketplace, Checkout, Profile, Page
    Middleware/        EnsureUserIsActive, EnsureUserCanSell, EnsureAdmin,
                       HandleInertiaRequests (shared props)
  Models/              38 models
  Services/            19 services — OrderService, FeeCalculator, SettingsService,
                       WalletService, NotificationService, UddoktaPayService, AuditLogger, …
  Support/Money.php    poisha ↔ BDT, percentOf()

resources/
  css/app.css          design tokens + component classes
  js/
    Pages/             30 Inertia page components
    Layouts/           PublicLayout, DashboardLayout, AdminLayout
    Components/        shared UI
    types/index.d.ts   shared prop types, Ziggy globals
    app.ts  ssr.ts     client and SSR entries
  views/               app.blade.php + errors/ only

database/migrations/   47 migrations
database/seeders/      PermissionRole, Settings, Category, DatabaseSeeder
routes/web.php         175 named routes
routes/console.php     the schedule
tests/Feature/         InertiaMigrationTest + auth, security, financial, withdrawal suites
.github/workflows/     CI — type gate, build, and the test suite vs. the failure baseline
```

---

## Further documentation

| File | Contents |
|---|---|
| [`ADMIN_SETUP.md`](ADMIN_SETUP.md) | staff roles, gateway and SMS setup, withdrawal and fraud workflows |
| [`DEPLOYMENT.md`](DEPLOYMENT.md) | production install, Supervisor, cron, Nginx, rollback |
| [`PRODUCTION_CHECKLIST.md`](PRODUCTION_CHECKLIST.md) | pre-launch checklist |
| [`CHANGELOG.md`](CHANGELOG.md) | release history |
| [`FINAL_QA_REPORT.md`](FINAL_QA_REPORT.md) | QA sign-off notes |
| [`README_PART1.md`](README_PART1.md) | original build notes — predates the Inertia migration, so its UI and Blade-component sections are historical |

---

## Notes

- SSR is wired (`resources/js/ssr.ts`) but not enabled.
- `resources/js/app.js` and the Alpine.js dependencies are vestigial — leftovers from the
  Blade era, not used by any rendered page.
- No `LICENSE` file is present in this repository.
