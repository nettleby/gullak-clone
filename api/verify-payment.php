<?php
/**
 * POST api/verify-payment.php   (JSON body)
 * Razorpay checkout handler posts here. Verifies the signature server-side,
 * credits the wallet exactly once (idempotent by order_id), returns JSON.
 */
require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'msg' => 'POST only']);
    exit;
}
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'msg' => 'Not logged in']);
    exit;
}

$in = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($in)) $in = $_POST;

csrf_check_json($in);

$orderId   = (string) ($in['razorpay_order_id']   ?? '');
$paymentId = (string) ($in['razorpay_payment_id'] ?? '');
$signature = (string) ($in['razorpay_signature']  ?? '');

if (!$orderId || !$paymentId || !$signature) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'msg' => 'Missing payment fields']);
    exit;
}

$pdo = db();
$uid = (int) current_user_id();

/* the order must belong to this user and still be 'created' */
$st = $pdo->prepare('SELECT * FROM payments WHERE order_id = ? AND user_id = ?');
$st->execute([$orderId, $uid]);
$pay = $st->fetch();

if (!$pay) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'msg' => 'Order not found for this account']);
    exit;
}

/* idempotency: already processed? */
if ($pay['status'] === 'paid') {
    echo json_encode(['ok' => true, 'redirect' => url('wallet.php'), 'msg' => 'Already credited']);
    exit;
}

/* signature check — THE critical security gate */
if (!rzp_verify_signature($orderId, $paymentId, $signature)) {
    $pdo->prepare('UPDATE payments SET status = "failed" WHERE id = ?')->execute([$pay['id']]);
    http_response_code(400);
    echo json_encode(['ok' => false, 'msg' => 'Signature verification failed']);
    exit;
}

/* credit wallet inside a transaction, flipping the payment row
   from 'created' to 'paid' — only the first flip credits money */
try {
    $pdo->beginTransaction();

    $st = $pdo->prepare('UPDATE payments SET status = "paid", payment_id = ?, paid_at = NOW()
                          WHERE id = ? AND status = "created"');
    $st->execute([$paymentId, $pay['id']]);
    if ($st->rowCount() !== 1) {
        throw new RuntimeException('Payment already processed.');
    }

    $amount = (float) $pay['amount'];

    $st = $pdo->prepare('SELECT balance FROM wallets WHERE user_id = ? FOR UPDATE');
    $st->execute([$uid]);
    $newBal = round((float) $st->fetchColumn() + $amount, 2);
    $pdo->prepare('UPDATE wallets SET balance = ? WHERE user_id = ?')->execute([$newBal, $uid]);

    ledger($pdo, [
        'user_id' => $uid, 'type' => 'deposit', 'amount' => $amount,
        'wallet_delta' => $amount, 'wallet_after' => $newBal,
        'note' => 'Wallet top-up via Razorpay (' . $orderId . ')',
    ]);

    $pdo->commit();
} catch (Throwable $ex) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Payment verify error: ' . $ex->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Server error while crediting']);
    exit;
}

flash_set('success', money($amount) . ' added to your wallet.');
echo json_encode(['ok' => true, 'redirect' => url('wallet.php')]);
