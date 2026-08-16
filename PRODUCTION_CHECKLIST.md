# Production Launch Checklist

## ☐ Environment & Configuration

- [ ] `APP_DEBUG=false` in `.env`
- [ ] `APP_ENV=production` in `.env`
- [ ] `APP_KEY` is set (run `php artisan key:generate` once)
- [ ] `APP_URL` matches your actual domain with `https://`
- [ ] `LOG_LEVEL=warning` in `.env`
- [ ] `SESSION_SECURE_COOKIE=true` (requires HTTPS)
- [ ] `SESSION_SAME_SITE=strict`
- [ ] Strong database password in `.env`
- [ ] `composer audit` shows no critical vulnerabilities
- [ ] `npm audit --production` shows no critical vulnerabilities

## ☐ Database

- [ ] `php artisan migrate --force` run on production DB
- [ ] `php artisan db:seed --force` run (permissions + roles)
- [ ] Initial super admin created (`php artisan admin:create-super`)
- [ ] Default admin password changed
- [ ] Database backups configured and tested
- [ ] `DB_DATABASE` is not a shared/staging database

## ☐ Storage & Files

- [ ] Private disk configured in `config/filesystems.php`
- [ ] `storage/app/private` not web-accessible (verify with curl)
- [ ] `php artisan storage:link` run
- [ ] Upload directory writable by web server user
- [ ] Backups include `storage/app/` directory

## ☐ Payment Gateway

- [ ] UddoktaPay LIVE URL set (`UDDOKTAPAY_BASE_URL=https://uddoktapay.com/api`)
- [ ] Live API key set (`UDDOKTAPAY_API_KEY=`)
- [ ] Webhook URL verified reachable from internet (`/checkout/webhook`)
- [ ] Success/cancel URLs configured in UddoktaPay dashboard
- [ ] Test payment made and verified end-to-end
- [ ] Idempotency tested: webhook called twice = no double order

## ☐ Queue & Scheduler

- [ ] Supervisor running queue workers (`queue:work`)
- [ ] `--queue=critical,notifications,sms,emails,default` order specified
- [ ] Cron entry for `* * * * * php artisan schedule:run`
- [ ] `php artisan queue:monitor default` shows workers healthy
- [ ] Failed jobs table exists (`php artisan queue:failed-table && php artisan migrate`)
- [ ] Failed job notifications configured (email or Slack)

## ☐ SMS (BulkSMSBD)

- [ ] `BULKSMSBD_ENABLED=true` if SMS required
- [ ] Live API key and sender ID set
- [ ] Test SMS sent and received on a real Bangladesh number
- [ ] SMS queue worker running (`--queue=sms`)

## ☐ Security

- [ ] `php artisan config:cache` run (prevents `.env` leaks)
- [ ] `php artisan route:cache` run
- [ ] `php artisan view:cache` run
- [ ] Nginx config blocks `/app/private` path
- [ ] HTTPS enforced (HTTP → HTTPS redirect in Nginx)
- [ ] HSTS header visible in browser DevTools
- [ ] `X-Content-Type-Options: nosniff` visible in DevTools
- [ ] `X-Frame-Options: SAMEORIGIN` visible in DevTools
- [ ] Admin panel accessible only over HTTPS
- [ ] `/admin` not indexed (verify via Google Search Console)
- [ ] `/robots.txt` returns correct content
- [ ] `/sitemap.xml` returns valid XML

## ☐ Verification & KYC

- [ ] Verification document storage confirmed private (not public)
- [ ] Admin can view documents via `/admin/verification/{id}/document/{type}`
- [ ] NID data encrypted at rest (verify `seller_verifications.nid_encrypted` column exists)

## ☐ Financial Rules Verified

- [ ] Seller fee is 10% on all prices (no free threshold)
- [ ] Buyer fee is OFF by default
- [ ] Min withdrawal is ৳50, fee is ৳5
- [ ] Offer validity is 8 hours
- [ ] 72-hour buyer protection auto-complete working
- [ ] 8-hour earning lock after order completion working
- [ ] Wallet operations tested: credit, debit, release

## ☐ Post-launch Monitoring

- [ ] Error monitoring configured (Sentry, Flare, or log watching)
- [ ] Health check endpoint `/up` monitored externally
- [ ] Database disk space monitoring
- [ ] Queue depth monitoring (alert if queue grows unexpectedly)
- [ ] Uptime monitoring (UptimeRobot or equivalent)
