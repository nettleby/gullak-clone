<?php
/**
 * api/icici-callback.php
 * ICICI hosted checkout redirects the browser back here after payment.
 * ICICI sends the response as POST form-urlencoded data. Verified against
 * the working reference implementation for this merchant:
 *  - only POST params count for the hash (query string ignored)
 *  - verified hash + responseCode 000/0000 = success (idempotent credit)
 *  - status query is a tiebreaker / manual-verify path, not mandatory
 * No CSRF check: the bank cannot send our token — HMAC is the auth.
 * Works logged-in or not; the owning user resolves from payments row.
 *
 * IMPORTANT: the bank's return is a CROSS-SITE POST, so browsers do not
 * attach our SameSite=Lax session cookie to it. The callback request is
 * therefore usually sessionless even though the user never logged out.
 * Never redirect a sessionless return to login.php — render an inline
 * result page instead. The [View wallet] button is a same-site GET link,
 * so the browser attaches the user's intact session cookie then.
 */
/* The bank's return is a cross-site POST and never carries our session
 * cookie. If it is absent, skip session startup entirely (see bootstrap):
 * starting a fresh session would send Set-Cookie and overwrite the user's
 * real session cookie — logging them out in their own browser. */
if (empty($_COOKIE[(string) (ini_get('session.name') ?: 'PHPSESSID')])) {
    define('SKIP_SESSION', true);
}
require_once __DIR__ . '/../includes/bootstrap.php';

/* POST-first: ICICI posts form-urlencoded data. Query string is ignored
 * for hash purposes but tolerated for txn lookup. */
$post = $_POST;
if (empty($post)) {
    $raw = (string) file_get_contents('php://input');
    $j = json_decode($raw, true);
    if (is_array($j)) $post = $j;
}
$lookup = array_merge($_GET, $post);

$merchantTxnNo = preg_replace('/[^A-Za-z0-9]/', '', (string) ($lookup['merchantTxnNo'] ?? ''));
$bankTxnID = preg_replace('/[^A-Za-z0-9]/', '', (string) ($lookup['txnID'] ?? ($lookup['txnId'] ?? ($lookup['paymentID'] ?? ''))));
$respCode = trim((string) ($lookup['responseCode'] ?? ''));
$respDesc = trim((string) ($lookup['respDescription'] ?? ($lookup['respDesc'] ?? '')));

function icici_cb_is_owner(int $uid): bool
{
    return is_logged_in() && current_user_id() === $uid;
}

/** Logged-in owner fast path: flash + redirect (in-app UX). */
function icici_cb_done(string $msg, bool $ok = true): void
{
    flash_set($ok ? 'success' : 'error', $msg);
    header('Location: ' . url($ok ? 'wallet.php' : 'add-money.php'));
    exit;
}

/**
 * Sessionless return: status message + automatic redirect. The refresh/JS
 * navigation is a same-site GET, so the browser sends the user's (intact)
 * session cookie then. Success → wallet in 3s; errors → add-money in 5s
 * (longer so the reason is readable). A "continue now" link covers no-JS.
 */
function icici_cb_page(bool $ok, string $title, string $msg, string $goUrl, string $goLabel, string $ref = ''): void
{
    http_response_code(200);
    $secs = $ok ? 3 : 5;
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#F59E0B">
<meta http-equiv="refresh" content="<?= $secs ?>;url=<?= e($goUrl) ?>">
<title><?= e($title) ?> · <?= e(APP_NAME) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
</head>
<body>
<div class="app">
<main class="screen">
<div class="auth" style="padding-top:60px">
  <div class="auth-logo">
    <div class="lg" style="<?= $ok ? '' : 'background:linear-gradient(135deg,#F87171,#DC2626);' ?>"><?= lucide($ok ? 'badge-check' : 'circle-alert') ?></div>
    <h1><?= e($title) ?></h1>
    <p><?= e($msg) ?></p>
    <?php if ($ref !== ''): ?><p class="mono">Ref: <?= e($ref) ?></p><?php endif; ?>
  </div>
  <div class="auth-card center">
    <p class="copy-note">Redirecting to <?= $ok ? 'your wallet' : 'Add Money' ?> in <span id="cb-count"><?= $secs ?></span>s…</p>
    <div class="mt14"><a class="btn" href="<?= e($goUrl) ?>"><?= lucide($ok ? 'wallet' : 'arrow-right') ?> <?= e($goLabel) ?> now</a></div>
    <p class="field-hint center mt14">If you are asked to log in, continue — your money is safe in your wallet.</p>
  </div>
</div>
</main>
</div>
<script src="https://unpkg.com/lucide@0.462.0/dist/umd/lucide.min.js" crossorigin="anonymous"></script>
<script>
if (window.lucide) try { lucide.createIcons(); } catch (e) {}
(function () {
  var n = <?= $secs ?>, el = document.getElementById('cb-count'), to = <?= json_encode($goUrl) ?>;
  var t = setInterval(function () {
    n -= 1;
    if (n <= 0) { clearInterval(t); window.location.replace(to); return; }
    if (el) el.textContent = n;
  }, 1000);
})();
</script>
</body>
</html>
    <?php
    exit;
}

