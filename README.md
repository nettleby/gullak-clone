# 🐷 MeraGullak — Gullak-style Digital Gold & Silver App

> For AI agents: see `AGENTS.md` as the spec — this file is background info only.

A full-featured app inspired by the Gullak mobile-app concept for digital gold investment,
built in **core PHP (no frameworks, no Composer)** + **MySQL** + vanilla CSS/JS.

> ⚠️ **Educational project.** Not affiliated with Gullak. Do not operate a real
> investment service without licences, audits and legal advice. "Gullak" is a
> trademark of its respective owners — rename the app before any public use.

---

## ✨ Features

**User app** (mobile-first, Gullak-style amber UI with bottom tab bar, **Lucide icons via CDN** — no emojis anywhere in the UI)
- Register / login (bcrypt passwords, CSRF-protected forms, session security, password show/hide + strength meter)
- **Dashboard**: portfolio hero with P&L + privacy **hide-balance eye toggle**, active-SIP banner, quick actions, per-metal holdings, both metals' rates with 24h change badges, price trend chart (Chart.js), recent activity
- **Wallet**: Razorpay **test-mode** top-ups with server-side signature verification, month-to-date money-in/out stats
- **Buy gold & silver** by ₹ amount or grams — quick-amount chips, amount slider, live conversion, insufficient-balance hint
- **Sell** metal back to the wallet instantly — 25/50/75/100% shortcuts + per-sale P&L estimate
- **SIP auto-invest**: daily / weekly / monthly plans with segmented frequency picker, “start today” option, per-plan stat cards (instalments, invested, grams), pause / resume / cancel, **projection calculator** (illustrative), execution log
- **Portfolio page**: allocation doughnut chart, per-metal breakdown (avg buy price, invested, value, P&L), buy/sell shortcuts
- **Withdraw to bank**: save bank accounts, request withdrawals, settlement timeline explainer, admin approval flow
- **History**: filters (all / gold / silver / money-in / money-out / SIP) + search + date-grouped rows + pagination
- **Notifications page**: actionable alerts (paused SIPs, pending withdrawals) + activity feed, with bell badge in the top bar
- **Profile hub**: avatar card, quick stats, menu to everything
- **Settings**: edit personal details, notification preferences (email/SMS), bank-account management (add/remove), change password, delete-account flow (with safety guards)
- **Help & support, About, Terms, Privacy** — full content pages with FAQ accordion

**Admin panel** (`/admin/`, separate credentials & session)
- Set gold & silver **buy/sell rates** (spread validation, full price history + chart)
- Dashboard: users, wallet liability, metal held, deposits, active SIPs
- Users: search, detail view, **manual wallet credit/debit** (ledgered), reset user
  password, enable/disable accounts
- Withdrawals: approve / reject (reject auto-refunds the wallet)
- SIPs: all plans + recent runs across users
- Change admin password

**Engineering**
- One unified `transactions` ledger with signed deltas & running balances
- `SELECT … FOR UPDATE` row locking on wallet/holdings — no double-spend
- Idempotent payment verification (an order can credit a wallet exactly once)
- Zero SDK dependencies — Razorpay called via REST/cURL
- Auto-detected base URL: works in a sub-folder or vhost root

---

## 🧰 Requirements

- XAMPP / WAMP / LAMP with **PHP 8.0+** (needs `pdo_mysql` and `curl` — both are
  enabled by default in XAMPP) and **MySQL 5.7+ / MariaDB 10.4+**
- Internet for the CDNs (Google Fonts, **Lucide icons**, Chart.js, Razorpay Checkout) — pages
  degrade gracefully offline

