<?php
require_once __DIR__ . '/includes/bootstrap.php';
$user = require_login();

$active_tab = 'profile';
$page_title  = 'Terms & conditions';
require __DIR__ . '/includes/header.php';
?>
<div class="page-title">Terms &amp; conditions</div>
<p class="page-sub">Last updated: <?= e(date('d M Y')) ?> · educational project.</p>

<div class="card">
  <div class="card-title">1. About this service</div>
  <p class="copy-note">
    <?= e(APP_NAME) ?> is an educational software project that simulates a digital gold and silver savings
    platform. It is provided "as is" for learning and demonstration purposes, without any warranty of
    merchantability or fitness for a particular purpose. It is not a regulated financial service, and operating
    one with real customers requires licenses and compliance obligations that this project does not fulfil.
  </p>
</div>

<div class="card">
  <div class="card-title">2. Your account</div>
  <p class="copy-note">
    You are responsible for keeping your password confidential and for all activity that happens under your
    account. Notify support immediately if you suspect unauthorised access. We may suspend accounts that show
    suspicious activity, violate these terms, or attempt to manipulate rates or the payment flow. You may delete
    your account at any time from Settings, provided your wallet is empty and no withdrawals are pending.
  </p>
</div>

<div class="card">
  <div class="card-title">3. Buying, selling &amp; rates</div>
  <p class="copy-note">
    All buy and sell rates are set by the platform operator and are displayed before every transaction. When you
    buy, you pay the displayed buy rate per gram; when you sell, you receive the sell rate per gram. Metal is
    recorded digitally in grams up to four decimal places and is not physically delivered. Sales are credited to
    your wallet instantly, and valuation always uses the current sell rate.
  </p>
</div>

<div class="card">
  <div class="card-title">4. Wallet, deposits &amp; withdrawals</div>
  <p class="copy-note">
    Wallet top-ups are processed through Razorpay and are credited only after a cryptographically verified
    payment confirmation. Withdrawal requests lock the amount immediately, are reviewed by the platform, and
    typically settle to your saved bank account within 1-2 working days of approval. Rejected requests are
    refunded to your wallet in full. The platform may impose minimums and per-transaction limits.
  </p>
</div>

<div class="card">
  <div class="card-title">5. SIP plans</div>
  <p class="copy-note">
    SIP instalments are executed automatically from your wallet balance on the schedule you choose. It is your
    responsibility to keep the wallet funded; a plan that fails repeatedly pauses itself and can be resumed
    anytime. Creating, pausing or cancelling a SIP is always free, and metal already bought remains yours.
  </p>
</div>

<div class="card">
  <div class="card-title">6. Liability</div>
  <p class="copy-note">
    To the maximum extent permitted by law, the project authors are not liable for any losses arising from the
    use of this software, including rate changes, payment failures, or data loss. Use sensible amounts while
    testing, keep your credentials private, and never share API keys publicly.
  </p>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
