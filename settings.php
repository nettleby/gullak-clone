<?php
require_once __DIR__ . '/includes/bootstrap.php';
$user = require_login();

$pdo = db();
$uid = (int) $user['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'profile') {
        $name  = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        if (mb_strlen($name) < 2 || !preg_match('/^[6-9]\d{9}$/', $phone)) {
            flash_set('error', 'Please enter a valid name and 10-digit mobile number.');
        } else {
            try {
                $pdo->prepare('UPDATE users SET name = ?, phone = ? WHERE id = ?')->execute([$name, $phone, $uid]);
                flash_set('success', 'Profile updated.');
            } catch (PDOException $e) {
                flash_set('error', 'That phone number is already used by another account.');
            }
        }

    } elseif ($action === 'password') {
        $cur  = $_POST['current'] ?? '';
        $new  = $_POST['new'] ?? '';
        $conf = $_POST['confirm'] ?? '';

        $st = $pdo->prepare('SELECT password_hash FROM users WHERE id = ?');
        $st->execute([$uid]);
        $hash = $st->fetchColumn();

        if (!password_verify($cur, $hash)) {
            flash_set('error', 'Current password is wrong.');
        } elseif (strlen($new) < 6) {
            flash_set('error', 'New password must be at least 6 characters.');
        } elseif ($new !== $conf) {
            flash_set('error', 'New passwords do not match.');
        } else {
            $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
                ->execute([password_hash($new, PASSWORD_BCRYPT), $uid]);
            flash_set('success', 'Password changed.');
        }

    } elseif ($action === 'prefs') {
        $email = isset($_POST['notify_email']) ? 1 : 0;
        $sms   = isset($_POST['notify_sms'])   ? 1 : 0;
        $pdo->prepare('UPDATE users SET notify_email = ?, notify_sms = ? WHERE id = ?')
            ->execute([$email, $sms, $uid]);
        flash_set('success', 'Notification preferences saved.');

    } elseif ($action === 'add_account') {
        $holder = trim($_POST['holder_name'] ?? '');
        $accno  = preg_replace('/\s+/', '', $_POST['account_number'] ?? '');
        $ifsc   = strtoupper(trim($_POST['ifsc'] ?? ''));
        $bank   = trim($_POST['bank_name'] ?? '');

        if (mb_strlen($holder) < 2 || !preg_match('/^\d{9,18}$/', $accno)
            || !preg_match('/^[A-Z]{4}0[A-Z0-9]{6}$/', $ifsc) || mb_strlen($bank) < 2) {
            flash_set('error', 'Please check the account details (account no. 9-18 digits, valid IFSC like HDFC0001234).');
        } else {
            $pdo->prepare('INSERT INTO bank_accounts (user_id, holder_name, account_number, ifsc, bank_name)
                           VALUES (?,?,?,?,?)')
                ->execute([$uid, $holder, $accno, $ifsc, $bank]);
            flash_set('success', 'Bank account saved.');
        }

    } elseif ($action === 'delete_account_row') {
        /* never allow deleting a bank account tied to a pending withdrawal */
        $bid = (int) ($_POST['bank_id'] ?? 0);
        $st = $pdo->prepare('SELECT id FROM bank_accounts WHERE id = ? AND user_id = ?');
        $st->execute([$bid, $uid]);
        if (!$st->fetch()) {
            flash_set('error', 'Account not found.');
        } else {
            $st = $pdo->prepare('SELECT COUNT(*) FROM withdrawals WHERE bank_account_id = ? AND status = "pending"');
            $st->execute([$bid]);
            if ((int) $st->fetchColumn() > 0) {
                flash_set('error', 'This account has a pending withdrawal — wait until it settles.');
            } else {
                $pdo->prepare('DELETE FROM bank_accounts WHERE id = ? AND user_id = ?')->execute([$bid, $uid]);
                flash_set('success', 'Bank account removed.');
            }
        }

    } elseif ($action === 'delete_account') {
        $pw   = $_POST['password'] ?? '';
        $word = trim($_POST['confirm_word'] ?? '');

        $st = $pdo->prepare('SELECT password_hash FROM users WHERE id = ?');
        $st->execute([$uid]);
        $hash = $st->fetchColumn();

        if (!password_verify($pw, $hash)) {
            flash_set('error', 'Password is wrong — account not deleted.');
        } elseif ($word !== 'DELETE') {
            flash_set('error', 'Please type DELETE exactly to confirm.');
        } else {
            /* refuse deletion while user still holds metal, wallet money or pending withdrawals (safety) */
            $hold = get_holdings($uid);
            if ($hold['gold']['grams'] > 0 || $hold['silver']['grams'] > 0
                || wallet_balance($uid) > 0
                || (int) $pdo->query('SELECT COUNT(*) FROM withdrawals WHERE user_id = ' . (int) $uid . ' AND status = "pending"')->fetchColumn() > 0) {
                flash_set('error', 'Please sell your metal, empty your wallet and settle withdrawals before deleting your account.');
            } else {
                session_destroy();
                $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$uid]);
                session_start();
                flash_set('success', 'Your account has been deleted. Goodbye!');
                header('Location: ' . url('login.php'));
                exit;
            }
        }
    }
    header('Location: ' . url('settings.php'));
    exit;
}

