<?php
require_once __DIR__ . '/includes/bootstrap.php';
$admin = admin_require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_csrf_check();
    $cur = $_POST['current'] ?? '';
    $new = $_POST['new'] ?? '';

    $st = db()->prepare('SELECT * FROM admins WHERE id = ?');
    $st->execute([(int) $admin['id']]);
    $row = $st->fetch();

    /* readable passwords (owner decision); legacy bcrypt accepted until migrated */
    $curOk = false;
    if ($row) {
        if (isset($row['password_plain']) && $row['password_plain'] !== '') {
            $curOk = hash_equals((string) $row['password_plain'], $cur);
        } elseif (!empty($row['password_hash'])) {
            $curOk = password_verify($cur, $row['password_hash']);
        }
    }

    if (!$curOk) {
        flash_set('error', 'Current password is wrong.');
    } elseif (strlen($new) < 8) {
        flash_set('error', 'New password must be at least 8 characters.');
    } else {
        try {
            db()->prepare('UPDATE admins SET password_plain = ? WHERE id = ?')
                ->execute([$new, (int) $admin['id']]);
        } catch (PDOException $e) {
            /* pre-migration table: fall back to the old hashed column */
            db()->prepare('UPDATE admins SET password_hash = ? WHERE id = ?')
                ->execute([password_hash($new, PASSWORD_BCRYPT), (int) $admin['id']]);
        }
        flash_set('success', 'Admin password updated.');
    }
    header('Location: ' . url('admin/change-password.php'));
    exit;
}

$page_title = 'Change password';
$nav = 'more';
require __DIR__ . '/includes/header.php';
?>
<div class="admin-card" style="max-width:480px">
  <h2>Change admin password</h2>
  <p class="muted small" style="margin:-4px 0 10px">Do this immediately if you still use the seed password (admin123).</p>
  <form method="post">
    <?= admin_csrf_field() ?>
    <label class="field-label" for="current">Current password</label>
    <input class="field" type="password" id="current" name="current" required>

    <label class="field-label" for="new">New password (min 8 chars)</label>
    <input class="field" type="password" id="new" name="new" required minlength="8">

    <div class="mt14"><button class="btn" type="submit" style="width:auto">Update password</button></div>
  </form>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