> Icons: the UI uses [Lucide](https://lucide.dev) loaded from unpkg
> (`lucide@0.462.0` pinned) in `includes/footer.php` and `admin/includes/footer.php`.
> No emoji icons are used anywhere.

## 🚀 Setup (5 minutes)

1. Copy the `gullak-clone` folder into your XAMPP `htdocs` directory.
2. Start **Apache** and **MySQL** from the XAMPP control panel.
3. Import the database — either:
   - open **phpMyAdmin** → *Import* → choose `db/schema.sql`, or
   - CLI: `mysql -u root < db/schema.sql`

   > Upgrading from v1 of this project? Run `db/migrate-v2.sql` once
   > (adds the notification-preference columns used by the Settings page).
4. Check `config/config.php` — the XAMPP defaults (`root`, empty password,
   db `gullak`) usually work as-is.
5. Open:

   | URL | What |
   |---|---|
   | http://localhost/gullak-clone/ | User app (register a new account) |
   | http://localhost/gullak-clone/admin/ | Admin panel |

**Default admin login:** `admin` / `admin123` → change it immediately
(Admin → Password).

---

## 💳 Testing payments (Razorpay TEST mode)

Your test keys live in `config/config.php` (`RZP_KEY_ID` / `RZP_KEY_SECRET`).

| Method | Value |
|---|---|
| Test card | `4111 1111 1111 1111` · any future expiry · any CVV |
| Test UPI (success) | `success@razorpay` |
| Test UPI (failure) | `failure@razorpay` |

Money flow: *Add Money* → server creates an order via Razorpay REST API →
Checkout modal opens → on success the browser posts the result to
`api/verify-payment.php` → server verifies the **HMAC-SHA256 signature** and
credits the wallet exactly once.

> 🔑 **Rotate your test keys** from the Razorpay dashboard before sharing this
> project anywhere, since the keys are in the code. Use **live** keys only on a
> secure server (HTTPS + `DISPLAY_ENV=false`).

## 🏷️ Setting prices

Admin → **Prices**. Users buy at the *buy rate* and sell back at the *sell
rate*; the difference is your spread. Every change is written to
`price_history` and appears instantly on user dashboards & charts.

## 🔁 How SIPs run

- When a user opens any page, due instalments for **that user** execute
  automatically (poor-man's cron built into `includes/bootstrap.php`).
- For running instalments even when users are offline, add a real cron:

  **Linux:** `15 0 * * * php /path/to/gullak-clone/cron/sip-runner.php`
  **Windows:** Task Scheduler → daily →
  `C:\xampp\php\php.exe C:\xampp\htdocs\gullak-clone\cron\sip-runner.php`

## 📁 Project structure

```
gullak-clone/
├── config/config.php        ← DB creds, Razorpay keys, business rules
├── db/schema.sql            ← full MySQL schema + seed (admin & rates)
├── db/migrate-v2.sql        ← one-time upgrade for v1 databases
├── includes/
│   ├── bootstrap.php        ← session, requires, due-SIP trigger, BASE_URL
│   ├── db.php               ← PDO singleton
│   ├── auth.php             ← register/login, CSRF
│   ├── functions.php        ← ledger, buy/sell engines, SIP runner, Razorpay, lucide()
│   ├── header.php/footer.php← app shell (topbar + bell + bottom tab bar)
├── assets/                  ← style.css (Gullak theme), app.js, checkout.js
├── api/                     ← verify-payment.php, get-rates.php (JSON)
├── cron/sip-runner.php      ← CLI SIP processor for real cron
├── index.php buy.php sell.php wallet.php add-money.php
├── sip.php withdraw.php history.php profile.php
├── settings.php portfolio.php notifications.php   ← v2 pages
├── help.php about.php terms.php privacy.php       ← v2 pages
├── login.php register.php logout.php
└── admin/                   ← separate panel with its own bootstrap
```

## 🗄️ Data model highlights

- `wallets` / `holdings` — current balances (locked with `FOR UPDATE` on writes)
- `transactions` — **unified ledger**: every money/gram movement with signed
  deltas and after-snapshots (wallet + metal in one place)
- `sip_plans` / `sip_logs` — plans + execution audit trail
- `payments` — Razorpay order lifecycle (`created → paid/failed`), the status
  flip makes crediting idempotent
- `metal_prices` / `price_history` — current rates + full audit
- `admins` — separate from users

## 🔒 Before going anywhere near production

- [ ] Rotate Razorpay keys; use live keys only over HTTPS
- [ ] Set `DISPLAY_ENV = false` in `config/config.php`
- [ ] Change the default admin password
- [ ] Add rate limiting / login throttling (not included)
- [ ] Real KYC, email verification & password reset flows
- [ ] Actual payout integration for withdrawals (currently manual)
- [ ] Legal/compliance review — selling real gold requires SEBI/NHB-regulated
      structures in India

## 🧪 Known limitations

- Prices are admin-set (by design, per requirements) — no market feed
- Withdrawals are approved-and-pay-manually (no bank payout API)
- Password reset is admin-assisted (no email system)