/* fresh copy (profile may have changed) */
$user = current_user();

$st = $pdo->prepare('SELECT * FROM bank_accounts WHERE user_id = ? ORDER BY id DESC');
$st->execute([$uid]);
$banks = $st->fetchAll();

$active_tab = 'profile';
$page_title  = 'Settings';
require __DIR__ . '/includes/header.php';
?>

<div class="page-title">Settings</div>
<p class="page-sub">Manage your account, security and preferences.</p>

<!-- ============ ACCOUNT ============ -->
<div class="sec-head" id="account"><?= lucide('user-round-pen') ?><h2>Account</h2></div>
<div class="card">
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="profile">

    <label class="field-label" for="name">Full name</label>
    <input class="field" id="name" name="name" required value="<?= e($user['name']) ?>">

    <label class="field-label" for="phone">Mobile number</label>
    <div class="input-wrap">
      <span class="prefix">+91</span>
      <input class="field" id="phone" name="phone" required maxlength="10" inputmode="numeric"
             value="<?= e($user['phone']) ?>">
    </div>

    <label class="field-label" for="email-ro">Email (login ID)</label>
    <input class="field" id="email-ro" value="<?= e($user['email']) ?>" disabled>
    <p class="field-hint">Email identifies your account and can't be changed. Member since <?= e(dt_ist($user['created_at'])) ?>.</p>

    <div class="mt14"><button class="btn" type="submit"><?= lucide('save') ?> Save changes</button></div>
  </form>
</div>

<!-- ============ NOTIFICATIONS ============ -->
<div class="sec-head" id="notifications"><?= lucide('bell') ?><h2>Notifications</h2></div>
<div class="card">
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="prefs">

    <div class="list-item" style="border:0;padding-left:0;padding-right:0">
      <div class="li-body">
        <div class="li-title">Email updates</div>
        <div class="li-sub">Deposits, SIP runs, withdrawals &amp; security alerts</div>
      </div>
      <label class="switch">
        <input type="checkbox" name="notify_email" <?= (int) $user['notify_email'] ? 'checked' : '' ?>>
        <span class="track"></span>
      </label>
    </div>
    <div class="list-item" style="border:0;padding-left:0;padding-right:0">
      <div class="li-body">
        <div class="li-title">SMS updates</div>
        <div class="li-sub">Important account activity on +91 <?= e($user['phone']) ?></div>
      </div>
      <label class="switch">
        <input type="checkbox" name="notify_sms" <?= (int) $user['notify_sms'] ? 'checked' : '' ?>>
        <span class="track"></span>
      </label>
    </div>
    <div class="mt14"><button class="btn btn-ghost" type="submit"><?= lucide('save') ?> Save preferences</button></div>
  </form>
</div>

<!-- ============ BANK ACCOUNTS ============ -->
<div class="sec-head" id="bank"><?= lucide('landmark') ?><h2>Bank accounts</h2></div>
<div class="card">
  <?php if (!$banks): ?>
    <div class="empty" style="padding:20px 10px">
      <div class="ico"><?= lucide('landmark') ?></div>
      <h3>No bank accounts yet</h3>
      <p>Add one below to withdraw money to your bank.</p>
    </div>
  <?php else: ?>
    <?php foreach ($banks as $b): ?>
      <div class="bank-row">
        <span class="b-icon"><?= lucide('landmark') ?></span>
        <div class="li-body">
          <div class="li-title"><?= e($b['bank_name']) ?> ···<?= e(substr($b['account_number'], -4)) ?></div>
          <div class="li-sub"><?= e($b['holder_name']) ?> · IFSC <?= e($b['ifsc']) ?></div>
        </div>
        <form method="post" data-confirm="Remove this bank account?"><?= csrf_field() ?>
          <input type="hidden" name="action" value="delete_account_row">
          <input type="hidden" name="bank_id" value="<?= (int) $b['id'] ?>">
          <button class="btn btn-sm btn-danger-ghost" type="submit" aria-label="Remove account"><?= lucide('trash-2') ?></button>
        </form>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>

  <details style="margin-top:12px">
    <summary class="btn btn-ghost btn-sm" style="display:inline-flex;list-style:none;cursor:pointer"><?= lucide('plus') ?> Add a bank account</summary>
    <form method="post" style="margin-top:12px">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="add_account">

      <label class="field-label" for="holder_name">Account holder</label>
      <input class="field" id="holder_name" name="holder_name" required placeholder="<?= e($user['name']) ?>">

      <label class="field-label" for="account_number">Account number</label>
      <input class="field" id="account_number" name="account_number" required inputmode="numeric"
             placeholder="9 to 18 digits">

      <label class="field-label" for="ifsc">IFSC code</label>
      <input class="field" id="ifsc" name="ifsc" required placeholder="HDFC0001234" maxlength="11"
             style="text-transform:uppercase">

      <label class="field-label" for="bank_name">Bank name</label>
      <input class="field" id="bank_name" name="bank_name" required placeholder="HDFC Bank">

      <div class="mt14">
        <button class="btn" type="submit"><?= lucide('landmark') ?> Save account</button>
      </div>
    </form>
  </details>
