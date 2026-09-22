<?php
require_once __DIR__ . '/includes/bootstrap.php';
$user = require_login();

$pdo     = db();
$uid     = (int) $user['id'];
$balance = wallet_balance($uid);

/* money in / out this month */
$st = $pdo->prepare(
    'SELECT
       COALESCE(SUM(CASE WHEN wallet_delta > 0 THEN  wallet_delta END), 0) AS inflow,
       COALESCE(SUM(CASE WHEN wallet_delta < 0 THEN -wallet_delta END), 0) AS outflow
     FROM transactions
     WHERE user_id = ? AND wallet_delta <> 0
       AND created_at >= DATE_FORMAT(NOW(), "%Y-%m-01")');
$st->execute([$uid]);
$flow = $st->fetch() ?: ['inflow' => 0, 'outflow' => 0];

$st = $pdo->prepare('SELECT * FROM transactions
                     WHERE user_id = ? AND wallet_delta <> 0
                     ORDER BY id DESC LIMIT 15');
$st->execute([$uid]);
$txns = $st->fetchAll();

$active_tab = 'wallet';
$page_title  = 'Wallet';
require __DIR__ . '/includes/header.php';
?>

<div class="page-title">Wallet</div>
<p class="page-sub">Money here is instantly usable for buying gold &amp; silver.</p>

<div class="hero" style="background:linear-gradient(135deg,#34D399,#059669);box-shadow:0 12px 28px rgba(5,150,105,.32)">
  <button class="eye-btn" data-toggle-balance aria-label="Show or hide balances"><?= lucide('eye') ?></button>
  <div class="hero-label"><?= lucide('wallet', 'ic-14') ?> Available balance</div>
  <div class="hero-value maskable"><?= money($balance) ?></div>
  <div class="hero-sub"><span>Used for purchases &amp; SIP instalments</span></div>
</div>

<div class="btn-row">
  <a class="btn" href="<?= url('add-money.php') ?>"><?= lucide('plus') ?> Add money</a>
  <a class="btn btn-ghost" href="<?= url('withdraw.php') ?>"><?= lucide('landmark') ?> Send to bank</a>
</div>

<div class="mini-grid mt14">
  <div class="mini">
    <div class="mk"><?= lucide('arrow-down-left') ?> Money in</div>
    <div class="mv" style="color:var(--green)"><?= money((float) $flow['inflow'], 0) ?></div>
    <div class="ms">this month</div>
  </div>
  <div class="mini">
    <div class="mk"><?= lucide('arrow-up-right') ?> Money out</div>
    <div class="mv" style="color:var(--red)"><?= money((float) $flow['outflow'], 0) ?></div>
    <div class="ms">this month</div>
  </div>
</div>

<div class="card">
  <div class="card-title">Wallet transactions</div>
  <?php if (!$txns): ?>
    <div class="empty">
      <div class="ico"><?= lucide('receipt') ?></div>
      <h3>No money movements yet</h3>
      <p>Top up your wallet to get started.</p>
    </div>
  <?php else: ?>
    <div class="list">
      <?php foreach ($txns as $t): [$label, $dir, $sign] = txn_label($t['type']); ?>
        <div class="list-item">
          <div class="li-icon <?= $dir === 'in' ? 'money-in' : 'money-out' ?>">
            <?= lucide($dir === 'in' ? 'arrow-down-left' : 'arrow-up-right') ?>
          </div>
          <div class="li-body">
            <div class="li-title"><?= e($label) ?></div>
            <div class="li-sub"><?= e(dt_ist($t['created_at'])) ?></div>
          </div>
          <div class="li-right">
            <div class="li-amt <?= $dir ?>"><?= $sign ?><?= money(abs((float) $t['wallet_delta'])) ?></div>
            <?php if ($t['grams_delta'] !== null && (float) $t['grams_delta'] != 0.0): ?>
              <div class="li-sub"><?= (float) $t['grams_delta'] > 0 ? '+' : '' ?><?= grams_fmt($t['grams_delta']) ?> g</div>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
    <div class="section-head mt14"><a href="<?= url('history.php') ?>">View full history ›</a></div>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
