<?php
require_once __DIR__ . '/includes/bootstrap.php';
$user = current_user();

$pdo = db();
$uid = $user ? (int) $user['id'] : 0;

$hold    = $uid ? get_holdings($uid) : ['gold' => ['grams' => 0, 'invested' => 0], 'silver' => ['grams' => 0, 'invested' => 0]];
$rates   = get_rates();
$balance = $uid ? wallet_balance($uid) : 0;

$gVal     = $hold['gold']['grams'] * ($rates['gold']['sell'] ?? 0);
$sVal     = $hold['silver']['grams'] * ($rates['silver']['sell'] ?? 0);
$portVal  = $gVal + $sVal;
$invested = $hold['gold']['invested'] + $hold['silver']['invested'];
$pl       = $portVal - $invested;

/* lifetime stats */
$st = $pdo->prepare('SELECT COUNT(*) FROM transactions WHERE user_id = ?');
$st->execute([$uid]);
$txnCount = (int) $st->fetchColumn();

$st = $pdo->prepare('SELECT COUNT(*) FROM sip_plans WHERE user_id = ? AND status = "active"');
$st->execute([$uid]);
$sipCount = (int) $st->fetchColumn();

$st = $pdo->prepare('SELECT COUNT(*) FROM bank_accounts WHERE user_id = ?');
$st->execute([$uid]);
$bankCount = (int) $st->fetchColumn();

$initials = $user ? mb_strtoupper(mb_substr($user['name'], 0, 1) . (mb_strpos($user['name'], ' ')
    ? mb_substr($user['name'], mb_strpos($user['name'], ' ') + 1, 1) : '')) : '';

$memberDays = $user ? max(1, (int) floor((time() - strtotime($user['created_at'])) / 86400)) : 0;

$active_tab = 'profile';
$page_title  = 'Profile';
require __DIR__ . '/includes/header.php';
?>

<?php if (!$user): ?>
<?= guest_cta('Log in to view your profile', 'Your portfolio, SIPs, banks and settings live here after you log in.') ?>
<?php else: ?>

<div class="profile-head">
  <div class="avatar"><?= e($initials ?: 'U') ?></div>
  <div class="p-name"><?= e($user['name']) ?></div>
  <div class="p-sub"><?= e($user['email']) ?> · +91 <?= e($user['phone']) ?></div>
  <span class="p-badge"><?= lucide('crown') ?> Gullak member · <?= $memberDays ?> day<?= $memberDays === 1 ? '' : 's' ?></span>
</div>

<!-- quick stats -->
<div class="mini-grid">
  <div class="mini">
    <div class="mk"><?= lucide('gem') ?> Portfolio</div>
    <div class="mv maskable"><?= money($portVal, 0) ?></div>
    <div class="ms"><?= $invested > 0 ? (($pl >= 0 ? '+' : '') . money($pl, 0) . ' P&L') : 'start investing' ?></div>
  </div>
  <div class="mini">
    <div class="mk"><?= lucide('wallet') ?> Wallet</div>
    <div class="mv maskable"><?= money($balance, 0) ?></div>
    <div class="ms">ready to invest</div>
  </div>
  <div class="mini">
    <div class="mk"><?= lucide('history') ?> Activity</div>
    <div class="mv"><?= number_format($txnCount) ?></div>
    <div class="ms">lifetime transactions</div>
  </div>
</div>

<!-- account -->
<div class="sec-head"><?= lucide('user-round') ?><h2>Account</h2></div>
<div class="menu">
  <a class="menu-item" href="<?= url('settings.php#account') ?>">
    <span class="mi-icon"><?= lucide('user-round-pen') ?></span>
    <span class="mi-body">
      <span class="mi-title">Personal details</span>
      <span class="mi-sub"><?= e($user['name']) ?> · +91 <?= e($user['phone']) ?></span>
    </span>
    <span class="mi-end"><?= lucide('chevron-right') ?></span>
  </a>
  <a class="menu-item" href="<?= url('settings.php#bank') ?>">
    <span class="mi-icon tone-blue"><?= lucide('landmark') ?></span>
    <span class="mi-body">
      <span class="mi-title">Bank accounts</span>
      <span class="mi-sub"><?= $bankCount ?> saved · used for withdrawals</span>
    </span>
    <span class="mi-end"><?= lucide('chevron-right') ?></span>
  </a>
  <a class="menu-item" href="<?= url('settings.php#security') ?>">
    <span class="mi-icon tone-green"><?= lucide('shield-check') ?></span>
    <span class="mi-body">
      <span class="mi-title">Security</span>
      <span class="mi-sub">Change password &amp; account safety</span>
    </span>
    <span class="mi-end"><?= lucide('chevron-right') ?></span>
  </a>
  <a class="menu-item" href="<?= url('settings.php#notifications') ?>">
    <span class="mi-icon tone-slate"><?= lucide('bell') ?></span>
    <span class="mi-body">
      <span class="mi-title">Notification settings</span>
      <span class="mi-sub">Email &amp; SMS preferences</span>
    </span>
    <span class="mi-end"><?= lucide('chevron-right') ?></span>
  </a>
