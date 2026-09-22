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

/* ---------- Razorpay (TEST MODE keys) ----------
 * Dashboard: https://dashboard.razorpay.com  →  Settings → API Keys
 * Test card:  4111 1111 1111 1111 | any future date | any CVV
 * Test UPI :  success@razorpay
 * NOTE: Rotate these keys before going anywhere near production,
 *       especially if you have shared them in chats/repos.
 */
define('RZP_KEY_ID', 'rzp_test_TWgt0P5dQMjmMF');
define('RZP_KEY_SECRET', 'VRRF03gIftB5pWOIbvdG1WDt');
define('RZP_ENABLED', true);    // set false to disable Add Money while offline

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
