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
        header('Location: ' . url('login.php?next=' . urlencode(current_path())));
        exit;
    }
    return current_user();
}

/** App-relative path of the current request (for ?next= return addresses). */
function current_path(): string
{
    $rel = ltrim((string) ($_SERVER['REQUEST_URI'] ?? ''), '/');
    $base = ltrim((string) (defined('BASE_URL') ? BASE_URL : ''), '/');
    if ($base !== '' && str_starts_with($rel, $base)) $rel = ltrim(substr($rel, strlen($base)), '/');
    if ($rel === '' || str_contains($rel, '..') || str_contains($rel, '\\')) $rel = 'index.php';
    return $rel;
}

/**
 * Standard guest gate: login card shown in place of gated content.
 * Never redirects — the user keeps exploring; actions still go through
 * require_login() with a ?next= return.
 */
function guest_cta(string $title, string $sub): string
{
    $next = current_path();
    return '<div class="card center" style="padding:28px 18px">'
        . '<div class="empty" style="padding:0 0 6px"><div class="ico">' . lucide('lock') . '</div>'
        . '<h3>' . e($title) . '</h3><p>' . e($sub) . '</p></div>'
        . '<div class="btn-row mt14">'
        . '<a class="btn btn-sm" style="width:100%" href="' . e(url('login.php?next=' . urlencode($next))) . '">'
        . lucide('log-in') . ' Log in</a>'
        . '<a class="btn btn-ghost btn-sm" style="width:100%" href="' . e(url('register.php')) . '">'
        . 'Create account</a>'
        . '</div></div>';
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

/* ---------------- admin sessions ----------------
 * Admins live in a separate table + separate session namespace (MGAADM),
 * started by admin/includes/bootstrap.php, so a browser can be logged in
 * as user + admin at the same time. Each login page touches only its own
 * session — no cross-session swapping anywhere.
 */

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
