<?php
require_once __DIR__ . '/includes/bootstrap.php';
$user = current_user();

$uid   = $user ? (int) $user['id'] : 0;
$rates = get_rates();
$hold  = $uid ? get_holdings($uid)
    : ['gold' => ['grams' => 0, 'invested' => 0], 'silver' => ['grams' => 0, 'invested' => 0]];
$metal = ($_GET['metal'] ?? ($_POST['metal'] ?? 'gold')) === 'silver' ? 'silver' : 'gold';

/* nothing to sell at all? */
if ($hold['gold']['grams'] <= 0 && $hold['silver']['grams'] <= 0) {
    $active_tab = 'invest';
    $page_title   = 'Sell';
    require __DIR__ . '/includes/header.php';
    ?>
    <div class="page-title">Sell metal</div>
    <div class="card">
      <div class="empty">
        <div class="ico"><?= lucide('hand-coins') ?></div>
        <h3>Nothing to sell yet</h3>
        <p>You don't hold any gold or silver right now. Buy some first and it will show up here.</p>
        <a class="btn btn-sm" href="<?= url('buy.php?metal=gold') ?>"><?= lucide('plus') ?> Buy gold</a>
      </div>
    </div>
    <?php if (!$user): ?>
    <?= guest_cta('Log in to sell metal', 'Selling credits your wallet instantly — log in to cash out your holdings.') ?>
    <?php endif; ?>
    <?php
    require __DIR__ . '/includes/footer.php';
    exit;
}

/* if requested metal has no holdings, switch to the one that does */
if ($hold[$metal]['grams'] <= 0) $metal = $metal === 'gold' ? 'silver' : 'gold';

$rate     = $rates[$metal]['sell'];
$gramsMax = $hold[$metal]['grams'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = require_login();   // selling is an action — guests log in first (returns here after)
    $uid = (int) $user['id'];
    csrf_check();
    $grams = dec_to_scaled($_POST['grams'] ?? '', 4) / 10000;      // exact 4-decimal grams
    $res   = execute_sell(db(), $uid, $metal, $grams);
    if ($res['ok']) {
        flash_set('success', 'Sold ' . grams_fmt($grams) . ' g ' . $metal . ' — '
            . money($res['amount']) . ' credited to your wallet.');
        header('Location: ' . url('wallet.php'));
        exit;
    }
    flash_set('error', $res['msg']);
    header('Location: ' . url('sell.php?metal=' . $metal));
    exit;
}

/* avg cost basis for the P&L estimate */
$avgCost = $gramsMax > 0 ? $hold[$metal]['invested'] / $gramsMax : 0;

$active_tab = 'invest';
$page_title  = 'Sell ' . ucfirst($metal);
require __DIR__ . '/includes/header.php';
?>

<div class="page-title">Sell <?= e($metal) ?></div>
<p class="page-sub">Proceeds are credited to your wallet instantly.</p>

<div class="metal-switch">
  <?php foreach (['gold', 'silver'] as $m): ?>
    <a class="metal-pill <?= $metal === $m ? ($m === 'gold' ? 'active-gold' : 'active-silver') : '' ?>"
       style="<?= $hold[$m]['grams'] <= 0 ? 'opacity:.45;pointer-events:none' : '' ?>"
       href="<?= url('sell.php?metal=' . $m) ?>">
      <?= lucide($m === 'gold' ? 'gem' : 'coins') ?> <?= $m === 'gold' ? 'Gold' : 'Silver' ?>
      <span class="sub"><?= grams_fmt($hold[$m]['grams']) ?> g held</span>
    </a>
  <?php endforeach; ?>
</div>

<div class="card">
  <div id="sell-calc" data-mode="grams" data-metal="<?= e($metal) ?>" data-rate="<?= e($rate) ?>" data-max="<?= e(grams_fmt($gramsMax)) ?>">
    <div class="kv"><span class="k">You sell at</span><span class="v"><?= money($rate) ?> / gram</span></div>
    <div class="kv"><span class="k">Your holdings</span><span class="v"><?= grams_fmt($gramsMax) ?> g</span></div>
    <div class="kv"><span class="k">Invested so far</span><span class="v"><?= money($hold[$metal]['invested']) ?></span></div>

    <label class="field-label" for="amt-grams">Weight to sell (grams)</label>
    <div class="input-wrap">
      <span class="prefix">g</span>
      <input class="field" id="amt-grams" type="number" min="<?= MIN_SELL_GRAMS ?>"
             max="<?= e(grams_fmt($gramsMax)) ?>" step="0.0001" placeholder="0.5" inputmode="decimal" autofocus>
    </div>
    <div class="chip-row">
      <button type="button" class="chip" data-fillpct="25">25%</button>
      <button type="button" class="chip" data-fillpct="50">50%</button>
      <button type="button" class="chip" data-fillpct="75">75%</button>
      <button type="button" class="chip" data-fillpct="100">Max</button>
    </div>
    <input type="hidden" id="amt-inr" value="">

    <div class="summary">
      <div class="summary-row"><span>Rate</span><span><?= money($rate) ?>/g</span></div>
      <div class="summary-row big"><span>You receive</span><span id="out-inr">₹0</span></div>
      <div class="summary-row"><span>Remaining after sale</span><span id="out-grams">…</span></div>
      <div class="summary-row"><span>Est. P&amp;L on this sale</span><span id="out-pl">—</span></div>
    </div>

    <form method="post" class="mt14" data-confirm="Sell this much <?= e($metal) ?>? Proceeds go to your wallet instantly.">
      <?= csrf_field() ?>
      <input type="hidden" name="metal" value="<?= e($metal) ?>">
      <input type="hidden" name="grams" id="post-grams" value="">
      <button class="btn btn-danger" id="calc-submit" type="submit" disabled><?= lucide('arrow-up-right') ?> Sell <?= e($metal) ?></button>
    </form>
    <p class="field-hint center mt8">Minimum sale <?= grams_fmt(MIN_SELL_GRAMS) ?> g · withdraw to bank from the Wallet tab</p>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  var max   = <?= (float) $gramsMax ?>;
  var avg   = <?= (float) $avgCost ?>;
  var out   = document.getElementById('out-grams');
  var plEl  = document.getElementById('out-pl');
  var inG   = document.getElementById('amt-grams');
  var pG    = document.getElementById('post-grams');

  function remaining() {
    var v = parseFloat(inG.value || '0');
    out.textContent = (Math.max(0, max - v)).toFixed(4) + ' g';
    pG.value = inG.value || '';
    if (plEl) {
      var proceeds = (window.mgCalc ? window.mgCalc.paisaForGrams(inG.value, <?= json_encode((string) $rate) ?>) / 100
                                   : v * <?= (float) $rate ?>);
      var cost     = v * avg;
      var pl       = proceeds - cost;
      plEl.textContent = v > 0
        ? (pl >= 0 ? '+' : '−') + '₹' + Math.abs(Math.round(pl)).toLocaleString('en-IN')
        : '—';
      plEl.style.color = v > 0 ? (pl >= 0 ? 'var(--green)' : 'var(--red)') : 'inherit';
      plEl.style.fontWeight = '900';
    }
  }
  inG.addEventListener('input', remaining);
  remaining();
});
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
