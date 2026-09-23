<?php
/**
 * CLI-only rate sync runner (for real cron where available).
 * The app also syncs on page load (poor-man's cron in bootstrap.php),
 * so this is optional redundancy, not a requirement.
 *
 * Linux:   5 10,16 * * * php /path/to/gullak-clone/cron/rates-sync.php
 * Windows: Task Scheduler → twice daily →
 *   C:\xampp\php\php.exe C:\xampp\htdocs\gullak-clone\cron\rates-sync.php
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only.');
}

require_once __DIR__ . '/../includes/bootstrap.php';

if (!metals_sync_due(db())) {
    echo '[' . date('Y-m-d H:i:s') . '] SKIP: no sync due.' . PHP_EOL;
    exit(0);
}
$res = metals_sync_rates();
echo '[' . date('Y-m-d H:i:s') . '] ' . ($res['ok'] ? 'OK: ' : 'SKIP: ') . $res['msg'] . PHP_EOL;
exit($res['ok'] ? 0 : 1);
