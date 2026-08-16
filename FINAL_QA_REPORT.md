# Final QA Report — Parts 1A through 9

## Financial Safety

All monetary operations use **integer poisha** (100 poisha = 1 BDT). No floats anywhere in financial code. Verified:

| Check | Status |
|-------|--------|
| `Money::percentOf()` — integer basis-point math | ✓ No floats |
| Fee snapshot locked at order creation | ✓ Admin changes don't affect past orders |
| Wallet `lockForUpdate()` before every debit | ✓ No concurrent overdraft |
| Withdrawal reserves gross immediately | ✓ `debitAvailable()` called in same transaction as creation |
| Earning release idempotent | ✓ `earning_released` boolean flag + re-read with lock |
| Dispute resolution idempotent | ✓ `isResolvable()` checked inside locked transaction |
| Promotion purchase idempotent | ✓ Active promotion check with `lockForUpdate()` |
| No free threshold for seller fee | ✓ 10% applies to all prices; verified in FeeCalculatorTest |

## Security

| Check | Status |
|-------|--------|
| XSS — Blade auto-escapes all output | ✓ `{!! !!}` audit complete; only hardcoded strings remain |
| SQL injection — parameterized queries | ✓ All `whereRaw` uses `?` bindings |
| CSRF — all POST routes protected | ✓ Laravel default; webhook excluded correctly |
| IDOR — ownership verified on all routes | ✓ Policies + DB relationship checks |
| Mass assignment — `$fillable` on all models | ✓ No `$guarded = []` |
| Rate limiting — login/register/reset/API | ✓ Named limiters in bootstrap/app.php |
| Security headers — all web responses | ✓ `SetSecurityHeaders` middleware |
| Private files — not web-accessible | ✓ `private` disk; authorized routes only |
| NID — encrypted at rest | ✓ `nid_encrypted` + `selfie_encrypted` |
| Privilege escalation — role assignment | ✓ Only super_admin can assign super_admin |
| Last super_admin — protected | ✓ Count check before demote/suspend |
| Admin panel — noindex | ✓ `<meta name="robots" content="noindex, nofollow">` |
| Dashboard — noindex | ✓ Same |

## Business Rules

| Rule | Enforced |
|------|----------|
| Seller fee 10% on ALL prices | ✓ FeeCalculator; no threshold |
| Buyer fee OFF by default | ✓ SettingsService + FeeCalculator |
| Listing fee: free | ✓ No charge on listing creation |
| Offer validity: 8 hours | ✓ `expires_at = now() + 8h` at creation |
| Buyer protection: 72 hours | ✓ `auto_complete_at` set at delivery |
| Earning lock: 8 hours | ✓ `seller_earning_available_at = completed_at + 8h` |
| Min withdrawal: ৳50 | ✓ WithdrawalService + validation |
| Withdrawal fee: ৳5 | ✓ Applied in WithdrawalService |
| Verification required to sell | ✓ `EnsureUserCanSell` middleware |
| Verification not required to buy | ✓ No check on checkout |
| No referral system | ✓ Not implemented |

## Known Deferred Items (not defects)

| Item | Reason |
|------|--------|
| Real-time WebSocket | Requires Reverb/Pusher setup; polling fallback active |
| BulkSMSBD test | Requires live Bangladesh number + live credentials |
| Automatic MFS payout | Manual process; admin marks complete |
| UddoktaPay gateway refund | Not standard in UddoktaPay; admin handles manually |
| 2FA for staff | Architecture ready; not implemented |
| CSP tightening | Intentionally permissive; frontend audit required first |
| CAPTCHA | Not implemented; rate limits are current mitigation |
| Image metadata stripping | Requires intervention library |
