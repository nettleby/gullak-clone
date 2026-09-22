<?php
require_once __DIR__ . '/includes/bootstrap.php';
$user = require_login();

$uid    = (int) $user['id'];
$metal  = ($_GET['metal'] ?? ($_POST['metal'] ?? 'gold')) === 'silver' ? 'silver' : 'gold';
$rates  = get_rates();
$balance = wallet_balance($uid);

if (!isset($rates[$metal])) {
    flash_set('error', 'Rates not configured yet. Please try again later.');
    header('Location: ' . url('index.php'));
    exit;
}
$rate = $rates[$metal]['buy'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $gramsIn = dec_to_scaled($_POST['grams'] ?? '', 4);            // exact, scaled ×10⁴
    $inrPaisa = dec_to_scaled($_POST['amount'] ?? '', 2);          // exact, scaled ×10²
    if ($inrPaisa <= 0) {
        $inr = $gramsIn > 0 ? exact_sell_amount($gramsIn / 10000, $rate) : 0.0;  // grams → ₹
    } else {
        $inr = $inrPaisa / 100;
    }

    $res = execute_buy(db(), $uid, $metal, $inr);
    if ($res['ok']) {
        flash_set('success', 'Bought ' . grams_fmt($res['grams']) . ' g of ' . $metal
            . ' for ' . money($inr) . ' at ' . money($res['rate']) . '/g');
        header('Location: ' . url('index.php'));
        exit;
    }
    flash_set('error', $res['msg']);
    header('Location: ' . url('buy.php?metal=' . $metal));
    exit;
}

$hold = get_holdings($uid);
$active_tab = 'invest';
$page_title  = 'Buy ' . ucfirst($metal);
require __DIR__ . '/includes/header.php';
?>

<div class="page-title">Buy <?= e($metal) ?></div>
<p class="page-sub">24K / 999 purity · money is deducted from your wallet.</p>

<!-- metal switch -->
<div class="metal-switch">
  <a class="metal-pill <?= $metal === 'gold' ? 'active-gold' : '' ?>" href="<?= url('buy.php?metal=gold') ?>">
    <?= lucide('gem') ?> Gold <span class="sub"><?= money($rates['gold']['buy'] ?? 0) ?>/g</span>
  </a>
  <a class="metal-pill <?= $metal === 'silver' ? 'active-silver' : '' ?>" href="<?= url('buy.php?metal=silver') ?>">
    <?= lucide('coins') ?> Silver <span class="sub"><?= money($rates['silver']['buy'] ?? 0) ?>/g</span>
  </a>
</div>

<div class="card">
  <div id="buy-calc" data-mode="inr" data-metal="<?= e($metal) ?>" data-rate="<?= e($rate) ?>">
    <div class="kv">
      <span class="k">You buy at</span>
      <span class="v"><?= money($rate) ?> / gram</span>
    </div>
    <div class="kv">
      <span class="k">Wallet balance</span>
      <span class="v"><span id="calc-balance" data-balance="<?= e($balance) ?>" class="maskable"><?= money($balance) ?></span></span>
    </div>
    <div class="kv">
      <span class="k">You hold</span>
      <span class="v"><?= grams_fmt($hold[$metal]['grams']) ?> g <?= e($metal) ?></span>
    </div>

    <div id="field-inr">
      <label class="field-label" for="amt-inr">Amount to invest</label>
      <div class="input-wrap">
        <span class="prefix">₹</span>
        <input class="field" id="amt-inr" name="amount" type="number" min="10" step="0.01"
               placeholder="500" inputmode="decimal" autofocus>
      </div>
      <input type="range" class="slider" id="amt-slider" min="10" max="50000" step="10" value="500"
             aria-label="Amount slider">
      <div class="chip-row">
        <button type="button" class="chip" data-fill="100" data-target="#amt-inr">₹100</button>
        <button type="button" class="chip" data-fill="500" data-target="#amt-inr">₹500</button>
        <button type="button" class="chip" data-fill="1000" data-target="#amt-inr">₹1,000</button>
        <button type="button" class="chip" data-fill="5000" data-target="#amt-inr">₹5,000</button>
      </div>
    </div>

    <div id="field-grams" style="display:none">
      <label class="field-label" for="amt-grams">Weight to buy (grams)</label>
      <div class="input-wrap">
        <span class="prefix">g</span>
        <input class="field" id="amt-grams" name="grams" type="number" min="0.001" step="0.0001"
               placeholder="0.5" inputmode="decimal">
      </div>
    </div>

    <div class="chip-row">
      <button type="button" class="chip active" data-mode="inr">In ₹</button>
      <button type="button" class="chip" data-mode="grams">In grams</button>
    </div>

    <div class="summary">
      <div class="summary-row"><span>Rate</span><span><?= money($rate) ?>/g</span></div>
      <div class="summary-row big"><span>You get</span><span id="out-grams">0.0000 g</span></div>
    </div>

    <div id="calc-lowbalance" style="display:none" class="flash flash-error mt8">
      Amount exceeds your wallet balance. <a href="<?= url('add-money.php') ?>" style="color:var(--red);font-weight:900">Add money</a> first.
    </div>

    <form method="post" class="mt14">
      <?= csrf_field() ?>
      <input type="hidden" name="metal" value="<?= e($metal) ?>">
      <input type="hidden" name="amount" id="post-amount" value="">
      <input type="hidden" name="grams" id="post-grams" value="">
      <button class="btn" id="calc-submit" type="submit" disabled><?= lucide('plus') ?> Buy <?= e($metal) ?></button>
    </form>
    <p class="field-hint center mt8">Minimum purchase <?= money(MIN_BUY_INR, 0) ?> · grams stored up to 4 decimals</p>
  </div>
</div>

<div class="card">
  <div class="card-title">How buying works</div>
  <div class="kv"><span class="k">Purity</span><span class="v"><?= $metal === 'gold' ? '24K · 999.9' : '999 pure' ?></span></div>
  <div class="kv"><span class="k">Stored as</span><span class="v">Digital units in your name</span></div>
  <div class="kv"><span class="k">Sell anytime</span><span class="v">Instantly to wallet at <?= money($rates[$metal]['sell']) ?>/g</span></div>
  <div class="kv"><span class="k">SIP available</span><span class="v"><a href="<?= url('sip.php') ?>" style="color:var(--amber-700);font-weight:900">Automate it →</a></span></div>
</div>

<script>
/* keep the hidden POST fields synced with the calculator inputs — the amount
   is derived with the same exact decimal formula the server uses */
document.addEventListener('DOMContentLoaded', function () {
  var inr  = document.getElementById('amt-inr');
  var g    = document.getElementById('amt-grams');
  var pAmt = document.getElementById('post-amount');
  var pG   = document.getElementById('post-grams');
  var rate = <?= json_encode((string) $rate) ?>;
  var form = pAmt.closest('form');
  form.addEventListener('submit', function () {
    if (inr.value) {
      pAmt.value = inr.value;
    } else if (g.value && window.mgCalc) {
      pAmt.value = window.mgCalc.inrStringForGrams(g.value, rate);  // exact grams→₹
    } else if (g.value) {
      pAmt.value = (parseFloat(g.value) * <?= (float) $rate ?>).toFixed(2);
    }
    pG.value = g.value || '';
  });
});
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
