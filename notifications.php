<?php
require_once __DIR__ . '/includes/bootstrap.php';
$user = require_login();

$pdo = db();
$uid = (int) $user['id'];

/* things that need attention */
$st = $pdo->prepare('SELECT * FROM sip_plans WHERE user_id = ? AND status = "paused" ORDER BY next_run');
$st->execute([$uid]);
$pausedSips = $st->fetchAll();

$st = $pdo->prepare('SELECT w.*, b.bank_name, b.account_number
                     FROM withdrawals w JOIN bank_accounts b ON b.id = w.bank_account_id
                    WHERE w.user_id = ? AND w.status = "pending" ORDER BY w.id DESC');
$st->execute([$uid]);
$pendingWd = $st->fetchAll();

/* main feed */
$st = $pdo->prepare('SELECT * FROM transactions WHERE user_id = ? ORDER BY id DESC LIMIT 40');
$st->execute([$uid]);
$txns = $st->fetchAll();

$active_tab = 'profile';
$page_title  = 'Notifications';
require __DIR__ . '/includes/header.php';
?>

<div class="page-title">Notifications</div>
<p class="page-sub">Alerts and account activity.</p>

<?php if (!$pausedSips && !$pendingWd && !$txns): ?>
  <div class="card">
    <div class="empty">
      <div class="ico"><?= lucide('bell') ?></div>
      <h3>Nothing here yet</h3>
      <p>Account events like deposits, SIP runs and withdrawals will appear here.</p>
    </div>
  </div>
<?php endif; ?>

<?php if ($pausedSips): ?>
  <div class="sec-head"><?= lucide('circle-alert') ?><h2>Needs attention</h2></div>
<?php foreach ($pausedSips as $p): ?>
  <div class="alert-card">
    <?= lucide('repeat') ?>
    <div class="li-body">
      <div class="li-title">SIP paused — <?= money($p['amount_inr'], 0) ?> <?= e($p['metal']) ?> (<?= e($p['frequency']) ?>)</div>
      <div class="li-sub">It stopped after <?= (int) $p['failed_attempts'] ?> failed run<?= (int) $p['failed_attempts'] === 1 ? '' : 's' ?>.
        Top up your wallet and resume it to keep saving.</div>
      <div class="mt8"><a class="btn btn-sm btn-green" href="<?= url('sip.php') ?>"><?= lucide('play') ?> Resume SIP</a></div>
    </div>
  </div>
<?php endforeach; ?>
<?php endif; ?>

<?php if ($pendingWd): ?>
  <?php if (!$pausedSips): ?><div class="sec-head"><?= lucide('circle-alert') ?><h2>Needs attention</h2></div><?php endif; ?>
<?php foreach ($pendingWd as $w): ?>
  <div class="alert-card">
    <?= lucide('landmark') ?>
    <div class="li-body">
      <div class="li-title">Withdrawal pending — <?= money($w['amount']) ?></div>
      <div class="li-sub">To <?= e($w['bank_name']) ?> ···<?= e(substr($w['account_number'], -4)) ?>, requested <?= e(dt_ist($w['created_at'])) ?>. Usually settles 1-2 working days after approval.</div>
    </div>
  </div>
<?php endforeach; ?>
<?php endif; ?>

<?php if ($txns): ?>
<div class="sec-head"><?= lucide('bell') ?><h2>Activity</h2><span class="spacer"></span><a href="<?= url('history.php') ?>">Full history ›</a></div>
<div class="card">
  <?php foreach ($txns as $t): [$label, $dir, $sign] = txn_label($t['type']); ?>
    <div class="notif-item">
      <span class="n-icon <?= $t['metal'] ? $t['metal'] : ($dir === 'in' ? 'tone-green' : 'tone-red') ?>">
        <?= lucide($t['metal'] ? ($t['metal'] === 'gold' ? 'gem' : 'coins')
            : ($dir === 'in' ? 'arrow-down-left' : 'arrow-up-right')) ?>
      </span>
      <div class="li-body">
        <div class="n-title"><?= e($label) ?><?= $t['metal'] ? ' · ' . e($t['metal']) : '' ?></div>
        <div class="n-sub">
          <?php if ((float) $t['wallet_delta'] != 0.0): ?>
            <?= $sign ?><?= money(abs((float) $t['wallet_delta'])) ?>
          <?php endif; ?>
          <?php if ($t['grams_delta'] !== null && (float) $t['grams_delta'] != 0.0): ?>
            <?= (float) $t['grams_delta'] > 0 ? '+' : '' ?><?= grams_fmt($t['grams_delta']) ?> g
          <?php endif; ?>
        </div>
        <div class="n-when"><?= e(dt_ist($t['created_at'])) ?></div>
      </div>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