function icici_cb_success(PDO $pdo, int $uid, string $merchantTxnNo, string $bankTxnID, ?float $knownAmount = null): void
{
    if ($knownAmount !== null) {
        error_log('ICICI callback replay for ' . $merchantTxnNo . ' (already paid)');
        $msg = money($knownAmount) . ' already added to your wallet.';
    } else {
        $cr = icici_credit_wallet($pdo, $uid, $merchantTxnNo, $bankTxnID);
        if (!$cr['ok']) {
            error_log('ICICI credit failed for ' . $merchantTxnNo . ': ' . ($cr['msg'] ?? 'unknown'));
            icici_cb_error($uid, 'Server error while crediting. Use Verify on Add Money.', $merchantTxnNo);
        }
        error_log('ICICI callback credited ' . $merchantTxnNo . ' uid=' . $uid . ' amount=' . $cr['amount'] . ($cr['already'] ? ' (replay)' : ''));
        $msg = money($cr['amount']) . ($cr['already'] ? ' already' : '') . ' added to your wallet.';
    }
    if (icici_cb_is_owner($uid)) {
        icici_cb_done($msg);
    }
    icici_cb_page(true, 'Payment successful', $msg, url('wallet.php'), 'View wallet', $merchantTxnNo);
}

function icici_cb_error(?int $uid, string $msg, string $ref = ''): void
{
    if ($uid !== null && icici_cb_is_owner($uid)) {
        icici_cb_done($msg, false);
    }
    icici_cb_page(false, 'Payment not completed', $msg, url('add-money.php'), 'Try again', $ref);
}

if ($merchantTxnNo === '') {
    icici_cb_error(null, 'Missing transaction reference from bank.');
}

$pdo = db();
$st = $pdo->prepare('SELECT * FROM payments WHERE order_id = ?');
$st->execute([$merchantTxnNo]);
$pay = $st->fetch();

if (!$pay) {
    error_log('ICICI callback: unknown merchantTxnNo ' . $merchantTxnNo);
    icici_cb_error(null, 'Order not found. If money was deducted, use Verify on Add Money.', $merchantTxnNo);
}

$uid = (int) $pay['user_id'];

if ($pay['status'] === 'paid') {
    // Idempotent replay: never rewrite a paid order.
    icici_cb_success($pdo, $uid, $merchantTxnNo, (string) ($pay['payment_id'] ?? ''), (float) $pay['amount']);
}

/* Merchant ownership check. */
if (isset($post['merchantId']) && $post['merchantId'] !== ICICI_MERCHANT_ID) {
    error_log('ICICI callback: invalid merchantId: ' . ($post['merchantId'] ?? ''));
    icici_cb_error($uid, 'Invalid payment response (merchant mismatch).', $merchantTxnNo);
}

/* Secure hash verification over POST params (reference algorithm). */
$hashOk = !empty($post) && icici_verify_callback_hash($post);
if (!$hashOk) {
    error_log('ICICI callback: hash verify failed for ' . $merchantTxnNo);
    // Unverified payload: do NOT credit from it. Fall through to the
    // server-side status query as a second chance before failing.
} elseif (in_array($respCode, ['000', '0000'], true)) {
    // Verified success from the bank: credit immediately (reference behavior).
    icici_cb_success($pdo, $uid, $merchantTxnNo, $bankTxnID);
} elseif ($respCode !== '') {
    // Verified non-success code: mark failed (paid is handled above, never overwritten).
    $pdo->prepare('UPDATE payments SET status = "failed" WHERE id = ? AND status = "created"')
        ->execute([$pay['id']]);
    icici_cb_error($uid, 'Payment was declined' . ($respDesc !== '' ? ': ' . $respDesc : '') . '. No money was added.', $merchantTxnNo);
}

/* Tiebreaker: server-side status query (also powers the manual Verify button). */
$chk = icici_status_check($merchantTxnNo, $bankTxnID !== '' ? $bankTxnID : (string) ($pay['payment_id'] ?? ''));
if (!$chk['ok']) {
    error_log('ICICI status check failed for ' . $merchantTxnNo . ': ' . ($chk['msg'] ?? 'unknown'));
    icici_cb_error($uid, !$hashOk
        ? 'Payment response could not be verified. If money was deducted, use Verify on Add Money.'
        : 'Could not confirm payment (' . ($chk['msg'] ?? 'bank unreachable') . '). Use Verify on Add Money.', $merchantTxnNo);
}

if ($chk['status'] === 'SUC') {
    $raw = is_array($chk['raw']) ? $chk['raw'] : [];
    $finalTxn = $bankTxnID !== '' ? $bankTxnID : (string) (($raw['txnID'] ?? '') ?: ($raw['paymentID'] ?? ''));
    icici_cb_success($pdo, $uid, $merchantTxnNo, $finalTxn);
}

if ($chk['status'] === 'REJ') {
    $pdo->prepare('UPDATE payments SET status = "failed" WHERE id = ? AND status = "created"')
        ->execute([$pay['id']]);
    icici_cb_error($uid, 'Payment was declined. No money was added.', $merchantTxnNo);
}

icici_cb_error($uid, 'Payment is still pending at the bank. Use Verify on Add Money in a minute.', $merchantTxnNo);
