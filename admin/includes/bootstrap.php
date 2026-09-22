<?php
/**
 * Admin bootstrap — separate session namespace from normal users
 * and a completely separate credentials table.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
        'cookie_secure'   => !empty($_SERVER['HTTPS']),
        'name'            => 'MGAADM',
    ]);
}

if (!defined('APP_ROOT')) define('APP_ROOT', dirname(__DIR__, 2));

require_once APP_ROOT . '/config/config.php';
require_once APP_ROOT . '/includes/db.php';
require_once APP_ROOT . '/includes/functions.php';

if (DISPLAY_ENV) { ini_set('display_errors', '1'); error_reporting(E_ALL); }

/* derive base URL (admin sits one level deeper than the app root) */
if (!defined('BASE_URL')) {
    $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
    $dir = preg_replace('~/(admin|api|cron)$~', '', rtrim($dir, '/'));
    define('BASE_URL', ($dir === '/' || $dir === '') ? '' : $dir);
}

function admin_is_logged_in(): bool
{
    return !empty($_SESSION['admin_id']);
}

function admin_current(): ?array
{
    static $a = null;
    if ($a === null && admin_is_logged_in()) {
        $st = db()->prepare('SELECT id, username FROM admins WHERE id = ?');
        $st->execute([(int) $_SESSION['admin_id']]);
        $a = $st->fetch() ?: null;
    }
    return $a;
}

function admin_require_login(): array
{
    if (!admin_is_logged_in() || !admin_current()) {
        // Unified login: single page with User/Admin tabs (admin/login.php is a shim).
        $cur = $_SERVER['REQUEST_URI'] ?? 'admin/index.php';
        // Make next relative to the app root so login.php can validate + redirect back.
        $base = (defined('BASE_URL') ? (string) BASE_URL : '');
        $rel = $cur;
        if ($base !== '' && str_starts_with($rel, $base)) {
            $rel = substr($rel, strlen($base));
        }
        $rel = ltrim($rel, '/');
        if ($rel === '' || str_contains($rel, '..')) $rel = 'admin/index.php';
        header('Location: ' . url('login.php?tab=admin&next=' . urlencode($rel)));
        exit;
    }
    return admin_current();
}

function admin_csrf_field(): string
{
    if (empty($_SESSION['admin_csrf'])) $_SESSION['admin_csrf'] = bin2hex(random_bytes(16));
    return '<input type="hidden" name="csrf" value="' . $_SESSION['admin_csrf'] . '">';
}

function admin_csrf_check(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') return;
    if (!hash_equals($_SESSION['admin_csrf'] ?? '', (string) ($_POST['csrf'] ?? ''))) {
        http_response_code(419);
        exit('Session expired — go back and try again.');
    }
}
