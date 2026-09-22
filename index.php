<?php
require_once __DIR__ . '/includes/bootstrap.php';
$user = require_login();

$pdo     = db();
$uid     = (int) $user['id'];
$rates   = get_rates();
$hold    = get_holdings($uid);
$balance = wallet_balance($uid);

/* portfolio value at current sell rates */
$goldVal  = $hold['gold']['grams']   * ($rates['gold']['sell']   ?? 0);
$silvVal  = $hold['silver']['grams'] * ($rates['silver']['sell'] ?? 0);
$totalVal = $goldVal + $silvVal;
$invested = $hold['gold']['invested'] + $hold['silver']['invested'];
$pl       = $totalVal - $invested;
$plPct    = $invested > 0 ? ($pl / $invested * 100) : 0;

/* rate change vs. the last price point older than 24h (both metals) */
$chg = ['gold' => null, 'silver' => null];
foreach (array_keys($chg) as $m) {
    if (!empty($rates[$m])) {
        $st = $pdo->prepare(
            'SELECT sell_rate FROM price_history
              WHERE metal = ? AND recorded_at <= (NOW() - INTERVAL 24 HOUR)
              ORDER BY recorded_at DESC LIMIT 1');
        $st->execute([$m]);
        $oldRate = $st->fetchColumn();
        if ($oldRate) {
            $chg[$m] = (($rates[$m]['sell'] - (float) $oldRate) / (float) $oldRate) * 100;
        }
    }
}

/* gold price history for the chart (last 40 points) */
$chartLabels = $chartBuy = $chartSell = [];
$st = $pdo->query('SELECT buy_rate, sell_rate, recorded_at FROM price_history
                    WHERE metal = "gold" ORDER BY recorded_at DESC, id DESC LIMIT 40');
foreach ($st->fetchAll() as $r) {
    array_unshift($chartLabels, date('d M', strtotime($r['recorded_at'])));
    array_unshift($chartBuy,    (float) $r['buy_rate']);
    array_unshift($chartSell,   (float) $r['sell_rate']);
}
if ($chartLabels) { // append current rate so the chart never lags
    $chartLabels[] = 'Now';
    $chartBuy[]    = $rates['gold']['buy']  ?? end($chartBuy);
    $chartSell[]   = $rates['gold']['sell'] ?? end($chartSell);
}

/* active SIPs (banner) */
$st = $pdo->prepare('SELECT * FROM sip_plans WHERE user_id = ? AND status = "active" ORDER BY next_run LIMIT 3');
$st->execute([$uid]);
$activeSips = $st->fetchAll();

/* recent activity */
$st = $pdo->prepare('SELECT * FROM transactions WHERE user_id = ? ORDER BY id DESC LIMIT 5');
$st->execute([$uid]);
$recent = $st->fetchAll();

$active_tab = 'home';
$page_title  = 'Home';
$load_chart  = true;
require __DIR__ . '/includes/header.php';

$firstName = explode(' ', trim($user['name']))[0];
?>

<div class="page-title-row" style="display:flex;align-items:flex-start;justify-content:space-between;gap:10px">
  <div>
    <h1 class="page-title" style="margin-top:0">Hi, <?= e($firstName) ?></h1>
    <p class="page-sub" style="margin-bottom:0"><?= e(date('l, d M Y')) ?> · your savings in gold &amp; silver</p>
  </div>
  <a class="icon-btn" href="<?= url('notifications.php') ?>" aria-label="Notifications" style="flex:0 0 38px">
    <?= lucide('bell') ?>
  </a>
</div>

<!-- portfolio hero -->
<div class="hero">
  <button class="eye-btn" data-toggle-balance aria-label="Show or hide balances"><?= lucide('eye') ?></button>
  <div class="hero-label"><?= lucide('gem', 'ic-14') ?> Total portfolio value</div>
  <div class="hero-value maskable"><?= money($totalVal) ?></div>
  <div class="hero-sub">
    <?php if ($invested > 0): ?>
      <span class="<?= $pl >= 0 ? 'up' : 'down' ?>"><?= ($pl >= 0 ? '+' : '') . money($pl) ?></span>
      <span><?= ($pl >= 0 ? '+' : '') ?><?= number_format($plPct, 2) ?>% overall</span>
      <span style="opacity:.85">Invested <?= money($invested, 0) ?></span>
    <?php else: ?>
      <span>Start investing to see growth here</span>
    <?php endif; ?>
  </div>
  <div class="btn-row" style="margin-top:14px">
    <a class="btn btn-sm" style="background:rgba(255,255,255,.92);color:var(--primary);box-shadow:none" href="<?= url('buy.php?metal=gold') ?>"><?= lucide('plus') ?> Buy</a>
    <a class="btn btn-sm" style="background:rgba(0,0,0,.18);box-shadow:none" href="<?= url('sell.php') ?>"><?= lucide('arrow-up-right') ?> Sell</a>
  </div>
</div>

<!-- active SIP banner -->
<?php foreach ($activeSips as $sp): ?>
  <a class="sip-banner" href="<?= url('sip.php') ?>">
    <span class="sb-icon"><?= lucide('repeat') ?></span>
    <span class="sb-body">
      <span class="sb-title">SIP running · <?= money($sp['amount_inr'], 0) ?> <?= e($sp['metal']) ?></span>
      <span class="sb-sub"><?= e(ucfirst($sp['frequency'])) ?> · next instalment <?= date('d M Y', strtotime($sp['next_run'])) ?></span>
    </span>
    <span class="mi-end"><?= lucide('chevron-right') ?></span>
  </a>
<?php endforeach; ?>

<!-- quick actions -->
<div class="quick">
  <a class="qa qa-buy-gold" href="<?= url('buy.php?metal=gold') ?>">
    <?= lucide('gem') ?>
    Buy Gold
  </a>
  <a class="qa qa-buy-silver" href="<?= url('buy.php?metal=silver') ?>">
    <?= lucide('coins') ?>
    Buy Silver
  </a>
  <a class="qa qa-add" href="<?= url('add-money.php') ?>">
    <?= lucide('circle-plus') ?>
    Add Money
  </a>
  <a class="qa qa-out" href="<?= url('withdraw.php') ?>">
    <?= lucide('landmark') ?>
    Withdraw
  </a>
</div>

<!-- holdings -->
<div class="sec-head">
  <?= lucide('wallet-minimal') ?>
  <h2>Your holdings</h2>
  <span class="spacer"></span>
  <a href="<?= url('portfolio.php') ?>">Portfolio ›</a>
</div>
<div class="metal-grid">
  <?php foreach ([['gold', 'Gold · 24K'], ['silver', 'Silver · 999']] as [$m, $label]): ?>
    <div class="metal-card <?= $m ?>">
      <div class="coin coin-<?= $m ?>"><?= lucide('indian-rupee') ?></div>
      <h3><?= e($label) ?></h3>
      <div class="g"><?= grams_fmt($hold[$m]['grams']) ?> g</div>
      <div class="v maskable"><?= money($m === 'gold' ? $goldVal : $silvVal) ?></div>
      <?php if ($hold[$m]['grams'] > 0): ?>
        <?php $mpl = ($m === 'gold' ? $goldVal : $silvVal) - $hold[$m]['invested']; ?>
        <div class="pl <?= $mpl >= 0 ? 'up' : 'down' ?>"><?= $mpl >= 0 ? '+' : '' ?><?= money($mpl) ?></div>
      <?php else: ?>
        <div class="pl" style="color:#B8AE9E">Not started</div>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</div>

<!-- live rates (admin-set) -->
<div class="sec-head">
  <?= lucide('badge-indian-rupee') ?>
  <h2>Today's rates</h2>
</div>
<div class="rate-strip">
  <?php foreach (['gold' => 'Gold 24K', 'silver' => 'Silver 999'] as $m => $label): ?>
    <div class="rate-box">
      <div class="m"><span class="dot <?= $m ?>"></span> <?= e($label) ?></div>
      <div class="r"><?= money($rates[$m]['buy'] ?? 0) ?><span class="small muted">/g</span></div>
      <div class="s">Sell back at <?= money($rates[$m]['sell'] ?? 0) ?>/g</div>
      <?php if ($chg[$m] !== null): ?>
        <span class="rate-change <?= $chg[$m] >= 0 ? 'up' : 'down' ?>" style="margin-top:6px">
          <?= lucide($chg[$m] >= 0 ? 'trending-up' : 'trending-down') ?>
          <?= number_format(abs($chg[$m]), 2) ?>% (24h)
        </span>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</div>
<p class="muted small" style="margin:-8px 2px 14px">Rates are set by the platform · updated <?= e(dt_ist($rates['gold']['updated_at'] ?? null)) ?></p>

<!-- gold price chart -->
<div class="card">
  <div class="card-title">Gold price trend (₹/gram)</div>
  <div class="chart-box">
    <canvas id="priceChart"></canvas>
  </div>
</div>

<!-- recent activity -->
<div class="card">
  <div class="card-title">Recent activity</div>
  <?php if (!$recent): ?>
    <div class="empty">
      <div class="ico"><?= lucide('hand-coins') ?></div>
      <h3>No transactions yet</h3>
      <p>Add money to your wallet and buy your first bit of gold.</p>
      <a class="btn btn-sm" href="<?= url('add-money.php') ?>"><?= lucide('plus') ?> Add money</a>
    </div>
  <?php else: ?>
    <div class="list">
      <?php foreach ($recent as $t): [$label, $dir, $sign] = txn_label($t['type']); ?>
        <div class="list-item">
          <div class="li-icon <?= $t['metal'] ? $t['metal'] : ($dir === 'in' ? 'money-in' : 'money-out') ?>">
            <?= lucide($t['metal'] ? ($t['metal'] === 'gold' ? 'gem' : 'coins')
                : ($dir === 'in' ? 'arrow-down-left' : 'arrow-up-right')) ?>
          </div>
          <div class="li-body">
            <div class="li-title"><?= e($label) ?><?= $t['metal'] ? ' · ' . e($t['metal']) : '' ?></div>
            <div class="li-sub"><?= e(dt_ist($t['created_at'])) ?></div>
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
    <div class="section-head mt14"><a href="<?= url('history.php') ?>">View full history ›</a></div>
  <?php endif; ?>
</div>

<?php if ($chartLabels): ?>
<script>
window.addEventListener('load', function () {
  if (typeof Chart === 'undefined') return;
  new Chart(document.getElementById('priceChart'), {
    type: 'line',
    data: {
      labels: <?= json_encode($chartLabels) ?>,
      datasets: [
        {
          label: 'Buy rate', data: <?= json_encode($chartBuy) ?>,
          borderColor: '#D97706', backgroundColor: 'rgba(245,158,11,.14)',
          fill: true, tension: .35, pointRadius: 2, borderWidth: 2
        },
        {
          label: 'Sell rate', data: <?= json_encode($chartSell) ?>,
          borderColor: '#9CA3AF', borderDash: [5, 4], fill: false, tension: .35, pointRadius: 2, borderWidth: 1.5
        }
      ]
    },
    options: {
      maintainAspectRatio: false,
      plugins: { legend: { labels: { boxWidth: 10, font: { family: 'Nunito', weight: '700', size: 11 } } } },
      scales: {
        y: { ticks: { callback: v => '₹' + v.toLocaleString('en-IN'), font: { size: 10 } } },
        x: { ticks: { font: { size: 10 }, maxTicksLimit: 6 } }
      }
    }
  });
});
</script>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
