# MeraGullak — Agent Source of Truth

> **Take this file seriously. It is the spec for AI agents.**
> `README.md` is background / extra info only — never use it as spec.
> If code vs `README.md` conflicts, trust code + ask the user.

## 1. What this is

* **App name: MeraGullak (keep this name).** Digital gold & silver buy/sell app,
  inspired by the Gullak mobile-app concept. No affiliation.
* **Do NOT reference `https://gullak.app`** — that link is dead and was removed
  from `README.md`. Never re-add it.
* Educational project. Not production-ready for real money without licences,
  audits, KYC, and legal review.

## 2. Stack + hard constraints (preserve these)

* Core PHP (no framework, no Composer) + MySQL/MariaDB (InnoDB, utf8mb4) +
  vanilla CSS/JS. PHP 8.0+ with `pdo_mysql` + `curl` (XAMPP defaults).
* **Dev:** XAMPP localhost (`http://localhost/gullak-clone/`).
  **Target:** shared hosting — no Composer, no guaranteed cron/shell, must run
  in sub-folder or domain root.
* `includes/bootstrap.php` auto-detects `BASE_URL` (strips `/admin|/api|/cron`).
  Keep this working.
* Money math: scaled-integer half-up in `includes/functions.php`
  (`dec_to_scaled`, `exact_buy_grams`, `exact_sell_amount`) mirrored in
  `assets/js/app.js` (`mgCalc` via `BigInt`). Preview must equal server.
* Concurrency: `SELECT ... FOR UPDATE` on `wallets`/`holdings`, own-txn or
  join-caller-txn in `execute_buy` / `execute_sell`. Unified `transactions`
  ledger with signed deltas + after-snapshots.
* Payments: ICICI Bank PG hosted checkout (`payType=0`) via cURL JSON only
  (no SDK). `icici_initiate_sale()` → browser redirect to `redirectURI` +
  `tranCtx` → `api/icici-callback.php` verifies + `icici_status_check()` as
  final truth, idempotent credit via
  `UPDATE payments ... WHERE status='created'` + `rowCount` gate
  (`icici_credit_wallet()`). Manual Verify button covers missed callbacks.
  Status queries hit `.../command?reqType=JSON` with the universal
  sorted-params hash (`icici_sorted_hash()`); official doc host
  `pgpayuat.icicibank.com` is fallback (keep `bank.in` primary — proven live).
  Bank support: `msintegration@icici.bank.in`. Official test set: card
  `4761 3400 0000 0035 | 12/26 | 123`, OTP `123456`, NB `CC Avenue Test Bank`,
  UPI `test@ybl`.
  Bank return is a cross-site POST (no session cookie): callback defines
  `SKIP_SESSION` when the cookie is absent so PHP never emits a fresh
  `Set-Cookie` that would clobber the user's real session; sessionless
  returns render an inline result page, never a login redirect.
* Sessions: user session (httponly, Lax) + separate `MGAADM` admin session.
  Separate logins (`login.php` user, `admin/login.php` admin, both `?next=`
  aware) on isolated sessions so both can coexist.
  CSRF on all POSTs (`csrf_field` / `admin_csrf_field`, `419` on fail).
* Frontend: server-rendered PHP, Lucide via `unpkg lucide@0.462.0` pinned in
  `includes/footer.php` + `admin/includes/footer.php`, Chart.js CDN with offline
  fallback. Alerts via SweetAlert2 pinned `jsdelivr sweetalert2@11.14.5`
  (both footers) with app theme in `assets/css/style.css` (`.swal-mg-*`,
  `buttonsStyling:false`); `assets/js/app.js` + `admin/includes/footer.php`
  bridge `data-confirm` (forms/buttons/links) and upgrade `.flash` divs
  (toast success / modal error). No Swal = native confirm + visible flashes
  (offline fallback). No emojis in UI.
* SIP: poor-man's cron in `includes/bootstrap.php` (`run_due_sips` on every
  logged-in page load) + real cron `cron/sip-runner.php` (CLI only).
* Rates: `RATE_SOURCE manual` currently (admin-set prices). `auto` stack is
  STANDBY (metals.dev IBJA via `metals_sync_rates()`: 1 call/sync, 10:00+16:00
  IST slots, 10h gap, hard cap `RATE_MAX_CALLS_PER_DAY` from `price_history`;
  hook in `bootstrap.php` + `cron/rates-sync.php` + admin Sync-now; spreads
  `GOLD/SILVER_BUY/SELL_SPREAD_PCT` over mid, state via
  `updated_by='metals.dev'`). Flip one constant to re-enable pending client
  approval.

## 3. Verified file map (from code, not README)

* User: `index.php` (dashboard), `buy.php`, `sell.php`, `wallet.php`,
  `add-money.php`, `sip.php`, `portfolio.php`, `withdraw.php`, `history.php`,
  `notifications.php`, `profile.php`, `settings.php`, `help.php`, `about.php`,
  `terms.php`, `privacy.php`, `login.php` (user login, `?next=` aware),
  `register.php`, `logout.php`
