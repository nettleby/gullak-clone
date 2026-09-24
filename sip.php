<?php
require_once __DIR__ . '/includes/bootstrap.php';
$user = current_user();

$pdo = db();
$uid = $user ? (int) $user['id'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = require_login();   // SIP actions need an account — guests log in first (returns here after)
    $uid = (int) $user['id'];
    csrf_check();
    $action = $_POST['action'] ?? '';
    $sid    = (int) ($_POST['sip_id'] ?? 0);

    /* ownership-checked helper */
    $own = function () use ($pdo, $uid, $sid) {
        $st = $pdo->prepare('SELECT * FROM sip_plans WHERE id = ? AND user_id = ?');
        $st->execute([$sid, $uid]);
        return $st->fetch();
    };

    if ($action === 'create') {
        $metal    = ($_POST['metal'] ?? 'gold') === 'silver' ? 'silver' : 'gold';
        $amount   = dec_to_scaled($_POST['amount'] ?? '', 2) / 100;  // exact 2-decimal ₹
        $freq     = in_array($_POST['frequency'] ?? '', ['daily', 'weekly', 'monthly'], true) ? $_POST['frequency'] : 'weekly';
        $startNow = ($_POST['start'] ?? 'tomorrow') === 'today';
        $errors   = [];
        if ($amount < SIP_MIN_INR)      $errors[] = 'SIP instalment must be at least ' . money(SIP_MIN_INR, 0) . '.';
        if ($amount > SIP_MAX_INR)      $errors[] = 'SIP instalment cannot exceed ' . money(SIP_MAX_INR, 0) . '.';
        if (!in_array($freq, ['daily', 'weekly', 'monthly'], true)) $errors[] = 'Invalid frequency.';
        if ($errors) {
            foreach ($errors as $er) flash_set('error', $er);
        } else {
            $first = $startNow ? date('Y-m-d') : sip_next_run($freq);
            $pdo->prepare('INSERT INTO sip_plans (user_id, metal, amount_inr, frequency, next_run)
                           VALUES (?,?,?,?,?)')
                ->execute([$uid, $metal, $amount, $freq, $first]);
            flash_set('success', $startNow
                ? 'SIP created! First instalment runs today — keep your wallet funded.'
                : 'SIP created! First instalment runs ' . date('d M Y', strtotime($first)) . '. Keep your wallet funded.');
        }

    } elseif ($action === 'pause' && ($p = $own())) {
        $pdo->prepare('UPDATE sip_plans SET status = "paused", failed_attempts = 0 WHERE id = ?')->execute([$sid]);
        flash_set('success', 'SIP paused.');

    } elseif ($action === 'resume' && ($p = $own())) {
        $pdo->prepare('UPDATE sip_plans SET status = "active",
                        next_run = CASE WHEN next_run < CURDATE() THEN CURDATE() ELSE next_run END
                       WHERE id = ?')->execute([$sid]);
        flash_set('success', 'SIP resumed.');

    } elseif ($action === 'cancel' && ($p = $own())) {
        $pdo->prepare('UPDATE sip_plans SET status = "cancelled" WHERE id = ?')->execute([$sid]);
        flash_set('success', 'SIP cancelled.');
    }
    header('Location: ' . url('sip.php'));
    exit;
}

/* my plans + per-plan execution stats */
$st = $pdo->prepare('SELECT * FROM sip_plans WHERE user_id = ? ORDER BY id DESC');
$st->execute([$uid]);
$plans = $uid ? $st->fetchAll() : [];

$stats = [];
if ($plans) {
    $ids = array_map(fn($p) => (int) $p['id'], $plans);
    $in  = implode(',', array_fill(0, count($ids), '?'));
    $st  = $pdo->prepare("SELECT sip_id,
                                 COUNT(*)                                        AS runs,
                                 COALESCE(SUM(CASE WHEN status = 'success' THEN amount END), 0) AS total_inr,
                                 COALESCE(SUM(CASE WHEN status = 'success' THEN grams  END), 0) AS total_g
                          FROM sip_logs WHERE sip_id IN ($in) GROUP BY sip_id");
    $st->execute($ids);
    foreach ($st->fetchAll() as $s) $stats[(int) $s['sip_id']] = $s;
}

/* my runs */
$st = $pdo->prepare('SELECT l.*, p.metal FROM sip_logs l JOIN sip_plans p ON p.id = l.sip_id
                      WHERE l.user_id = ? ORDER BY l.id DESC LIMIT 10');
$st->execute([$uid]);
$logs = $uid ? $st->fetchAll() : [];

/* overview numbers */
$active   = array_filter($plans, fn($p) => $p['status'] === 'active');
$totRuns  = 0; $totInr = 0.0;
foreach ($stats as $s) { $totRuns += (int) $s['runs']; $totInr += (float) $s['total_inr']; }
$nextRun  = null;
foreach ($active as $p) if ($nextRun === null || $p['next_run'] < $nextRun) $nextRun = $p['next_run'];

$rates = get_rates();
$active_tab = 'sip';
$page_title  = 'SIP auto-invest';
require __DIR__ . '/includes/header.php';
?>

<div class="page-title">Gullak SIP</div>
<p class="page-sub">Automate your savings — we buy metal for you on schedule.</p>

<?php if (!$user): ?>
<?= guest_cta('Log in to start a SIP', 'Build an auto-invest plan in a minute — daily, weekly or monthly from ₹10.') ?>
<?php endif; ?>

<!-- overview stats -->
<div class="mini-grid">
  <div class="mini">
    <div class="mk"><?= lucide('repeat') ?> Active</div>
    <div class="mv"><?= count($active) ?></div>
    <div class="ms"><?= count($active) === 1 ? 'plan running' : 'plans running' ?></div>
  </div>
  <div class="mini">
    <div class="mk"><?= lucide('banknote') ?> Invested</div>
    <div class="mv"><?= money($totInr, 0) ?></div>
    <div class="ms">via <?= (int) $totRuns ?> instalment<?= $totRuns === 1 ? '' : 's' ?></div>
  </div>
  <div class="mini">
    <div class="mk"><?= lucide('calendar-clock') ?> Next run</div>
    <div class="mv" style="font-size:13px"><?= $nextRun ? date('d M', strtotime($nextRun)) : '—' ?></div>
    <div class="ms"><?= $nextRun ? date('D, d M Y', strtotime($nextRun)) : 'no active SIP' ?></div>
  </div>
</div>

<div class="notice">
  <?= lucide('info', 'ic-16') ?>
  SIP instalments are bought automatically from your <b>wallet balance</b> — keep it funded.
  A plan pauses itself after <?= (int) SIP_MAX_FAILS ?> failed attempts.
</div>

<?php if ($plans): ?>
<div class="sec-head"><?= lucide('list') ?><h2>Your plans</h2></div>
<?php foreach ($plans as $p):
      $s   = $stats[(int) $p['id']] ?? null;
      $runs = (int) ($s['runs'] ?? 0);
      $sInr = (float) ($s['total_inr'] ?? 0);
      $sG   = (float) ($s['total_g'] ?? 0);
?>
<div class="plan">
  <div class="plan-top">
    <div class="li-icon <?= $p['metal'] ?>"><?= lucide($p['metal'] === 'gold' ? 'gem' : 'coins') ?></div>
    <div class="li-body">
      <div class="plan-amount"><?= money($p['amount_inr'], 0) ?> <span class="plan-meta" style="font-size:12px">/ <?= e($p['frequency']) ?></span></div>
      <div class="plan-meta"><?= e(ucfirst($p['metal'])) ?> · started <?= date('d M Y', strtotime($p['created_at'])) ?></div>
    </div>
    <span class="badge <?= $p['status'] === 'active' ? 'badge-success' : ($p['status'] === 'paused' ? 'badge-warning' : 'badge-muted') ?>">
      <?= e($p['status']) ?>
    </span>
  </div>

  <div class="plan-stats">
    <div><div class="ps-k">Instalments</div><div class="ps-v"><?= $runs ?></div></div>
    <div><div class="ps-k">Invested</div><div class="ps-v"><?= money($sInr, 0) ?></div></div>
    <div><div class="ps-k">Bought</div><div class="ps-v"><?= grams_fmt($sG) ?> g</div></div>
  </div>

  <div class="li-sub">
    <?php if ($p['status'] === 'active'): ?>
      Next run <?= date('D, d M Y', strtotime($p['next_run'])) ?>
    <?php elseif ($p['status'] === 'paused'): ?>
      Paused — resume whenever you like
    <?php else: ?>
      Cancelled on <?= date('d M Y', strtotime($p['updated_at'])) ?>
    <?php endif; ?>
    <?php if ((int) $p['failed_attempts'] > 0): ?> · <?= (int) $p['failed_attempts'] ?> failed run<?= (int) $p['failed_attempts'] > 1 ? 's' : '' ?><?= $p['status'] === 'active' ? '' : ' (auto-paused)' ?><?php endif; ?>
  </div>

  <?php if ($p['status'] !== 'cancelled'): ?>
    <div class="mt8" style="display:flex;gap:6px">
      <?php if ($p['status'] === 'active'): ?>
        <form method="post"><?= csrf_field() ?>
          <input type="hidden" name="action" value="pause"><input type="hidden" name="sip_id" value="<?= (int) $p['id'] ?>">
          <button class="btn btn-ghost btn-sm" type="submit"><?= lucide('pause') ?> Pause</button>
        </form>
      <?php else: ?>
        <form method="post"><?= csrf_field() ?>
          <input type="hidden" name="action" value="resume"><input type="hidden" name="sip_id" value="<?= (int) $p['id'] ?>">
          <button class="btn btn-green btn-sm" type="submit"><?= lucide('play') ?> Resume</button>
        </form>
      <?php endif; ?>
      <form method="post" data-confirm="Cancel this SIP plan? Your bought metal stays yours."><?= csrf_field() ?>
        <input type="hidden" name="action" value="cancel"><input type="hidden" name="sip_id" value="<?= (int) $p['id'] ?>">
        <button class="btn btn-danger btn-sm" type="submit"><?= lucide('ban') ?> Cancel</button>
      </form>
    </div>
  <?php endif; ?>
</div>
<?php endforeach; ?>
<?php endif; ?>

<div class="sec-head"><?= lucide('sparkles') ?><h2>Create a new SIP</h2></div>
<div class="card">
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="create">

    <div class="metal-switch">
      <label class="metal-pill <?= 'gold' ?>">
        <input type="radio" name="metal" value="gold" checked style="display:none">
        <?= lucide('gem') ?> Gold <span class="sub"><?= money($rates['gold']['buy'] ?? 0) ?>/g</span>
      </label>
      <label class="metal-pill">
        <input type="radio" name="metal" value="silver" style="display:none">
        <?= lucide('coins') ?> Silver <span class="sub"><?= money($rates['silver']['buy'] ?? 0)?>/g</span>
      </label>
    </div>

    <label class="field-label" for="amount">Instalment amount (₹)</label>
    <div class="input-wrap">
      <span class="prefix">₹</span>
      <input class="field" id="amount" name="amount" type="number" min="<?= SIP_MIN_INR ?>"
             step="10" value="100" inputmode="decimal" required>
    </div>
    <div class="chip-row">
      <button type="button" class="chip" data-fill="100" data-target="#amount">₹100</button>
      <button type="button" class="chip" data-fill="250" data-target="#amount">₹250</button>
      <button type="button" class="chip" data-fill="500" data-target="#amount">₹500</button>
      <button type="button" class="chip" data-fill="1000" data-target="#amount">₹1,000</button>
    </div>
    <p class="field-hint">Per instalment · between <?= money(SIP_MIN_INR, 0) ?> and <?= money(SIP_MAX_INR, 0) ?>.</p>

    <label class="field-label">Frequency</label>
    <div class="seg">
      <label>
        <input type="radio" name="frequency" value="daily">
        <span class="seg-btn"><?= lucide('zap') ?> Daily<small>every day</small></span>
      </label>
      <label>
        <input type="radio" name="frequency" value="weekly" checked>
        <span class="seg-btn"><?= lucide('calendar') ?> Weekly<small>every week</small></span>
      </label>
      <label>
        <input type="radio" name="frequency" value="monthly">
        <span class="seg-btn"><?= lucide('calendar-days') ?> Monthly<small>every month</small></span>
      </label>
    </div>

    <label class="field-label" style="margin-top:14px">First instalment</label>
    <div class="seg">
      <label>
        <input type="radio" name="start" value="tomorrow" checked>
        <span class="seg-btn"><?= lucide('calendar-clock') ?> Next cycle<small><?= e(ucfirst('runs on the next due date')) ?></small></span>
      </label>
      <label>
        <input type="radio" name="start" value="today">
        <span class="seg-btn"><?= lucide('zap') ?> Start today<small>runs on your next visit</small></span>
      </label>
    </div>
    <p class="field-hint"><?php if ($user): ?>Instalments are debited from your wallet (currently <?= money(wallet_balance($uid)) ?>).<?php else: ?>Instalments are debited from your wallet — <a href="<?= url('login.php?next=' . urlencode('sip.php')) ?>">log in</a> to start your first plan.<?php endif; ?></p>

    <div class="mt14">
      <button class="btn" type="submit"><?= lucide('repeat') ?> Start SIP</button>
    </div>
  </form>

  <!-- projection calculator (illustrative) -->
  <div class="projection" id="proj-box">
    <div class="card-title" style="margin-bottom:8px">What could it grow to?</div>
    <div class="proj-grid">
      <div class="proj-cell">
        <div class="pk">You invest</div>
        <div class="pv" id="proj-invested">₹0</div>
      </div>
      <div class="proj-cell">
        <div class="pk">Est. value* (<?= (float) SIP_PROJ_RATE_PCT ?>% p.a.)</div>
        <div class="pv grow" id="proj-value">₹0</div>
      </div>
    </div>
    <label class="field-label" for="proj-years" style="margin:12px 0 0">Time horizon: <b id="proj-years-lbl">5 years</b></label>
    <input type="range" class="slider" id="proj-years" min="1" max="20" step="1" value="5">
    <p class="field-hint">Projection assumes the current instalment amount, compounding monthly. *Illustrative only — metal returns are not guaranteed.</p>
  </div>
</div>

<?php if ($logs): ?>
<div class="sec-head"><?= lucide('history') ?><h2>Recent SIP runs</h2></div>
<div class="card">
  <div class="list">
    <?php foreach ($logs as $l): ?>
      <div class="list-item">
        <div class="li-icon <?= $l['status'] === 'success' ? ($l['metal'] === 'gold' ? 'gold' : 'silver') : 'money-out' ?>">
          <?= lucide($l['status'] === 'success' ? 'check' : 'circle-alert') ?>
        </div>
        <div class="li-body">
          <div class="li-title"><?= e(ucfirst($l['metal'])) ?> SIP · <?= e($l['status']) ?></div>
          <div class="li-sub">
            <?= date('d M Y', strtotime($l['run_date'])) ?>
            <?php if ($l['status'] === 'success'): ?>
              · <?= grams_fmt($l['grams']) ?> g @ <?= money($l['rate']) ?>/g
            <?php else: ?>
              · <?= e($l['reason']) ?>
            <?php endif; ?>
          </div>
        </div>
        <div class="li-right">
          <?php if ($l['status'] === 'success'): ?>
            <div class="li-amt out">−<?= money($l['amount']) ?></div>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
  var amt   = document.getElementById('amount');
  var freq  = document.querySelectorAll('input[name="frequency"]');
  var years = document.getElementById('proj-years');
  var yLbl  = document.getElementById('proj-years-lbl');
  var outInv = document.getElementById('proj-invested');
  var outVal = document.getElementById('proj-value');
  var RATE = <?= (float) SIP_PROJ_RATE_PCT ?> / 100 / 12; // configured in config/config.php (SIP_PROJ_RATE_PCT), monthly compounding

  function inr(n) { return '₹' + Math.round(n).toLocaleString('en-IN'); }
  function monthsOf(f) { return f === 'daily' ? 30 : (f === 'weekly' ? 4.33 : 1); }

  function project() {
    var a = parseFloat(amt.value || '0') * monthsOf(currentFreq());
    var n = parseInt(years.value, 10) * 12;
    var fv = 0;
    for (var i = 0; i < n; i++) fv = (fv + a) * (1 + RATE);
    outInv.textContent = inr(a * n);
    outVal.textContent = inr(fv);
    yLbl.textContent = years.value + (years.value === '1' ? ' year' : ' years');
  }
  function currentFreq() {
    for (var i = 0; i < freq.length; i++) if (freq[i].checked) return freq[i].value;
    return 'weekly';
  }

  amt.addEventListener('input', project);
  years.addEventListener('input', project);
  freq.forEach(function (r) { r.addEventListener('change', project); });
  project();
});
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
