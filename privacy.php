<?php
require_once __DIR__ . '/includes/bootstrap.php';
$user = require_login();

$active_tab = 'profile';
$page_title  = 'Privacy policy';
require __DIR__ . '/includes/header.php';
?>
<div class="page-title">Privacy policy</div>
<p class="page-sub">What we store and why · last updated <?= e(date('d M Y')) ?>.</p>

<div class="card">
  <div class="card-title">Data we collect</div>
  <p class="copy-note">
    To run your account we store the name, email address and mobile number you register with, a bcrypt hash of
    your password (never the password itself), your wallet balance and metal holdings, the bank accounts you
    choose to save, and a complete ledger of every transaction. Payment processing is handled by ICICI Bank; we
    keep only the order identifier and amounts, never your card or UPI credentials.
  </p>
</div>

<div class="card">
  <div class="card-title">How it is used</div>
  <p class="copy-note">
    Your details are used to operate the service: authenticating logins, executing buys, sells and SIPs,
    settling withdrawals to the right bank account, and showing you an accurate history. If you enable email
    or SMS updates in Settings, those preferences control transactional notifications only — there are no
    marketing lists in this project.
  </p>
</div>

<div class="card">
  <div class="card-title">Cookies &amp; sessions</div>
  <p class="copy-note">
    The app uses a single server-side session cookie to keep you logged in. It is marked HttpOnly and
    SameSite=Lax for security. We do not use analytics, advertising or third-party tracking cookies. Fonts,
    Chart.js and Lucide icons are loaded from CDNs, so those providers can observe
    standard request metadata such as your IP address.
  </p>
</div>

<div class="card">
  <div class="card-title">Sharing</div>
  <p class="copy-note">
    Data is never sold or shared with advertisers. The only external processor is ICICI Bank for payments, and
    your bank details are used solely to pay out withdrawals you request. Within the platform, the
    administrator can view accounts for support, audits and payout processing.
  </p>
</div>

<div class="card">
  <div class="card-title">Your choices</div>
  <p class="copy-note">
    You can correct your name and phone number in Settings, change your notification preferences at any time,
    and delete your account whenever your wallet is empty and no withdrawals are pending. Account deletion
    removes your profile, holdings, saved banks and settings; transaction records tied to payments are kept for
    accounting integrity until the database itself is removed.
  </p>
</div>

<div class="card">
  <div class="card-title">Security</div>
  <p class="copy-note">
    Passwords are hashed with bcrypt, payment callbacks are verified with HMAC-SHA256 signatures, wallet
    operations run inside database transactions with row locking, and all forms are protected against
    cross-site request forgery. Still, treat this as a learning project: rotate any keys you use and keep
    balances modest while testing.
  </p>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
