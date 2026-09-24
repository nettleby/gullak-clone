<?php
/**
 * App shell — top. Expects (before include):
 *   $page_title  string   browser title
 *   $active_tab  string   home|invest|sip|wallet|profile  (hides tabbar if unset)
 *   $hide_header bool     (auth pages)
 *   $load_chart  bool     loads Chart.js on this page
 */
if (!isset($page_title)) $page_title = APP_NAME;
$u    = is_logged_in() ? current_user() : null;
$tabs = [
    'home'    => ['url' => 'index.php',          'label' => 'Home',    'icon' => 'house'],
    'invest'  => ['url' => 'buy.php',            'label' => 'Invest',  'icon' => 'gem'],
    'sip'     => ['url' => 'sip.php',            'label' => 'SIP',     'icon' => 'repeat'],
    'wallet'  => ['url' => 'wallet.php',         'label' => 'Wallet',  'icon' => 'wallet'],
    'profile' => ['url' => 'profile.php',        'label' => 'Profile', 'icon' => 'user-round'],
];

/* things that deserve the red dot on the bell (cheap indexed queries) */
$bell_dot = 0;
if ($u) {
    try {
        $st = db()->prepare(
            'SELECT (SELECT COUNT(*) FROM sip_plans WHERE user_id = ? AND status = "paused")
                  + (SELECT COUNT(*) FROM withdrawals WHERE user_id = ? AND status = "pending")');
        $st->execute([(int) $u['id'], (int) $u['id']]);
        $bell_dot = (int) $st->fetchColumn();
    } catch (Throwable $e) { $bell_dot = 0; }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#F59E0B">
<title><?= e($page_title) ?> · <?= e(APP_NAME) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&display=swap" rel="stylesheet">
<?php if (!empty($load_chart)): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<?php endif; ?>
<link rel="stylesheet" href="<?= url('assets/css/style.css') ?>">
</head>
<body>
<div class="app">
<?php if (empty($hide_header)): ?>
  <header class="topbar">
    <a class="brand" href="<?= url('index.php') ?>">
      <span class="brand-mark"><?= lucide('piggy-bank') ?></span>
      <span class="brand-name"><?= e(APP_NAME) ?></span>
    </a>
    <div class="topbar-actions">
      <?php if ($u): ?>
      <a class="icon-btn" href="<?= url('notifications.php') ?>" aria-label="Notifications">
        <?= lucide('bell') ?>
        <?php if ($bell_dot > 0): ?><span class="dot-alert"><?= (int) $bell_dot ?></span><?php endif; ?>
      </a>
      <a class="wallet-chip" href="<?= url('wallet.php') ?>">
        <?= lucide('wallet', 'ic-sm') ?>
        <span class="wallet-chip-amount"><?= money(wallet_balance((int) $u['id']), 2) ?></span>
      </a>
      <?php else: ?>
      <a class="btn btn-sm" href="<?= url('login.php?next=' . urlencode(current_path())) ?>"><?= lucide('log-in') ?> Log in</a>
      <?php endif; ?>
    </div>
  </header>
<?php endif; ?>
<main class="screen">
<?= flash_render() ?>
