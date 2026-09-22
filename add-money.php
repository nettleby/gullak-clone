<?php
require_once __DIR__ . '/includes/bootstrap.php';
$user = require_login();

$uid    = (int) $user['id'];
$amount = null;
$order  = null;   // ['order_id'=>..., 'amount'=>...] once created

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_order') {
    csrf_check();
    $amount = dec_to_scaled($_POST['amount'] ?? '', 2) / 100;          // exact 2-decimal ₹

    if ($amount < MIN_DEPOSIT) {
        flash_set('error', 'Minimum top-up is ' . money(MIN_DEPOSIT, 0) . '.');
        header('Location: ' . url('add-money.php'));
        exit;
    }
    if ($amount > MAX_DEPOSIT) {
        flash_set('error', 'Maximum top-up is ' . money(MAX_DEPOSIT, 0) . ' per order.');
        header('Location: ' . url('add-money.php'));
        exit;
    }
    if (!RZP_ENABLED) {
        flash_set('error', 'Payments are temporarily disabled.');
        header('Location: ' . url('wallet.php'));
        exit;
    }

    $res = rzp_create_order($amount);
    if (!$res['ok']) {
        flash_set('error', $res['msg']);
        header('Location: ' . url('add-money.php'));
        exit;
    }

    /* remember the order — verify endpoint will match against it */
    $st = db()->prepare('INSERT INTO payments (user_id, order_id, amount) VALUES (?,?,?)');
    $st->execute([$uid, $res['order_id'], $amount]);
    $order = ['order_id' => $res['order_id'], 'amount' => $amount];
}

$active_title = 'wallet';
$page_title   = 'Add money';
require __DIR__ . '/includes/header.php';
?>
<meta name="base-url" content="<?= e(BASE_URL) ?>">

<div class="page-title">Add money</div>
<p class="page-sub">Secure payment via Razorpay (test mode).</p>

<?php if ($order): ?>
  <!-- step 2: pay the created order -->
  <div class="card">
    <div class="card-title">Confirm &amp; pay</div>
    <div class="kv"><span class="k">Amount</span><span class="v"><?= money($order['amount']) ?></span></div>
    <div class="kv"><span class="k">Goes to</span><span class="v">MeraGullak wallet</span></div>
    <div class="kv"><span class="k">Order</span><span class="v mono"><?= e($order['order_id']) ?></span></div>

    <div class="mt20">
      <input type="hidden" id="csrf-token" value="<?= e(csrf_token()) ?>">
      <button class="btn" id="rzp-btn" onclick="rzpStart(this)"
              data-order="<?= e($order['order_id']) ?>"
              data-amount="<?= (int) round($order['amount'] * 100) ?>"
              data-key="<?= e(RZP_KEY_ID) ?>"
              data-uname="<?= e($user['name']) ?>"
              data-uemail="<?= e($user['email']) ?>"
              data-uphone="<?= e($user['phone']) ?>">
        <?= lucide('lock') ?> Pay <?= money($order['amount']) ?> securely
      </button>
    </div>
    <p class="field-hint center mt8">Test card: 4111 1111 1111 1111 · any future date · any CVV</p>
    <p class="field-hint center">Or change your mind? <a href="<?= url('add-money.php') ?>" style="color:var(--amber-700);font-weight:800">Back</a></p>
  </div>

<?php else: ?>
  <!-- step 1: choose amount -->
  <div class="card">
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="create_order">
      <label class="field-label" for="amount">Enter amount</label>
      <div class="input-wrap">
        <span class="prefix">₹</span>
        <input class="field" id="amount" name="amount" type="number" min="<?= MIN_DEPOSIT ?>"
               max="<?= MAX_DEPOSIT ?>" step="10" value="500" inputmode="decimal" required>
      </div>
      <p class="field-hint">Between <?= money(MIN_DEPOSIT, 0) ?> and <?= money(MAX_DEPOSIT, 0) ?> per order.</p>

      <div class="chip-row">
        <button type="button" class="chip" onclick="document.getElementById('amount').value='500'">₹500</button>
        <button type="button" class="chip" onclick="document.getElementById('amount').value='1000'">₹1,000</button>
        <button type="button" class="chip" onclick="document.getElementById('amount').value='2000'">₹2,000</button>
        <button type="button" class="chip" onclick="document.getElementById('amount').value='5000'">₹5,000</button>
      </div>

      <div class="mt14">
        <button class="btn" type="submit"><?= lucide('arrow-right') ?> Continue to payment</button>
      </div>
    </form>
  </div>
<?php endif; ?>

<div class="card">
  <div class="card-title"><?= lucide('shield-check', 'ic-14') ?> Payments are powered by Razorpay · TEST MODE</div>
  <div class="kv"><span class="k">Test card</span><span class="v">4111 1111 1111 1111</span></div>
  <div class="kv"><span class="k">Test UPI</span><span class="v">success@razorpay</span></div>
  <div class="kv"><span class="k">Wallet</span><span class="v"><?= money(wallet_balance($uid)) ?></span></div>
</div>

<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script src="<?= url('assets/js/checkout.js') ?>"></script>
<?php require __DIR__ . '/includes/footer.php'; ?>
