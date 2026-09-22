<?php
/**
 * cron/sip-runner.php — process every due SIP instalment for ALL users.
 *
 * The app also auto-runs due SIPs whenever their owner logs in, so this
 * cron is only needed if you want instalments to run even when users
 * never open the app.
 *
 * Usage (CLI):
 *     php cron/sip-runner.php
 *
 * Linux crontab (run every hour, e.g. 00:15 IST daily would be
 * "15 0 * * * php /path/to/gullak-clone/cron/sip-runner.php"):
 */
if (PHP_SAPI !== 'cli') {
    exit('CLI only.');
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

date_default_timezone_set('Asia/Kolkata');

$pdo   = db();
$today = date('Y-m-d');

/* find distinct users with due plans */
$st = $pdo->prepare('SELECT DISTINCT user_id FROM sip_plans WHERE status = "active" AND next_run <= ?');
$st->execute([$today]);
$users = $st->fetchAll(PDO::FETCH_COLUMN);

$done = $failed = $skipped = 0;
foreach ($users as $uid) {
    /* run_due_sips flashes to $_SESSION which doesn't exist in CLI — silence it */
    $_SESSION = $_SESSION ?? [];
    $before = count($_SESSION['flash'] ?? []);
    run_due_sips($pdo, (int) $uid);
    $added = count($_SESSION['flash'] ?? []) - $before;
    $done   += max(0, $added);
    unset($_SESSION['flash']);
}

/* summary of today's runs */
$st = $pdo->prepare('SELECT status, COUNT(*) n FROM sip_logs WHERE run_date = ? GROUP BY status');
$st->execute([$today]);
$summary = $st->fetchAll(PDO::FETCH_KEY_PAIR);

printf(
    "[%s] SIP run complete — users: %d, success today: %d, failed today: %d\n",
    date('Y-m-d H:i:s'),
    count($users),
    (int) ($summary['success'] ?? 0),
    (int) ($summary['failed'] ?? 0)
);
