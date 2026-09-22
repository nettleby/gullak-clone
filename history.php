<?php
require_once __DIR__ . '/includes/bootstrap.php';
$user = require_login();

$pdo = db();
$uid = (int) $user['id'];

/* filter tabs: all | gold | silver | in | out | sip */
$valid = ['all', 'gold', 'silver', 'in', 'out', 'sip'];
$f = in_array($_GET['f'] ?? '', $valid, true) ? $_GET['f'] : 'all';
$q = trim($_GET['q'] ?? '');

$where = 'user_id = ?';
$args  = [$uid];
if ($f === 'gold' || $f === 'silver') { $where .= ' AND metal = ?'; $args[] = $f; }
if ($f === 'in')   { $where .= ' AND wallet_delta > 0'; }
if ($f === 'out')  { $where .= ' AND wallet_delta < 0'; }
if ($f === 'sip')  { $where .= ' AND type = "sip_buy"'; }
if ($q !== '')     { $where .= ' AND (note LIKE ? OR type LIKE ?)'; $args[] = "%$q%"; $args[] = "%$q%"; }

$page  = max(1, (int) ($_GET['page'] ?? 1));
$per   = 25;
$st = $pdo->prepare("SELECT COUNT(*) FROM transactions WHERE $where");
$st->execute($args);
$total = (int) $st->fetchColumn();
$pages = max(1, (int) ceil($total / $per));
$page  = min($page, $pages);
$off   = ($page - 1) * $per;

$st = $pdo->prepare("SELECT * FROM transactions WHERE $where ORDER BY id DESC LIMIT $per OFFSET $off");
$st->execute($args);
$txns = $st->fetchAll();

$qs = fn($p) => url('history.php') . '?f=' . $f . '&q=' . urlencode($q) . '&page=' . $p;

$filtTabs = [
    'all'    => ['layers',   'All'],
    'gold'   => ['gem',      'Gold'],
    'silver' => ['coins',    'Silver'],
    'in'     => ['arrow-down-left', 'Money in'],
    'out'    => ['arrow-up-right', 'Money out'],
    'sip'    => ['repeat',   'SIP'],
];

$active_tab = 'profile';
$page_title  = 'History';
require __DIR__ . '/includes/header.php';

/* group rows by day for date headers */
$lastDay = '';
?>
<div class="page-title">History</div>
<p class="page-sub">Every buy, sell, SIP run and money movement.</p>

<div class="search-wrap">
  <?= lucide('search') ?>
  <form method="get" action="<?= url('history.php') ?>">
    <input type="hidden" name="f" value="<?= e($f) ?>">
    <input class="field" name="q" value="<?= e($q) ?>" placeholder="Search notes or types — try 'SIP' or 'gold'">
  </form>
</div>

<div class="filter-tabs">
  <?php foreach ($filtTabs as $k => [$icon, $label]): ?>
    <a href="<?= url('history.php?f=' . $k . ($q !== '' ? '&q=' . urlencode($q) : '')) ?>"
       class="<?= $f === $k ? 'active' : '' ?>" style="display:inline-flex;align-items:center;gap:5px">
      <?= lucide($icon, 'ic-14') ?> <?= e($label) ?>
    </a>
  <?php endforeach; ?>
</div>

<div class="card">
  <?php if (!$txns): ?>
    <div class="empty">
      <div class="ico"><?= lucide('search') ?></div>
      <h3>Nothing here yet</h3>
      <p><?= $q !== '' ? 'No transactions match your search.' : 'Transactions will appear as you start using the app.' ?></p>
    </div>
  <?php else: ?>
    <div class="list">
      <?php foreach ($txns as $t):
        [$label, $dir, $sign] = txn_label($t['type']);
        $day = date('d M Y', strtotime($t['created_at']));
        if ($day !== $lastDay):
            $lastDay = $day;
            $today = date('d M Y'); $yest = date('d M Y', strtotime('-1 day')); ?>
      <div class="date-group"><?= $day === $today ? 'Today' : ($day === $yest ? 'Yesterday' : $day) ?></div>
      <?php endif; ?>
      <div class="list-item">
        <div class="li-icon <?= $t['metal'] ? $t['metal'] : ($dir === 'in' ? 'money-in' : 'money-out') ?>">
          <?= lucide($t['metal'] ? ($t['metal'] === 'gold' ? 'gem' : 'coins')
              : ($dir === 'in' ? 'arrow-down-left' : 'arrow-up-right')) ?>
        </div>
        <div class="li-body">
          <div class="li-title"><?= e($label) ?><?= $t['metal'] ? ' · ' . e($t['metal']) : '' ?></div>
          <div class="li-sub"><?= e(date('h:i A', strtotime($t['created_at']))) ?></div>
          <?php if ($t['note']): ?><div class="li-sub"><?= e($t['note']) ?></div><?php endif; ?>
        </div>
        <div class="li-right">
          <?php if ((float) $t['wallet_delta'] != 0.0): ?>
            <div class="li-amt <?= $dir ?>"><?= $sign ?><?= money(abs((float) $t['wallet_delta'])) ?></div>
          <?php endif; ?>
          <?php if ($t['grams_delta'] !== null && (float) $t['grams_delta'] != 0.0): ?>
            <div class="li-sub"><?= (float) $t['grams_delta'] > 0 ? '+' : '' ?><?= grams_fmt($t['grams_delta']) ?> g</div>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <?php if ($pages > 1): ?>
      <div class="pager">
        <?php if ($page > 1): ?><a href="<?= $qs($page - 1) ?>">‹ Prev</a><?php endif; ?>
        <span class="cur">Page <?= $page ?> / <?= $pages ?></span>
        <?php if ($page < $pages): ?><a href="<?= $qs($page + 1) ?>">Next ›</a><?php endif; ?>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
