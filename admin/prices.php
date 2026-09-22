<?php
require_once __DIR__ . '/includes/bootstrap.php';
$admin = admin_require_login();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_csrf_check();
    $metal = ($_POST['metal'] ?? '') === 'silver' ? 'silver' : 'gold';
    $buy   = dec_to_scaled($_POST['buy_rate'] ?? '', 2) / 100;         // exact 2-decimal ₹
    $sell  = dec_to_scaled($_POST['sell_rate'] ?? '', 2) / 100;

    if ($buy <= 0 || $sell <= 0) {
        flash_set('error', 'Rates must be positive numbers.');
    } elseif ($sell > $buy) {
        flash_set('error', 'Sell rate cannot be higher than buy rate (you would lose money on every trade).');
    } else {
        try {
            $pdo->beginTransaction();
            $pdo->prepare('UPDATE metal_prices SET buy_rate = ?, sell_rate = ?, updated_by = ? WHERE metal = ?')
                ->execute([$buy, $sell, $admin['username'], $metal]);
            $pdo->prepare('INSERT INTO price_history (metal, buy_rate, sell_rate, recorded_by) VALUES (?,?,?,?)')
                ->execute([$metal, $buy, $sell, $admin['username']]);
            $pdo->commit();
            flash_set('success', ucfirst($metal) . ' rates updated to buy ' . money($buy) . '/g, sell ' . money($sell) . '/g.');
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            flash_set('error', 'Update failed: ' . $ex->getMessage());
        }
    }
    header('Location: ' . url('admin/prices.php'));
    exit;
}

$rates = get_rates();

/* chart data: last 60 points per metal */
$labels = $goldBuy = $goldSell = $silvBuy = $silvSell = [];
$st = $pdo->query('SELECT * FROM price_history ORDER BY recorded_at DESC, id DESC LIMIT 120');
foreach ($st->fetchAll() as $r) {
    array_unshift($labels, date('d M H:i', strtotime($r['recorded_at'])));
    if ($r['metal'] === 'gold') {
        array_unshift($goldBuy, (float) $r['buy_rate']);
        array_unshift($goldSell, (float) $r['sell_rate']);
        array_unshift($silvBuy, null); array_unshift($silvSell, null);
    } else {
        array_unshift($silvBuy, (float) $r['buy_rate']);
        array_unshift($silvSell, (float) $r['sell_rate']);
        array_unshift($goldBuy, null); array_unshift($goldSell, null);
    }
}

$st = $pdo->query('SELECT p.*, a.username FROM price_history p
                   LEFT JOIN admins a ON a.username = p.recorded_by
                   ORDER BY p.id DESC LIMIT 15');
$history = $st->fetchAll();

$page_title = 'Prices';
$nav = 'prices';
require __DIR__ . '/includes/header.php';
?>

<div class="admin-card">
  <h2>Set metal rates (₹ per gram)</h2>
  <p class="muted small" style="margin:-6px 0 12px">
    Users buy at the <b>buy rate</b> and sell back at the <b>sell rate</b>.
    The difference is your platform spread. Every change is logged to price history
    and instantly reflected on user dashboards.
  </p>
  <div class="admin-form">
    <?php foreach (['gold', 'silver'] as $m): $r = $rates[$m] ?? ['buy' => 0, 'sell' => 0]; ?>
    <form method="post" class="admin-card" style="box-shadow:none;margin:0;padding:14px;border:1px dashed var(--line)">
      <?= admin_csrf_field() ?>
      <input type="hidden" name="metal" value="<?= $m ?>">
      <h3><?= lucide($m === 'gold' ? 'gem' : 'coins', 'ic-16') ?> <?= $m === 'gold' ? 'Gold 24K' : 'Silver 999' ?></h3>
      <label class="field-label">Buy rate (user pays)</label>
      <input class="field" name="buy_rate" type="number" step="0.01" min="1" required
             value="<?= e($r['buy']) ?>">
      <label class="field-label">Sell rate (user receives)</label>
      <input class="field" name="sell_rate" type="number" step="0.01" min="1" required
             value="<?= e($r['sell']) ?>">
      <div class="mt8 muted small">Spread: <b id="spread-<?= $m ?>"><?= money(($r['buy'] - $r['sell']), 2) ?></b>/g
        (<?= $r['buy'] > 0 ? number_format(($r['buy'] - $r['sell']) / $r['buy'] * 100, 2) : '0' ?>%)</div>
      <div class="mt14"><button class="btn btn-sm" type="submit">Update <?= e($m) ?></button></div>
    </form>
    <?php endforeach; ?>
  </div>
</div>

<div class="admin-card">
  <h2>Rate history</h2>
  <div class="chart-box" style="height:230px"><canvas id="priceChart"></canvas></div>
  <script>
  window.addEventListener('load', function () {
    if (typeof Chart === 'undefined') {
      document.querySelector('.chart-box').innerHTML = '<p class="muted small">Chart.js CDN unreachable.</p>';
      return;
    }
    new Chart(document.getElementById('priceChart'), {
      type: 'line',
      data: {
        labels: <?= json_encode(array_values(array_unique($labels))) ?: '[]' ?>,
        datasets: [
          { label: 'Gold buy',  data: <?= json_encode($goldBuy) ?>,  borderColor: '#D97706', tension: .3, pointRadius: 2 },
          { label: 'Gold sell', data: <?= json_encode($goldSell) ?>, borderColor: '#FBBF24', borderDash: [5,4], tension: .3, pointRadius: 2 },
          { label: 'Silver buy (×10)', data: <?= json_encode(array_map(fn($v) => $v === null ? null : $v * 10, $silvBuy)) ?>, borderColor: '#64748B', tension: .3, pointRadius: 2 }
        ]
      },
      options: {
        maintainAspectRatio: false,
        plugins: { legend: { labels: { boxWidth: 12, font: { family: 'Nunito', size: 11 } } } },
        scales: { y: { ticks: { callback: v => '₹' + v.toLocaleString('en-IN'), font: { size: 10 } } },
                  x: { ticks: { font: { size: 10 }, maxTicksLimit: 8 } } }
      }
    });
  });
  </script>
</div>

<div class="admin-card">
  <h2>Recent changes</h2>
  <div class="table-wrap">
    <table class="tbl">
      <tr><th>Metal</th><th class="num">Buy rate</th><th class="num">Sell rate</th><th>Changed by</th><th>When</th></tr>
      <?php foreach ($history as $h): ?>
      <tr>
        <td><?= lucide($h['metal'] === 'gold' ? 'gem' : 'coins', 'ic-16') ?> <?= $h['metal'] === 'gold' ? 'Gold' : 'Silver' ?></td>
        <td class="num"><?= money($h['buy_rate']) ?></td>
        <td class="num"><?= money($h['sell_rate']) ?></td>
        <td><?= e($h['recorded_by'] ?: 'system') ?></td>
        <td class="muted"><?= e(dt_ist($h['recorded_at'])) ?></td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<?php require __DIR__ . '/includes/footer.php'; ?>
