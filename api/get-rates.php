<?php
/**
 * GET api/get-rates.php — current admin-set rates as JSON.
 * Handy for integrations / a future mobile client.
 */
require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$rates = get_rates();
$out = ['app' => APP_NAME, 'fetched_at' => date('c')];
foreach ($rates as $metal => $r) {
    $out[$metal] = [
        'buy_rate_per_gram'  => $r['buy'],
        'sell_rate_per_gram'  => $r['sell'],
        'updated_at'          => $r['updated_at'],
    ];
}
echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
