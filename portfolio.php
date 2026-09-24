<?php
require_once __DIR__ . '/includes/bootstrap.php';
$user = current_user();

$pdo   = db();
$uid   = $user ? (int) $user['id'] : 0;
$rates = get_rates();
$hold  = $uid ? get_holdings($uid)
    : ['gold' => ['grams' => 0, 'invested' => 0], 'silver' => ['grams' => 0, 'invested' => 0]];

$gVal  = $hold['gold']['grams']   * ($rates['gold']['sell']   ?? 0);
$sVal  = $hold['silver']['grams'] * ($rates['silver']['sell'] ?? 0);
$total = $gVal + $sVal;
$inv   = $hold['gold']['invested'] + $hold['silver']['invested'];
$pl    = $total - $inv;
$plPct = $inv > 0 ? $pl / $inv * 100 : 0;

/* avg buy prices */
$gAvg = $hold['gold']['grams']   > 0 ? $hold['gold']['invested']   / $hold['gold']['grams']   : 0;
$sAvg = $hold['silver']['grams'] > 0 ? $hold['silver']['invested'] / $hold['silver']['grams'] : 0;

$load_chart = true;
$active_tab = 'invest';
$page_title  = 'Portfolio';
require __DIR__ . '/includes/header.php';
?>

<div class="page-title">Portfolio</div>
<p class="page-sub">Everything you own, valued at today's rates.</p>

<?php if (!$user): ?>
<?= guest_cta('Log in to build your portfolio', 'Track allocation, average prices and P&L once you start investing.') ?>
<?php endif; ?>

<div class="hero">
  <button class="eye-btn" data-toggle-balance aria-label="Show or hide balances"><?= lucide('eye') ?></button>
  <div class="hero-label"><?= lucide('chart-pie', 'ic-14') ?> Total value</div>
  <div class="hero-value maskable"><?= money($total) ?></div>
  <div class="hero-sub">
    <?php if ($inv > 0): ?>
      <span class="<?= $pl >= 0 ? 'up' : 'down' ?>"><?= ($pl >= 0 ? '+' : '') . money($pl) ?> (<?= number_format($plPct, 2) ?>%)</span>
      <span style="opacity:.85">Invested <?= money($inv) ?></span>
    <?php else: ?>
      <span>Buy gold or silver to build your portfolio</span>
    <?php endif; ?>
  </div>
</div>

<?php if ($total > 0): ?>
<!-- allocation -->
<div class="card">
  <div class="card-title">Allocation</div>
  <div class="alloc-box">
    <div class="chart-box" style="height:130px">
      <canvas id="allocChart"></canvas>
    </div>
    <div class="alloc-legend">
      <div class="al-row">
        <span class="al-swatch" style="background:linear-gradient(135deg,#FFD977,#E8890C)"></span>
        Gold
        <span class="al-val"><?= $total > 0 ? number_format($gVal / $total * 100, 1) : '0' ?>%</span>
      </div>
      <div class="al-row">
        <span class="al-swatch" style="background:linear-gradient(135deg,#F1F5F9,#94A3B8)"></span>
        Silver
        <span class="al-val"><?= $total > 0 ? number_format($sVal / $total * 100, 1) : '0' ?>%</span>
      </div>
      <div class="al-row" style="color:var(--muted);font-weight:700">
        <span class="al-swatch" style="background:#EDE4D2"></span>
        Cash (wallet)
        <span class="al-val"><?= money(wallet_balance($uid), 0) ?></span>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<?php foreach ([
    ['gold',   'Gold · 24K · 999.9', $gVal, $gAvg],
    ['silver', 'Silver · 999 pure',  $sVal, $sAvg],
] as [$m, $label, $val, $avg]): ?>
<div class="metal-detail">
  <div class="md-head">
    <span class="coin coin-<?= $m ?>"><?= lucide('indian-rupee') ?></span>
    <h3 style="flex:1"><?= e($label) ?></h3>
    <?php if ($hold[$m]['grams'] > 0):
        $mpl = $val - $hold[$m]['invested']; ?>
      <span class="rate-change <?= $mpl >= 0 ? 'up' : 'down' ?>">
        <?= lucide($mpl >= 0 ? 'trending-up' : 'trending-down') ?>
        <?= ($mpl >= 0 ? '+' : '') . money($mpl) ?>
      </span>
    <?php endif; ?>
  </div>

  <?php if ($hold[$m]['grams'] <= 0): ?>
    <p class="li-sub" style="margin-bottom:10px">You don't own any <?= e($m) ?> yet.</p>
    <a class="btn btn-sm" href="<?= url('buy.php?metal=' . $m) ?>"><?= lucide('plus') ?> Buy <?= e($m) ?></a>
  <?php else: ?>
    <div class="kv"><span class="k">You hold</span><span class="v"><?= grams_fmt($hold[$m]['grams']) ?> g</span></div>
    <div class="kv"><span class="k">Avg buy price</span><span class="v"><?= money($avg) ?>/g</span></div>
    <div class="kv"><span class="k">Current sell rate</span><span class="v"><?= money($rates[$m]['sell']) ?>/g</span></div>
    <div class="kv"><span class="k">Invested</span><span class="v maskable"><?= money($hold[$m]['invested']) ?></span></div>
    <div class="kv"><span class="k">Current value</span><span class="v maskable"><?= money($val) ?></span></div>
    <div class="btn-row mt14">
      <a class="btn btn-sm" href="<?= url('buy.php?metal=' . $m) ?>"><?= lucide('plus') ?> Buy more</a>
      <a class="btn btn-sm btn-danger" href="<?= url('sell.php?metal=' . $m) ?>"><?= lucide('arrow-up-right') ?> Sell</a>
    </div>
  <?php endif; ?>
</div>
<?php endforeach; ?>

<div class="card">
  <div class="card-title">How valuation works</div>
  <div class="kv"><span class="k">Valued at</span><span class="v">Sell rate (what you'd get)</span></div>
  <div class="kv"><span class="k">Avg buy price</span><span class="v">Invested ÷ grams held</span></div>
  <div class="kv"><span class="k">P&amp;L</span><span class="v">Current value − invested</span></div>
  <p class="field-hint" style="margin-top:8px">Rates are set by the platform. The spread between buy and sell rates means short-term selling may recover slightly less than invested even if the rate hasn't changed.</p>
</div>

<?php if ($total > 0): ?>
<script>
window.addEventListener('load', function () {
  if (typeof Chart === 'undefined') return;
  new Chart(document.getElementById('allocChart'), {
    type: 'doughnut',
    data: {
      labels: ['Gold', 'Silver'],
      datasets: [{
        data: [<?= (float) $gVal ?>, <?= (float) $sVal ?>],
        backgroundColor: ['#F5A623', '#CBD5E1'],
        borderWidth: 0,
        cutout: '68%'
      }]
    },
    options: {
      maintainAspectRatio: false,
      plugins: { legend: { display: false } }
    }
  });
});
</script>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
