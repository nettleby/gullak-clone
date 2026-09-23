<?php
/**
 * MeraGullak — Gullak-style Digital Gold & Silver app (core PHP)
 * ------------------------------------------------------------------
 * Central configuration. Edit the values below to match your setup.
 */

/* ---------- Database (XAMPP defaults) ---------- */
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'gullak');
define('DB_USER', 'root');
define('DB_PASS', '');          // XAMPP default MySQL root password is empty

/* ---------- App ---------- */
define('APP_NAME', 'MeraGullak');
define('APP_TAGLINE', 'Digital Gold & Silver');

/* ---------- ICICI Bank PG (UAT / TEST MODE) ----------
 * Flow: server calls initiateSale (JSON + HMAC-SHA256) → user is
 * redirected to the ICICI hosted page → ICICI redirects back to
 * api/icici-callback.php → server verifies hash + status and credits.
 * Test card:  4761 3400 0000 0035 | 12/26 | 123, OTP 123456, Name test
 * Test NB  :  CC Avenue Test Bank (login / OTP 123456)
 * Test UPI :  test@ybl
 * NOTE: These UAT credentials were shared in chat. Rotate them in the
 *       ICICI dashboard before any share/prod use. returnURL is derived
 *       automatically (works on XAMPP localhost for browser testing);
 *       override ICICI_RETURN_URL only if UAT demands a public HTTPS URL.
 */
define('ICICI_MERCHANT_ID', '100000000007164');
define('ICICI_AGGREGATOR_ID', 'A100000000007164');
define('ICICI_SECRET_KEY', 'db06cca0-838b-4e01-8b20-6ac446ffb6bd');
define('ICICI_INITIATE_URL', 'https://pgpayuat.icici.bank.in/tsp/pg/api/v2/initiateSale');
define('ICICI_COMMAND_URL', 'https://pgpayuat.icici.bank.in/tsp/pg/api/command?reqType=JSON');
define('ICICI_RETURN_URL', '');       // '' = auto-derive absolute api/icici-callback.php URL
define('ICICI_CURRENCY', '356');      // INR numeric code
define('ICICI_PAYTYPE', '0');         // 0 = hosted checkout (ICICI shows payment page)
define('ICICI_ENABLED', true);        // set false to disable Add Money while offline

/* ---------- Market rates (metals.dev, IBJA) ----------
 * RATE_SOURCE 'auto': gold/silver buy/sell are synced from metals.dev twice
 *   daily (RATE_SYNC_TIMES IST) with percent spreads over the market mid.
 *   Free plan = 100 calls/mo: 2 auto/day ≈ 60/mo, hard-capped below.
 * RATE_SOURCE 'manual': admin sets rates by hand on admin/prices.php.
 * NOTE: This UAT key was shared in chat — rotate it on the metals.dev
 *   dashboard before sharing this project or going live.
 */
define('METALS_API_KEY', 'KB0ZJ4ECLQTQOW6QMV9T9196QMV9T');
define('RATE_SOURCE', 'auto');       // 'auto' = metals.dev feed, 'manual' = admin-set
define('GOLD_BUY_SPREAD_PCT', 1.5);  // buy  = mid × (1 + pct/100)
define('GOLD_SELL_SPREAD_PCT', 1.5); // sell = mid × (1 − pct/100)
define('SILVER_BUY_SPREAD_PCT', 4.0);
define('SILVER_SELL_SPREAD_PCT', 4.0);
define('RATE_SYNC_TIMES', ['10:00', '16:00']); // IST slots, earliest-first
define('RATE_MAX_CALLS_PER_DAY', 3); // hard quota guard (auto + manual Sync-now)

/* ---------- Business rules (₹ / grams) ---------- */
define('MIN_DEPOSIT', 100);        // min wallet top-up per order
define('MAX_DEPOSIT', 100000);     // max wallet top-up per order
define('MIN_BUY_INR', 10);        // min purchase amount
define('MIN_SELL_GRAMS', 0.01);   // min sale quantity (grams)
define('MIN_WITHDRAW', 100);      // min withdrawal request
define('SIP_MIN_INR', 10);        // min SIP instalment
define('SIP_MAX_INR', 100000);    // max SIP instalment
define('SIP_MAX_FAILS', 3);       // auto-pause a SIP after this many failed runs
define('SIP_PROJ_RATE_PCT', 10);  // % p.a. used by the ILLUSTRATIVE SIP projection (does NOT affect real returns)

/* ---------- Misc ---------- */
define('DISPLAY_ENV', true);      // true = show PHP errors (dev only!)
date_default_timezone_set('Asia/Kolkata');