</div>

<!-- savings -->
<div class="sec-head"><?= lucide('piggy-bank') ?><h2>My savings</h2></div>
<div class="menu">
  <a class="menu-item" href="<?= url('portfolio.php') ?>">
    <span class="mi-icon"><?= lucide('chart-pie') ?></span>
    <span class="mi-body">
      <span class="mi-title">My portfolio</span>
      <span class="mi-sub"><?= grams_fmt($hold['gold']['grams']) ?> g gold · <?= grams_fmt($hold['silver']['grams']) ?> g silver</span>
    </span>
    <span class="mi-end"><?= lucide('chevron-right') ?></span>
  </a>
  <a class="menu-item" href="<?= url('sip.php') ?>">
    <span class="mi-icon"><?= lucide('repeat') ?></span>
    <span class="mi-body">
      <span class="mi-title">Manage SIPs</span>
      <span class="mi-sub"><?= $sipCount ? $sipCount . ' active plan' . ($sipCount > 1 ? 's' : '') : 'automate your savings' ?></span>
    </span>
    <span class="mi-end"><?= lucide('chevron-right') ?></span>
  </a>
  <a class="menu-item" href="<?= url('history.php') ?>">
    <span class="mi-icon tone-slate"><?= lucide('receipt-text') ?></span>
    <span class="mi-body">
      <span class="mi-title">Transaction history</span>
      <span class="mi-sub">Every buy, sell, SIP &amp; transfer</span>
    </span>
    <span class="mi-end"><?= lucide('chevron-right') ?></span>
  </a>
  <a class="menu-item" href="<?= url('notifications.php') ?>">
    <span class="mi-icon tone-blue"><?= lucide('bell') ?></span>
    <span class="mi-body">
      <span class="mi-title">Account activity</span>
      <span class="mi-sub">Notifications &amp; alerts</span>
    </span>
    <span class="mi-end"><?= lucide('chevron-right') ?></span>
  </a>
</div>
<?php endif; ?>

<!-- more (public) -->
<div class="sec-head"><?= lucide('circle-help') ?><h2>More</h2></div>
<div class="menu">
  <a class="menu-item" href="<?= url('help.php') ?>">
    <span class="mi-icon"><?= lucide('life-buoy') ?></span>
    <span class="mi-body">
      <span class="mi-title">Help &amp; support</span>
      <span class="mi-sub">FAQs and how things work</span>
    </span>
    <span class="mi-end"><?= lucide('chevron-right') ?></span>
  </a>
  <a class="menu-item" href="<?= url('about.php') ?>">
    <span class="mi-icon tone-slate"><?= lucide('info') ?></span>
    <span class="mi-body">
      <span class="mi-title">About <?= e(APP_NAME) ?></span>
      <span class="mi-sub">What this app is &amp; disclaimers</span>
    </span>
    <span class="mi-end"><?= lucide('chevron-right') ?></span>
  </a>
  <a class="menu-item" href="<?= url('terms.php') ?>">
    <span class="mi-icon tone-slate"><?= lucide('file-text') ?></span>
    <span class="mi-body">
      <span class="mi-title">Terms &amp; conditions</span>
    </span>
    <span class="mi-end"><?= lucide('chevron-right') ?></span>
  </a>
  <a class="menu-item" href="<?= url('privacy.php') ?>">
    <span class="mi-icon tone-slate"><?= lucide('shield-check') ?></span>
    <span class="mi-body">
      <span class="mi-title">Privacy policy</span>
    </span>
    <span class="mi-end"><?= lucide('chevron-right') ?></span>
  </a>
</div>

<?php if ($user): ?>
<a class="btn btn-danger-ghost" href="<?= url('logout.php') ?>" style="display:flex" data-confirm="Log out of your account?"><?= lucide('log-out') ?> Log out</a>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
