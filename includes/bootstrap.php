<?php
/**
 * Session bootstrapping + shared requires. Every user-facing page starts with:
 *     require_once __DIR__ . '/includes/bootstrap.php';
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
        'cookie_secure'   => !empty($_SERVER['HTTPS']),
    ]);
}

define('APP_ROOT', dirname(__DIR__));

require_once APP_ROOT . '/config/config.php';

if (DISPLAY_ENV) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
}

require_once APP_ROOT . '/includes/db.php';
require_once APP_ROOT . '/includes/functions.php';
require_once APP_ROOT . '/includes/auth.php';

/**
 * Derive the app's base URL automatically so the app works
 * both in a sub-folder (http://localhost/gullak-clone) and at a
 * domain root (vhost / php -S). Admin pages live one level deeper,
 * so strip that suffix.
 */
if (!defined('BASE_URL')) {
    $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
    $dir = preg_replace('~/(admin|api|cron)$~', '', rtrim($dir, '/'));
    define('BASE_URL', ($dir === '/' || $dir === '') ? '' : $dir);
}

/*
 * Poor-man's cron: run any SIP instalments that became due for the
 * logged-in user. Cheap indexed query; never lets errors break the page.
 */
if (is_logged_in()) {
    try {
        run_due_sips(db(), (int) current_user_id());
    } catch (Throwable $e) {
        error_log('SIP runner error: ' . $e->getMessage());
    }
}
