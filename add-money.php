<?php
require_once __DIR__ . '/includes/bootstrap.php';
$user = current_user();

$uid    = $user ? (int) $user['id'] : 0;
$amount = null;
$pay    = null;   // ['merchantTxnNo'=>..., 'redirectURI'=>..., 'tranCtx'=>..., 'amount'=>...] once initiated

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_order') {
    $user = require_login();   // topping up is an action — guests log in first (returns here after)
    $uid = (int) $user['id'];
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
    if (!ICICI_ENABLED) {
        flash_set('error', 'Payments are temporarily disabled.');
        header('Location: ' . url('wallet.php'));
        exit;
    }

    $res = icici_initiate_sale($amount, ['id' => $uid, 'name' => $user['name'], 'email' => $user['email'], 'phone' => $user['phone']]);
    if (!$res['ok']) {
        flash_set('error', $res['msg']);
        header('Location: ' . url('add-money.php'));
        exit;
    }

    /* remember the merchant txn — callback will match against it */
    try {
        $st = db()->prepare('INSERT INTO payments (user_id, order_id, amount) VALUES (?,?,?)');
        $st->execute([$uid, $res['merchantTxnNo'], $amount]);
    } catch (PDOException $e) {
        // Extremely rare txn-no collision: retry once with a fresh number.
        if ($e->getCode() === '23000') {
            $res = icici_initiate_sale($amount, ['id' => $uid, 'name' => $user['name'], 'email' => $user['email'], 'phone' => $user['phone']]);
            if (!$res['ok']) {
                flash_set('error', $res['msg']);
                header('Location: ' . url('add-money.php'));
                exit;
            }
            $st = db()->prepare('INSERT INTO payments (user_id, order_id, amount) VALUES (?,?,?)');
            $st->execute([$uid, $res['merchantTxnNo'], $amount]);
        } else {
            throw $e;
        }
    }
    $pay = [
        'merchantTxnNo' => $res['merchantTxnNo'],
        'redirectURI'   => $res['redirectURI'],
        'tranCtx'       => $res['tranCtx'],
        'payment_url'   => $res['payment_url'],
        'amount'        => $amount,
    ];
}

/* Manual verify: user returns without callback (e.g. closed ICICI tab). */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'verify_order') {
    $user = require_login();
    $uid = (int) $user['id'];
    csrf_check();
    $txn = preg_replace('/[^A-Za-z0-9]/', '', (string) ($_POST['merchantTxnNo'] ?? ''));
    if ($txn === '') {
        flash_set('error', 'Missing transaction reference.');
        header('Location: ' . url('add-money.php'));
        exit;
    }
    $st = db()->prepare('SELECT * FROM payments WHERE order_id = ? AND user_id = ?');
    $st->execute([$txn, $uid]);
    $row = $st->fetch();
    if (!$row) {
        flash_set('error', 'Order not found for this account.');
        header('Location: ' . url('add-money.php'));
        exit;
    }
    if ($row['status'] === 'paid') {
        flash_set('success', money((float) $row['amount']) . ' already added to your wallet.');
        header('Location: ' . url('wallet.php'));
        exit;
    }
    $chk = icici_status_check($txn, (string) ($row['payment_id'] ?? ''));
    if (!$chk['ok']) {
        flash_set('error', $chk['msg'] ?? 'Status check failed. Try again.');
        header('Location: ' . url('add-money.php'));
        exit;
    }
    if ($chk['status'] === 'SUC') {
        $cr = icici_credit_wallet(db(), $uid, $txn, (string) (($chk['raw']['txnID'] ?? '') ?: ($chk['raw']['paymentID'] ?? '')));
        if ($cr['ok']) {
            flash_set('success', money($cr['amount']) . ($cr['already'] ? ' already' : '') . ' added to your wallet.');
            header('Location: ' . url('wallet.php'));
            exit;
        }
        flash_set('error', $cr['msg'] ?? 'Could not credit wallet.');
    } elseif ($chk['status'] === 'REJ') {
        db()->prepare('UPDATE payments SET status = "failed" WHERE id = ?')->execute([$row['id']]);
        flash_set('error', 'Payment was declined. No money was added.');
    } else {
        flash_set('error', 'Payment is still pending at the bank. Try verifying again in a minute.');
    }
    header('Location: ' . url('add-money.php'));
    exit;
}

