<?php
/**
 * User authentication, session helpers, CSRF protection.
 */

function is_logged_in(): bool
{
    return !empty($_SESSION['user_id']);
}

function current_user_id(): int
{
    return (int) ($_SESSION['user_id'] ?? 0);
}

function current_user(): ?array
{
    static $user = null;
    if ($user === null && is_logged_in()) {
        $st = db()->prepare('SELECT id, name, email, phone, is_active, notify_email, notify_sms, created_at FROM users WHERE id = ?');
        $st->execute([current_user_id()]);
        $user = $st->fetch() ?: null;
        if ($user && !(int) $user['is_active']) {
            $user = null; // disabled mid-session -> treat as logged out
            session_destroy();
        }
    }
    return $user;
}

function require_login(): array
{
    if (!is_logged_in() || !current_user()) {
        // Preserve destination so a genuine-expiry detour returns here after login.
        $rel = ltrim((string) ($_SERVER['REQUEST_URI'] ?? ''), '/');
        $base = ltrim((string) (defined('BASE_URL') ? BASE_URL : ''), '/');
        if ($base !== '' && str_starts_with($rel, $base)) $rel = ltrim(substr($rel, strlen($base)), '/');
        if ($rel === '' || str_contains($rel, '..') || str_contains($rel, '\\')) $rel = 'index.php';
        header('Location: ' . url('login.php?tab=user&next=' . urlencode($rel)));
        exit;
    }
    return current_user();
}

function attempt_login(string $email, string $password): bool
{
    $st = db()->prepare('SELECT id, password_hash, is_active FROM users WHERE email = ?');
    $st->execute([strtolower(trim($email))]);
    $u = $st->fetch();
    if ($u && (int) $u['is_active'] === 1 && password_verify($password, $u['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $u['id'];
        return true;
    }
    return false;
}

/* ---------------- admin auth (unified login support) ----------------
 * Admins live in a separate table + separate session namespace (MGAADM)
 * so a browser can be logged in as user + admin at the same time.
 * The unified login page (login.php) swaps between the two sessions.
 */

if (!defined('ADMIN_SESSION_NAME')) define('ADMIN_SESSION_NAME', 'MGAADM');

function user_session_cookie_opts(): array
{
    return [
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
        'cookie_secure'   => !empty($_SERVER['HTTPS']),
    ];
}

function admin_session_cookie_opts(): array
{
    return [
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
        'cookie_secure'   => !empty($_SERVER['HTTPS']),
    ];
}

/** Switch PHP to $name session (closing current first). No output must precede this. */
function swap_session(string $name, array $opts): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        if (session_name() === $name) return; // already there
        session_write_close();
    }
    // Clear sticky ID from the previous session, otherwise the new session
    // would reuse it and both namespaces would share one storage file.
    session_id('');
    session_name($name);
    // Bind to this namespace's cookie when present (prevents ID bleed).
    if (!empty($_COOKIE[$name]) && preg_match('/^[A-Za-z0-9,-]+$/', (string) $_COOKIE[$name])) {
        session_id((string) $_COOKIE[$name]);
    }
    if (session_status() === PHP_SESSION_NONE) {
        session_start($opts);
    }
}

/** Default user session name (usually PHPSESSID from php.ini). */
function user_session_name(): string
{
    return (string) (ini_get('session.name') ?: 'PHPSESSID');
}

/** Run $fn with the admin (MGAADM) session active, then restore user session.
 * The previous session is restored by its saved ID — never via cookie.
 * (On a first visit there is no cookie yet; cookie-binding the restore
 * would orphan the session holding the CSRF tokens just rendered.) */
function with_admin_session(callable $fn)
{
    $prevName = session_name();
    if ($prevName === '') $prevName = user_session_name();
    $prevId = session_status() === PHP_SESSION_ACTIVE ? (string) session_id() : '';
    if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
    // Open the admin session (cookie-bound: resume it when the browser has it).
    session_id('');
    session_name(ADMIN_SESSION_NAME);
    if (!empty($_COOKIE[ADMIN_SESSION_NAME]) && preg_match('/^[A-Za-z0-9,-]+$/', (string) $_COOKIE[ADMIN_SESSION_NAME])) {
        session_id((string) $_COOKIE[ADMIN_SESSION_NAME]);
    }
    session_start(admin_session_cookie_opts());
    try {
        return $fn();
    } finally {
        session_write_close();
        // Restore the exact previous session.
        session_id('');
        session_name($prevName);
        if ($prevId !== '' && preg_match('/^[A-Za-z0-9,-]+$/', $prevId)) {
            session_id($prevId);
        } elseif (!empty($_COOKIE[$prevName]) && preg_match('/^[A-Za-z0-9,-]+$/', (string) $_COOKIE[$prevName])) {
            session_id((string) $_COOKIE[$prevName]);
        }
        session_start(user_session_cookie_opts());
    }
}

