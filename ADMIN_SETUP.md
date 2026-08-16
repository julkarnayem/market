# Admin Setup Guide

## Initial Access

After seeding, log in with the default admin:
- **Email:** admin@marketplace.test
- **Password:** password (change immediately)

Or create a super admin:
```bash
php artisan admin:create-super
```

---

## Role Overview

| Role | Can do |
|------|--------|
| **super_admin** | Everything — also the only role that can create other super admins |
| **admin** | All marketplace operations, no financial ledger edits |
| **moderator** | Listing/verification review only — no financial access |
| **support** | Customer support, tickets, disputes — no financial access |
| **finance** | Payments, withdrawals, refunds, reports — no moderation |

---

## Staff Management

1. Go to **Admin → Staff → Add Staff**
2. Enter name, email, password (min 10 chars), and assign a role
3. The new staff member can log in at `/login`

**Important:** Only super_admin accounts can:
- Assign the super_admin role to others
- Suspend a super_admin account
- The last super_admin cannot be demoted or suspended

---

## Setting Up Payment (UddoktaPay)

1. Register at https://uddoktapay.com and get your API key
2. Add to `.env`:
   ```
   UDDOKTAPAY_API_KEY=your_api_key
   UDDOKTAPAY_BASE_URL=https://sandbox.uddoktapay.com/api   # (test)
   UDDOKTAPAY_BASE_URL=https://uddoktapay.com/api           # (live)
   ```
3. Configure callback URLs in the UddoktaPay dashboard:
   - Redirect URL: `https://yourdomain.com/checkout/success`
   - Cancel URL:   `https://yourdomain.com/checkout/cancel`
   - Webhook URL:  `https://yourdomain.com/checkout/webhook`
4. Verify the webhook endpoint is reachable (no auth required — it uses API key verification)

---

## Setting Up SMS (BulkSMSBD)

1. Register at https://bulksmsbd.net and get API credentials
2. Add to `.env`:
   ```
   BULKSMSBD_API_KEY=your_api_key
   BULKSMSBD_SENDER_ID=YourSenderID
   BULKSMSBD_ENABLED=true
   ```
3. Ensure the queue worker is running (`php artisan queue:work --queue=sms`)
4. Test with a real Bangladesh number

---

## Platform Settings

Go to **Admin → Settings** to configure:
- Seller fee basis points (default: 1000 = 10%)
- Buyer fee (default: disabled)
- Minimum withdrawal amount (default: ৳50)
- Withdrawal fee (default: ৳5)
- Promotion prices per day

**Important:** Fee changes do NOT affect existing orders. The fee snapshot is locked at order creation time.

---

## Withdrawal Workflow

1. User requests withdrawal → status: `pending`
2. Admin reviews in **Admin → Withdrawals** → click **Approve**
3. Admin makes the MFS transfer manually (bKash/Nagad/Rocket)
4. Admin clicks **Mark paid** and enters the MFS transaction reference

---

## Fraud Review

High-risk users appear in **Admin → Fraud Review**.

- Score ≥ 30: Review queue
- Score ≥ 70: Escalated (urgent)
- **Never auto-ban** — always review before taking action
- Actions: **Clear** (false positive) or **Restrict** (restrict account)