/* Pending ICICI orders for this user (created, newest first) — for manual verify. */
$st = db()->prepare('SELECT order_id, amount, created_at FROM payments WHERE user_id = ? AND status = "created" ORDER BY id DESC LIMIT 5');
$st->execute([$uid]);
$pending = $st->fetchAll();

$active_title = 'wallet';
$page_title   = 'Add money';
require __DIR__ . '/includes/header.php';
?>

<div class="page-title">Add money</div>
<p class="page-sub">Secure payment via ICICI Bank (test mode).</p>

<?php if ($pay): ?>
  <!-- step 2: continue to the ICICI hosted page (GET redirect with tranCtx) -->
  <div class="card">
    <div class="card-title">Confirm &amp; pay</div>
    <div class="kv"><span class="k">Amount</span><span class="v"><?= money($pay['amount']) ?></span></div>
    <div class="kv"><span class="k">Goes to</span><span class="v">MeraGullak wallet</span></div>
    <div class="kv"><span class="k">Reference</span><span class="v mono"><?= e($pay['merchantTxnNo']) ?></span></div>

    <div class="mt20">
      <a class="btn" href="<?= e($pay['payment_url']) ?>"><?= lucide('lock') ?> Pay <?= money($pay['amount']) ?> via ICICI</a>
    </div>
    <p class="field-hint center mt8">You will be redirected to the ICICI test payment page, then back here automatically.</p>
    <script>window.location.replace(<?= json_encode($pay['payment_url']) ?>);</script>
    <div class="card mt14" style="box-shadow:none">
      <div class="card-title">Didn't return automatically?</div>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="verify_order">
        <input type="hidden" name="merchantTxnNo" value="<?= e($pay['merchantTxnNo']) ?>">
        <button class="btn btn-ghost" type="submit"><?= lucide('refresh-cw') ?> I paid — verify now</button>
      </form>
    </div>
    <p class="field-hint center">Or change your mind? <a href="<?= url('add-money.php') ?>" style="color:var(--primary);font-weight:800">Back</a></p>
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

<?php if (!$pay && $pending): ?>
<div class="card">
  <div class="card-title"><?= lucide('history', 'ic-14') ?> Pending bank payments</div>
  <?php foreach ($pending as $p): ?>
    <div class="kv">
      <span class="k mono"><?= e($p['order_id']) ?> · <?= money((float) $p['amount']) ?></span>
      <span class="v">
        <form method="post" style="display:inline">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="verify_order">
          <input type="hidden" name="merchantTxnNo" value="<?= e($p['order_id']) ?>">
          <button class="btn btn-sm" type="submit">Verify</button>
        </form>
      </span>
    </div>
  <?php endforeach; ?>
  <p class="field-hint">Use Verify after paying on the ICICI page if auto-return didn't credit you.</p>
</div>
<?php endif; ?>

<div class="card">
  <div class="card-title"><?= lucide('shield-check', 'ic-14') ?> Payments are powered by ICICI Bank · TEST MODE</div>
  <div class="kv"><span class="k">Test card</span><span class="v">4761 3400 0000 0035</span></div>
  <div class="kv"><span class="k">Expiry / CVV</span><span class="v">12/26 · 123</span></div>
  <div class="kv"><span class="k">Card OTP</span><span class="v">123456</span></div>
  <div class="kv"><span class="k">Test net-banking</span><span class="v">CC Avenue Test Bank</span></div>
  <div class="kv"><span class="k">Test UPI</span><span class="v">test@ybl</span></div>
  <?php if ($user): ?>
  <div class="kv"><span class="k">Wallet</span><span class="v"><?= money(wallet_balance($uid)) ?></span></div>
  <?php else: ?>
  <div class="kv"><span class="k">Wallet</span><span class="v"><a href="<?= url('login.php?next=' . urlencode('add-money.php')) ?>">Log in to view</a></span></div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