/** Must be called with the ADMIN session active. */
function attempt_admin_login_active(string $username, string $password): bool
{
    $st = db()->prepare('SELECT * FROM admins WHERE username = ?');
    $st->execute([trim($username)]);
    $a = $st->fetch();
    if ($a && password_verify($password, $a['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['admin_id'] = (int) $a['id'];
        return true;
    }
    return false;
}

function admin_csrf_token_active(): string
{
    if (empty($_SESSION['admin_csrf'])) {
        $_SESSION['admin_csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['admin_csrf'];
}

function admin_csrf_check_active(string $sent): bool
{
    return isset($_SESSION['admin_csrf'])
        && is_string($sent) && $sent !== ''
        && hash_equals((string) $_SESSION['admin_csrf'], $sent);
}

/** True if the MGAADM session holds a valid admin (does not disturb user session). */
function unified_admin_is_logged_in(): bool
{
    return (bool) with_admin_session(function () {
        if (empty($_SESSION['admin_id'])) return false;
        $st = db()->prepare('SELECT id FROM admins WHERE id = ?');
        $st->execute([(int) $_SESSION['admin_id']]);
        if (!$st->fetch()) {
            unset($_SESSION['admin_id']);
            return false;
        }
        return true;
    });
}

function unified_admin_csrf_token(): string
{
    return (string) with_admin_session(function () {
        return admin_csrf_token_active();
    });
}

function register_user(string $name, string $email, string $phone, string $password): array
{
    $pdo  = db();
    $errs = [];
    if (mb_strlen(trim($name)) < 2)                      $errs[] = 'Please enter your full name.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))       $errs[] = 'Please enter a valid email address.';
    if (!preg_match('/^[6-9]\d{9}$/', $phone))           $errs[] = 'Please enter a valid 10-digit Indian mobile number.';
    if (strlen($password) < 6)                            $errs[] = 'Password must be at least 6 characters.';

    if (!$errs) {
        $email = strtolower(trim($email));
        try {
            $pdo->beginTransaction();
            $st = $pdo->prepare('INSERT INTO users (name, email, phone, password_hash) VALUES (?,?,?,?)');
            $st->execute([trim($name), $email, $phone, password_hash($password, PASSWORD_BCRYPT)]);
            $uid = (int) $pdo->lastInsertId();

            $pdo->prepare('INSERT INTO wallets (user_id, balance) VALUES (?,0)')->execute([$uid]);
            $ih = $pdo->prepare('INSERT INTO holdings (user_id, metal, grams, invested) VALUES (?,? ,0,0)');
            foreach (['gold', 'silver'] as $m) $ih->execute([$uid, $m]);

            $pdo->commit();
            session_regenerate_id(true);
            $_SESSION['user_id'] = $uid;
            return ['ok' => true];
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            if ($e->getCode() === '23000') {
                $errs[] = (str_contains($e->getMessage(), 'email') ? 'This email' : 'This phone number')
                        . ' is already registered. Try logging in instead.';
            } else {
                $errs[] = 'Something went wrong. Please try again.';
            }
        }
    }
    return ['ok' => false, 'errors' => $errs];
}

/* ---------------- CSRF ---------------- */

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . csrf_token() . '">';
}

function csrf_check(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') return;
    $sent = $_POST['csrf'] ?? '';
    if ($sent === '' && strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
        $json = json_decode((string) file_get_contents('php://input'), true);
        $sent = is_array($json) ? ($json['csrf'] ?? '') : '';
    }
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        http_response_code(419);
        exit('Your session expired. Please go back and try again.');
    }
}

/** CSRF check for endpoints whose JSON body was already parsed by the caller. */
function csrf_check_json(array $in): void
{
    $sent = (string) ($in['csrf'] ?? '');
    if (!hash_equals(csrf_token(), $sent)) {
        http_response_code(419);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'msg' => 'Session expired — reload the page and retry.']);
        exit;
    }
}