* Shared: `config/config.php`, `includes/bootstrap.php`, `includes/db.php`,
  `includes/auth.php`, `includes/functions.php`, `includes/header.php`,
  `includes/footer.php`, `assets/css/style.css` (user app + mobile admin shell,
  neutral theme), `assets/js/app.js`
* API: `api/icici-callback.php`, `api/get-rates.php`
* Cron: `cron/sip-runner.php`, `cron/rates-sync.php`
* DB: `db/schema.sql` (fresh-install truth) + `db/migrate-v2.sql`
  (DEPRECATED legacy v1→v2 only, fails on fresh DBs) + new timestamped
  `db/YYYYMMDD-HHMM-*.sql` migrations (see §8)
* Admin (mobile app shell: dark topbar + bottom tabbar + More screen, Cards/Table
  toggle per list via `?view=`, choice remembered in localStorage):
  `admin/index.php`, `admin/prices.php`, `admin/users.php`,
  `admin/user-view.php`, `admin/withdrawals.php`, `admin/sips.php`,
  `admin/more.php`,
  `admin/change-password.php`, `admin/login.php`,
  `admin/logout.php`,
  `admin/includes/bootstrap.php`, `admin/includes/header.php`,
  `admin/includes/footer.php`

## 4. Business rules (from `config/config.php` — trust code over README)

* `MIN_DEPOSIT 100`, `MAX_DEPOSIT 100000`
* `MIN_BUY_INR 10`, single-buy hard cap ₹50,00,000 (`execute_buy`)
* `MIN_SELL_GRAMS 0.01`
* `MIN_WITHDRAW 100`
* `SIP_MIN_INR 10`, `SIP_MAX_INR 100000`, `SIP_MAX_FAILS 3`,
  `SIP_PROJ_RATE_PCT 10` (illustrative only, does not affect returns)
* Test ICICI UAT credentials committed in config — dev only, rotate before sharing/live.
  `DISPLAY_ENV=true` dev only (leaks traces). `DB root/''/gullak` XAMPP default.
  Default admin `admin / admin123` — change immediately. Admin passwords are
  stored READABLE (`admins.password_plain`, owner decision — see
  `db/20260923-1530-plaintext_admin_passwords.sql`); user passwords stay bcrypt.

## 5. DB truth (from `db/schema.sql`)

* `users`, `wallets (PK user_id)`, `holdings (PK user,metal grams4/invested)`,
  `metal_prices (PK metal buy/sell)`, `price_history`,
  `transactions (ledger: deposit|buy|sell|sip_buy|withdraw_request|withdraw_refund|withdraw_paid|admin_credit|admin_debit)`,
  `sip_plans (active|paused|cancelled, next_run, failed_attempts)`,
  `sip_logs (success|failed)`, `bank_accounts`, `withdrawals (pending|approved|rejected)`,
  `payments (order_id UNIQUE, created|paid|failed)`, `admins`,
  `settings (admin-editable spreads, config-constant fallback)`.
* Seeds: admin `admin123` plaintext + `gold 11250/10980`, `silver 138/128` + price_history rows.

## 6. README policy

* `README.md` = human background only. Useful for feature overview and setup flow,
  but may drift (proven by dead `gullak.app` link).
* Never implement from README claims without checking the files above.
* When in doubt, ask the user instead of believing README blindly.

## 7. Workflow

* One improvement at a time from the user's list. Plan first, then build.
* Verify on XAMPP PHP 8 + MySQL. Keep shared-hosting safe (no new extensions,
  no Composer, no hard-coded paths).
* Git split: agent may run READ-ONLY git (`status`, `diff`, `log`) for
  inspection/verification only. All mutating git (add, commit, push, pull,
  merge, reset, branches) is the user's job — never run those, never commit
  secrets, never force-push, never skip hooks unless asked.

## 8. DB migrations (user runs manually in phpMyAdmin)

* `db/schema.sql` = fresh-install truth only. Never assume it is already applied.
* `db/migrate-v2.sql` = DEPRECATED, legacy v1→v2 only. Never use for new work.
* All new DB changes ship as `db/YYYYMMDD-HHMM-added_x_to_y.sql`
  (e.g. `db/20260923-1030-added_kyc_status_to_users.sql`), one file per change,
  run in name order via phpMyAdmin Import with `gullak` DB selected.
* Every migration file must be **re-runnable** (safe to import twice): guard
  each ALTER/CREATE with an `INFORMATION_SCHEMA` check + `PREPARE/EXECUTE`
  pattern that prints `already applied: ...` instead of erroring. Start every
  file with `USE gullak;`.
* When a migration is added, also update `db/schema.sql` with the matching
  definition so fresh installs never need the migration chain.
* Never auto-run migrations from PHP. Agent creates the file, user executes it.