</div>

<!-- ============ SECURITY ============ -->
<div class="sec-head" id="security"><?= lucide('shield-check') ?><h2>Security</h2></div>
<div class="card">
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="password">

    <label class="field-label" for="current">Current password</label>
    <div class="pw-wrap">
      <input class="field" type="password" id="current" name="current" required>
      <button class="pw-eye" type="button" data-toggle-pw="current" aria-label="Show password"><?= lucide('eye') ?></button>
    </div>

    <label class="field-label" for="new">New password</label>
    <div class="pw-wrap">
      <input class="field" type="password" id="new" name="new" required minlength="6">
      <button class="pw-eye" type="button" data-toggle-pw="new" aria-label="Show password"><?= lucide('eye') ?></button>
    </div>
    <div class="pw-meter" id="pw-meter" data-for="new"><span></span><span></span><span></span></div>
    <p class="field-hint">At least 6 characters. Use something unique.</p>

    <label class="field-label" for="confirm2">Confirm new password</label>
    <div class="pw-wrap">
      <input class="field" type="password" id="confirm2" name="confirm" required>
      <button class="pw-eye" type="button" data-toggle-pw="confirm2" aria-label="Show password"><?= lucide('eye') ?></button>
    </div>

    <div class="mt14"><button class="btn btn-ghost" type="submit"><?= lucide('key-round') ?> Update password</button></div>
  </form>
</div>

<!-- ============ APP ============ -->
<div class="sec-head"><?= lucide('info') ?><h2>App</h2></div>
<div class="menu">
  <a class="menu-item" href="<?= url('help.php') ?>">
    <span class="mi-icon"><?= lucide('life-buoy') ?></span>
    <span class="mi-body"><span class="mi-title">Help &amp; support</span><span class="mi-sub">FAQs and guides</span></span>
    <span class="mi-end"><?= lucide('chevron-right') ?></span>
  </a>
  <a class="menu-item" href="<?= url('about.php') ?>">
    <span class="mi-icon tone-slate"><?= lucide('info') ?></span>
    <span class="mi-body"><span class="mi-title">About</span><span class="mi-sub">Version 2.0 · what this app is</span></span>
    <span class="mi-end"><?= lucide('chevron-right') ?></span>
  </a>
  <a class="menu-item" href="<?= url('terms.php') ?>">
    <span class="mi-icon tone-slate"><?= lucide('file-text') ?></span>
    <span class="mi-body"><span class="mi-title">Terms &amp; conditions</span></span>
    <span class="mi-end"><?= lucide('chevron-right') ?></span>
  </a>
  <a class="menu-item" href="<?= url('privacy.php') ?>">
    <span class="mi-icon tone-slate"><?= lucide('shield-check') ?></span>
    <span class="mi-body"><span class="mi-title">Privacy policy</span></span>
    <span class="mi-end"><?= lucide('chevron-right') ?></span>
  </a>
</div>

<!-- ============ DANGER ZONE ============ -->
<div class="danger-zone">
  <div class="dz-title"><?= lucide('circle-alert') ?> Delete account</div>
  <p>Permanently deletes your account, holdings history and settings. Your metal and wallet must be empty first. This cannot be undone.</p>
  <details>
    <summary class="btn btn-sm btn-danger" style="display:inline-flex;list-style:none;cursor:pointer">Delete my account</summary>
    <form method="post" style="margin-top:12px" data-confirm="Really delete your account forever?">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="delete_account">

      <label class="field-label" for="del-pw">Your password</label>
      <input class="field" type="password" id="del-pw" name="password" required>

      <label class="field-label" for="del-word">Type <b>DELETE</b> to confirm</label>
      <input class="field" id="del-word" name="confirm_word" required placeholder="DELETE">

      <div class="mt14">
        <button class="btn btn-danger" type="submit"><?= lucide('trash-2') ?> Delete permanently</button>
      </div>
    </form>
  </details>
</div>

<a class="btn btn-danger-ghost" href="<?= url('logout.php') ?>" style="display:flex" data-confirm="Log out of your account?"><?= lucide('log-out') ?> Log out</a>
<?php require __DIR__ . '/includes/footer.php'; ?>
