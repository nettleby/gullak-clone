<?php
require_once __DIR__ . '/includes/bootstrap.php';
$user = current_user();   // public page — login only needed for actions

$active_tab = 'profile';
$page_title  = 'About';
require __DIR__ . '/includes/header.php';
?>
<div class="page-title">About <?= e(APP_NAME) ?></div>
<p class="page-sub"><?= e(APP_TAGLINE) ?> · version 2.0</p>

<div class="profile-head" style="padding:18px">
  <div style="display:flex;align-items:center;gap:14px">
    <span class="brand-mark" style="width:52px;height:52px;border-radius:16px"><?= lucide('piggy-bank') ?></span>
    <div>
      <div style="font-size:18px;font-weight:900"><?= e(APP_NAME) ?></div>
      <div style="font-size:12px;font-weight:700;opacity:.95">A Gullak-style savings app built with core PHP</div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-title">What this is</div>
  <p class="copy-note">
    <?= e(APP_NAME) ?> is a full-featured digital gold &amp; silver savings platform: add money to a wallet,
    buy and sell 24K gold and 999 silver by rupee or gram, automate savings with daily, weekly or monthly SIPs,
    and withdraw proceeds to your bank account. Every rupee and every gram is tracked in a permanent, auditable
    ledger, and a separate admin panel manages rates, users and payouts.
  </p>
</div>

<div class="sec-head"><?= lucide('sparkles') ?><h2>Highlights</h2></div>
<div class="card">
  <div class="menu" style="box-shadow:none;margin-bottom:0">
    <div class="menu-item">
      <span class="mi-icon"><?= lucide('gem') ?></span>
      <span class="mi-body"><span class="mi-title">Gold &amp; silver</span><span class="mi-sub">24K / 999 purity, four-decimal gram precision</span></span>
    </div>
    <div class="menu-item">
      <span class="mi-icon tone-green"><?= lucide('repeat') ?></span>
      <span class="mi-body"><span class="mi-title">SIPs</span><span class="mi-sub">Daily, weekly &amp; monthly auto-invest plans</span></span>
    </div>
    <div class="menu-item">
      <span class="mi-icon tone-blue"><?= lucide('credit-card') ?></span>
      <span class="mi-body"><span class="mi-title">ICICI Bank payments</span><span class="mi-sub">Cards, UPI &amp; net-banking (test mode)</span></span>
    </div>
    <div class="menu-item">
      <span class="mi-icon tone-slate"><?= lucide('shield-check') ?></span>
      <span class="mi-body"><span class="mi-title">Auditable ledger</span><span class="mi-sub">Every movement logged with running balances</span></span>
    </div>
    <div class="menu-item">
      <span class="mi-icon"><?= lucide('chart-pie') ?></span>
      <span class="mi-body"><span class="mi-title">Portfolio analytics</span><span class="mi-sub">Allocation, avg price and P&amp;L tracking</span></span>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-title">Important disclaimers</div>
  <p class="copy-note">
    This application is an educational clone for learning purposes and is not affiliated with or endorsed by
    Gullak or any regulated provider. Digital gold involves market risk — rates can move against you, and the
    spread between buy and sell rates means short-term trading usually recovers slightly less than invested.
    Nothing here is investment advice. If you deploy this project, replace the test payment keys, change the
    default admin password, and comply with the regulations that apply to you before allowing any real money.
  </p>
</div>

<div class="menu">
  <a class="menu-item" href="<?= url('terms.php') ?>">
    <span class="mi-icon tone-slate"><?= lucide('file-text') ?></span>
    <span class="mi-body"><span class="mi-title">Terms &amp; conditions</span></span>
    <span class="mi-end"><?= lucide('chevron-right') ?></span>
  </a>
  <a class="menu-item" href="<?= url('privacy.php') ?>">
    <span class="mi-icon tone-slate"><?= lucide('shield-check') ?></span>
    <span class="mi-body"><span class="mi-title">Privacy policy</span></span>
    <span class="mi-end"><?= lucide('chevron-right') ?></span>
  </a>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
