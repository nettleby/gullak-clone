<?php
/**
 * Shared helpers: formatting, flash messages, rates, ledger,
 * buy/sell engines, SIP runner and the ICICI Bank PG calls.
 */

/* ---------------- output & URL helpers ---------------- */

function e(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

function url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

/** Indian digit grouping: 12,34,567.89 */
function inr_format($n, int $dec = 2): string
{
    $neg = $n < 0 ? '-' : '';
    $n   = abs((float) $n);
    $s   = number_format($n, $dec, '.', '');
    [$int, $frac] = array_pad(explode('.', $s), 2, null);
    if (strlen($int) > 3) {
        $last3 = substr($int, -3);
        $rest  = substr($int, 0, -3);
        $int   = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest) . ',' . $last3;
    }
    return $neg . $int . ($frac !== null ? '.' . $frac : '');
}

function money($n, int $dec = 2): string
{
    return '₹' . inr_format($n, $dec);
}

function grams_fmt($g): string
{
    return rtrim(rtrim(number_format((float) $g, 4, '.', ''), '0'), '.');
}

function dt_ist(?string $ts): string
{
    if (!$ts) return '—';
    return date('d M Y, h:i A', strtotime($ts));
}

/* ---------------- flash messages ---------------- */

function flash_set(string $type, string $msg): void
{
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $msg];
}

function flash_render(): string
{
    if (empty($_SESSION['flash'])) return '';
    $html = '';
    foreach ($_SESSION['flash'] as $f) {
        $cls = $f['type'] === 'error' ? 'flash-error' : 'flash-success';
        $html .= '<div class="flash ' . $cls . '">' . e($f['msg']) . '</div>';
    }
    unset($_SESSION['flash']);
    return $html;
}

/* ---------------- rates ---------------- */

/** Both metals' current rates: ['gold'=>['buy'=>..,'sell'=>..,'updated_at'=>..], ...] */
function get_rates(): array
{
    static $rates = null;
    if ($rates === null) {
        $st = db()->query('SELECT * FROM metal_prices');
        $rates = [];
        foreach ($st->fetchAll() as $r) {
            $rates[$r['metal']] = [
                'buy'        => (float) $r['buy_rate'],
                'sell'       => (float) $r['sell_rate'],
                'updated_at' => $r['updated_at'],
                'updated_by' => $r['updated_by'],
            ];
        }
    }
    return $rates;
}

/* ---------------- metals.dev market feed (IBJA) ----------------
 * Quota-first design (free plan = 100 calls/mo):
 *  - exactly 1 API call per sync (latest endpoint, INR per gram)
 *  - auto-sync at most twice daily (RATE_SYNC_TIMES IST) with a 10h
 *    minimum gap, hard-capped at RATE_MAX_CALLS_PER_DAY incl. manual syncs
 *  - no new tables: last-sync state derives from metal_prices.updated_at
 *    and the daily call count from price_history (recorded_by='metals.dev')
 * Field picks: gold = ibja_gold else spot gold; silver = ibja_silver
 * else spot silver (doc sample carries no ibja_silver key).
 */

/** Raw fetch. Returns ['ok'=>true,'gold'=>float,'silver'=>float,'gold_src'=>..,'silver_src'=>..] or ['ok'=>false,'msg'=>..]. */
function metals_fetch_latest(): array
{
    $url = 'https://api.metals.dev/v1/latest?api_key=' . urlencode(METALS_API_KEY)
        . '&currency=INR&unit=g';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Accept: application/json'],
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_CONNECTTIMEOUT => 10,
    ]);
    $res  = curl_exec($ch);
    $err  = curl_error($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($res === false) {
        return ['ok' => false, 'msg' => 'Rate feed unreachable: ' . $err];
    }
    $data = json_decode($res, true);
    if (!is_array($data) || ($data['status'] ?? '') !== 'success' || !is_array($data['metals'] ?? null)) {
        $msg = $data['error_message'] ?? ('Bad rate response (HTTP ' . $code . ')');
        return ['ok' => false, 'msg' => (string) $msg];
    }
    $m = $data['metals'];
    $gold = $m['ibja_gold'] ?? $m['gold'] ?? null;
    $silver = $m['ibja_silver'] ?? $m['silver'] ?? null;
    if (!is_numeric($gold) || (float) $gold <= 0 || !is_numeric($silver) || (float) $silver <= 0) {
        return ['ok' => false, 'msg' => 'Rate feed missing gold/silver values.'];
    }
    return [
        'ok' => true,
        'gold' => (float) $gold, 'silver' => (float) $silver,
        'gold_src' => isset($m['ibja_gold']) ? 'ibja' : 'spot',
        'silver_src' => isset($m['ibja_silver']) ? 'ibja' : 'spot',
    ];
}

/** Spread buy/sell off a market mid (percent, 2-decimal half-up). */
function metals_spread_rates(float $mid, float $buyPct, float $sellPct): array
{
    $buy  = round($mid * (1 + $buyPct / 100), 2);
    $sell = round($mid * (1 - $sellPct / 100), 2);
    if ($sell > $buy) { [$buy, $sell] = [$sell, $buy]; } // degenerate config guard
    return [$buy, $sell];
}

/**
 * Platform spreads (%): admin-editable `settings` rows with config-constant
 * fallback, clamped to 0–25. Never fatal — a missing table/row just yields
 * the compiled defaults (fresh DBs without the migration keep pricing).
 */
function get_spreads(): array
{
    static $sp = null;
    if ($sp !== null) return $sp;
    $sp = [
        'gold_buy' => GOLD_BUY_SPREAD_PCT, 'gold_sell' => GOLD_SELL_SPREAD_PCT,
        'silver_buy' => SILVER_BUY_SPREAD_PCT, 'silver_sell' => SILVER_SELL_SPREAD_PCT,
    ];
    $map = ['gold_buy_pct' => 'gold_buy', 'gold_sell_pct' => 'gold_sell',
            'silver_buy_pct' => 'silver_buy', 'silver_sell_pct' => 'silver_sell'];
    try {
        $rows = db()->query("SELECT setting_key, setting_value FROM settings
                             WHERE setting_key IN ('gold_buy_pct','gold_sell_pct','silver_buy_pct','silver_sell_pct')")
            ->fetchAll(PDO::FETCH_KEY_PAIR);
        foreach ($map as $dbKey => $short) {
            if (!isset($rows[$dbKey])) continue;
            $v = filter_var($rows[$dbKey], FILTER_VALIDATE_FLOAT);
            if ($v === false || $v < 0 || $v > 25) continue;
            $sp[$short] = $v;
        }
    } catch (Throwable $e) {
        error_log('get_spreads fallback to config: ' . $e->getMessage());
    }
    return $sp;
}

/** API syncs used today (auto + manual), for the quota guard.
 * Counts gold rows only — one sync writes gold+silver, so rows would double-count. */
function metals_calls_today(PDO $pdo): int
{
    $st = $pdo->query("SELECT COUNT(*) FROM price_history
                       WHERE recorded_by = 'metals.dev' AND metal = 'gold' AND recorded_at >= CURDATE()");
    return (int) $st->fetchColumn();
}

/** Last successful auto-sync time (null when never). */
function metals_last_sync(PDO $pdo): ?string
{
    $st = $pdo->query("SELECT MAX(updated_at) FROM metal_prices WHERE updated_by = 'metals.dev'");
    $ts = $st->fetchColumn();
    return $ts ? (string) $ts : null;
}

/** True when an auto-sync is due: past a slot with no sync yet, 10h gap, under cap. */
function metals_sync_due(PDO $pdo, ?int $now = null): bool
{
    if (RATE_SOURCE !== 'auto' || !METALS_API_KEY) return false;
    $now = $now ?? time();
    // IST day/slots (server tz is already Asia/Kolkata).
    $today = date('Y-m-d', $now);
    $slots = [];
    foreach ((array) RATE_SYNC_TIMES as $t) {
        $ts = strtotime($today . ' ' . $t);
        if ($ts && $ts <= $now) $slots[] = $ts;
    }
    if (!$slots) return false;                       // before first slot today
    if (metals_calls_today($pdo) >= RATE_MAX_CALLS_PER_DAY) return false;
    $last = metals_last_sync($pdo);
    if ($last === null) return true;                  // never synced
    $lastTs = strtotime($last);
    if ($lastTs && date('Y-m-d', $lastTs) === $today) {
        // already synced today: only if a LATER slot passed with 10h gap
        $later = array_filter($slots, fn($s) => $s > $lastTs);
        if (!$later) return false;
        if ($now - $lastTs < 10 * 3600) return false;
    }
    return true;
}

/**
 * Run one sync: fetch (1 call) → spread → write metal_prices + price_history.
 * $force=true skips the schedule check but NEVER the daily cap.
 * Returns ['ok'=>bool,'msg'=>..,'rates'=>[metal=>[buy,sell]]].
 */
function metals_sync_rates(bool $force = false): array
{
    $pdo = db();
    try {
        if (RATE_SOURCE !== 'auto') {
            return ['ok' => false, 'msg' => 'Rate source is manual — auto-sync disabled.'];
        }
        if (!$force && !metals_sync_due($pdo)) {
            return ['ok' => false, 'msg' => 'No sync due (schedule/quota guard).'];
        }
        if (metals_calls_today($pdo) >= RATE_MAX_CALLS_PER_DAY) {
            return ['ok' => false, 'msg' => 'Daily API quota reached — try again tomorrow.'];
        }
        $feed = metals_fetch_latest();
        if (!$feed['ok']) return $feed;

        $sp = get_spreads();
        [$gBuy, $gSell] = metals_spread_rates($feed['gold'], $sp['gold_buy'], $sp['gold_sell']);
        [$sBuy, $sSell] = metals_spread_rates($feed['silver'], $sp['silver_buy'], $sp['silver_sell']);
        $rates = ['gold' => [$gBuy, $gSell], 'silver' => [$sBuy, $sSell]];

        $pdo->beginTransaction();
        $up = $pdo->prepare('UPDATE metal_prices SET buy_rate = ?, sell_rate = ?, updated_by = "metals.dev" WHERE metal = ?');
        $hi = $pdo->prepare('INSERT INTO price_history (metal, buy_rate, sell_rate, recorded_by) VALUES (?,?,?,"metals.dev")');
        foreach ($rates as $metal => [$buy, $sell]) {
            $up->execute([$buy, $sell, $metal]);
            $hi->execute([$metal, $buy, $sell]);
        }
        $pdo->commit();
        return ['ok' => true,
            'msg' => sprintf('Rates synced (gold %s, silver %s).', $feed['gold_src'], $feed['silver_src']),
            'rates' => ['gold' => ['buy' => $gBuy, 'sell' => $gSell], 'silver' => ['buy' => $sBuy, 'sell' => $sSell]]];
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('metals.dev sync failed: ' . $ex->getMessage());
        return ['ok' => false, 'msg' => 'Sync failed: ' . $ex->getMessage()];
    }
}

/* ---------------- exact decimal money math ----------------
 * Every rupee amount is a 2-decimal quantity, every rate 2 decimals and every
 * gram balance 4 decimals. All trade arithmetic is therefore done on scaled
 * INTEGERS with half-up rounding (banker-style decimal half-up):
 *   - deterministic and auditable (no binary float noise at .xx5 boundaries)
 *   - reproducible exactly in JavaScript, so the on-screen preview always
 *     equals the amount the server credits.
 */

/** Parse a decimal string ('12', '10.505', '-3.2') and round HALF-UP to
 *  $scale decimals. Returns the value scaled by 10^scale ('10.51',2 → 1051). */
function dec_to_scaled(string $s, int $scale): int
{
    if (!preg_match('/^(-?)(\d+)(?:\.(\d*))?$/', trim($s), $m)) return 0;
    $neg = $m[1] === '-';
    $ip  = ltrim($m[2], '0');
    $fp  = $m[3] ?? '';
    if ($ip === '' && rtrim($fp, '0') === '') return 0;          // (negative) zero
    if (strlen($fp) > $scale) {
        $up  = $fp[$scale] >= '5';                               // half-up on magnitude
        $fp  = substr($fp, 0, $scale);
        $num = ltrim(($ip !== '' ? $ip : '0') . $fp, '0') ?: '0';
        if ($up) $num = bcadd($num, '1', 0);                     // carry, string-safe
    } else {
        $num = ltrim(($ip !== '' ? $ip : '0') . str_pad($fp, $scale, '0'), '0') ?: '0';
    }
    if (bccomp($num, (string) PHP_INT_MAX) > 0) return $neg ? PHP_INT_MIN : PHP_INT_MAX;
    return (int) ($neg ? '-' . $num : $num);
}

/** grams credited for ₹$inr at ₹$rate/g — exact: round_half_up(inr/rate, 4). */
function exact_buy_grams(float $inr, float $rate): float
{
    $ii = (int) round($inr * 100);            // paisa (exact for 2-decimal values)
    $ri = (int) round($rate * 100);           // rate in paisa
    if ($ri <= 0) return 0.0;
    /* grams×10^4 = floor((2·ii·10^4 + ri) / (2·ri))  — half-up, all positive */
    $g4 = intdiv(2 * $ii * 10000 + $ri, 2 * $ri);
    return $g4 / 10000;
}

/** rupees received for $grams at ₹$rate/g — exact: round_half_up(grams×rate, 2). */
function exact_sell_amount(float $grams, float $rate): float
{
    $gi = (int) round($grams * 10000);        // grams×10^4 (exact for 4-decimal values)
    $ri = (int) round($rate * 100);           // rate in paisa
    $p  = $gi * $ri;                          // product ×10^6
    if (abs($p) > PHP_INT_MAX - 10000) {      // absurd magnitudes: bcmath fallback
        $paisa = (int) bcdiv(bcadd(bcmul((string) $gi, (string) $ri, 0), '5000', 0), '10000', 0);
    } else {
        $paisa = intdiv($p + 5000, 10000);    // half-up to paisa
    }
    return $paisa / 100;
}


function rate_for(string $metal, string $side): float
{
    $r = get_rates()[$metal] ?? null;
    if (!$r || $r['buy'] <= 0) {
        throw new RuntimeException('Rates not configured. Ask admin to set prices.');
    }
    return $side === 'buy' ? $r['buy'] : $r['sell'];
}

/* ---------------- wallets & holdings ---------------- */

function wallet_balance(int $uid): float
{
    $st = db()->prepare('SELECT balance FROM wallets WHERE user_id = ?');
    $st->execute([$uid]);
    return (float) ($st->fetchColumn() ?: 0);
}

function get_holdings(int $uid): array
{
    $st = db()->prepare('SELECT metal, grams, invested FROM holdings WHERE user_id = ?');
    $st->execute([$uid]);
    $out = ['gold' => ['grams' => 0.0, 'invested' => 0.0], 'silver' => ['grams' => 0.0, 'invested' => 0.0]];
    foreach ($st->fetchAll() as $h) {
        $out[$h['metal']] = ['grams' => (float) $h['grams'], 'invested' => (float) $h['invested']];
    }
    return $out;
}

/** Write one row into the unified ledger (caller owns the transaction). */
function ledger(PDO $pdo, array $row): void
{
    $pdo->prepare(
        'INSERT INTO transactions
            (user_id, type, amount, wallet_delta, wallet_after, metal, grams_delta, grams_after, rate, note)
         VALUES (?,?,?,?,?,?,?,?,?,?)'
    )->execute([
        $row['user_id'], $row['type'], $row['amount'],
        $row['wallet_delta'], $row['wallet_after'],
        $row['metal']     ?? null,
        $row['grams_delta'] ?? null,
        $row['grams_after'] ?? null,
        $row['rate']       ?? null,
        mb_substr($row['note'] ?? '', 0, 250),
    ]);
}

/* ---------------- buy / sell engines ----------------
 * Both engines open their own transaction when none is active,
 * and participate silently in the caller's transaction otherwise
 * (used by the SIP runner).
 */

function execute_buy(PDO $pdo, int $uid, string $metal, float $inr, string $type = 'buy', ?int $sipId = null): array
{
    $opened = false;
    if (!$pdo->inTransaction()) { $pdo->beginTransaction(); $opened = true; }
    try {
        $rate = rate_for($metal, 'buy');           // may throw if not configured
        if ($inr < MIN_BUY_INR)  throw new RuntimeException('Minimum purchase is ' . money(MIN_BUY_INR, 0) . '.');
        if ($inr > 5000000)     throw new RuntimeException('Single purchase limit is ₹50,00,000.');

        $grams = exact_buy_grams($inr, $rate);          // exact decimal math
        if ($grams <= 0) throw new RuntimeException('Amount too small to buy.');

        // lock wallet + holding rows
        $st = $pdo->prepare('SELECT balance FROM wallets WHERE user_id = ? FOR UPDATE');
        $st->execute([$uid]);
        $balance = (float) ($st->fetchColumn() ?: 0);
        if ($balance + 0.001 < $inr) throw new RuntimeException('Insufficient wallet balance. Add money first.');

        $st = $pdo->prepare('SELECT grams, invested FROM holdings WHERE user_id = ? AND metal = ? FOR UPDATE');
        $st->execute([$uid, $metal]);
        $h = $st->fetch();
        if (!$h) {
            $pdo->prepare('INSERT INTO holdings (user_id, metal) VALUES (?,?) ON DUPLICATE KEY UPDATE user_id=user_id')
                ->execute([$uid, $metal]);
            $h = ['grams' => 0, 'invested' => 0];
        }

        $newBal = round($balance - $inr, 2);
        $newG   = round((float) $h['grams'] + $grams, 4);

        $pdo->prepare('UPDATE wallets SET balance = ? WHERE user_id = ?')->execute([$newBal, $uid]);
        $pdo->prepare('UPDATE holdings SET grams = ?, invested = ? WHERE user_id = ? AND metal = ?')
            ->execute([$newG, round((float) $h['invested'] + $inr, 2), $uid, $metal]);

        ledger($pdo, [
            'user_id' => $uid, 'type' => $type, 'amount' => $inr,
            'wallet_delta' => -$inr, 'wallet_after' => $newBal,
            'metal' => $metal, 'grams_delta' => $grams, 'grams_after' => $newG,
            'rate' => $rate,
            'note' => $type === 'sip_buy'
                ? sprintf('SIP auto-buy: %s g %s @ %s/g', grams_fmt($grams), $metal, money($rate))
                : sprintf('Bought %s g %s @ %s/g', grams_fmt($grams), $metal, money($rate)),
        ]);
        if ($sipId !== null) {
            $pdo->prepare('INSERT INTO sip_logs (sip_id, user_id, run_date, status, amount, grams, rate)
                           VALUES (?,?,?, "success", ?, ?, ?)')
                ->execute([$sipId, $uid, date('Y-m-d'), $inr, $grams, $rate]);
        }
        if ($opened) $pdo->commit();
        return ['ok' => true, 'grams' => $grams, 'rate' => $rate, 'inr' => $inr];
    } catch (Throwable $ex) {
        if ($opened && $pdo->inTransaction()) $pdo->rollBack();
        return ['ok' => false, 'msg' => $ex->getMessage()];
    }
}

function execute_sell(PDO $pdo, int $uid, string $metal, float $grams): array
{
    $opened = false;
    if (!$pdo->inTransaction()) { $pdo->beginTransaction(); $opened = true; }
    try {
        $rate = rate_for($metal, 'sell');
        if ($grams < MIN_SELL_GRAMS) throw new RuntimeException('Minimum sale is ' . grams_fmt(MIN_SELL_GRAMS) . ' g.');

        $st = $pdo->prepare('SELECT grams, invested FROM holdings WHERE user_id = ? AND metal = ? FOR UPDATE');
        $st->execute([$uid, $metal]);
        $h = $st->fetch();
        if (!$h || (float) $h['grams'] + 0.00005 < $grams) {
            throw new RuntimeException('You are trying to sell more ' . $metal . ' than you hold.');
        }

        $amount = exact_sell_amount($grams, $rate);     // exact decimal math

        // proportional cost basis for realised P&L tracking
        $oldG = (float) $h['grams'];
        $oldI = (float) $h['invested'];
        $invOut = $oldG > 0 ? round($oldI * ($grams / $oldG), 2) : 0.0;

        $st = $pdo->prepare('SELECT balance FROM wallets WHERE user_id = ? FOR UPDATE');
        $st->execute([$uid]);
        $newBal = round((float) $st->fetchColumn() + $amount, 2);
        $newG   = round($oldG - $grams, 4);

        $pdo->prepare('UPDATE wallets SET balance = ? WHERE user_id = ?')->execute([$newBal, $uid]);
        $pdo->prepare('UPDATE holdings SET grams = ?, invested = ? WHERE user_id = ? AND metal = ?')
            ->execute([$newG, round(max(0, $oldI - $invOut), 2), $uid, $metal]);

        ledger($pdo, [
            'user_id' => $uid, 'type' => 'sell', 'amount' => $amount,
            'wallet_delta' => $amount, 'wallet_after' => $newBal,
            'metal' => $metal, 'grams_delta' => -$grams, 'grams_after' => $newG,
            'rate' => $rate,
            'note' => sprintf('Sold %s g %s @ %s/g', grams_fmt($grams), $metal, money($rate)),
        ]);
        if ($opened) $pdo->commit();
        return ['ok' => true, 'amount' => $amount, 'grams' => $grams, 'rate' => $rate];
    } catch (Throwable $ex) {
        if ($opened && $pdo->inTransaction()) $pdo->rollBack();
        return ['ok' => false, 'msg' => $ex->getMessage()];
    }
}

/* ---------------- SIP engine ---------------- */

function sip_next_run(string $frequency, ?string $from = null): string
{
    $unit = ['daily' => 'day', 'weekly' => 'week', 'monthly' => 'month'][$frequency] ?? 'day';
    $d = new DateTime($from ?? 'today');
    if ($frequency === 'monthly') {
        /* PHP's '+1 month' overflows (Jan 31 → Mar 3), which would make a
         * month-end SIP drift forward forever. Clamp instead: keep the same
         * day-of-month, or the last day of the next month when it is shorter
         * (Jan 31 → Feb 28, Mar 31 → Apr 30, Feb 28 → Mar 28). */
        $anchor = (int) $d->format('j');
        $d->modify('first day of next month');
        $d->setDate((int) $d->format('Y'), (int) $d->format('n'), min($anchor, (int) $d->format('t')));
        return $d->format('Y-m-d');
    }
    return $d->modify('+1 ' . $unit)->format('Y-m-d');
}

/**
 * Runs every due instalment for one user.
 * Called automatically on page load (bootstrap) and by cron/sip-runner.php.
 */
function run_due_sips(PDO $pdo, int $uid): void
{
    $today = date('Y-m-d');
    $st = $pdo->prepare('SELECT id FROM sip_plans WHERE user_id = ? AND status = "active" AND next_run <= ?');
    $st->execute([$uid, $today]);
    $ids = $st->fetchAll(PDO::FETCH_COLUMN);

    foreach ($ids as $sid) {
        $pdo->beginTransaction();
        try {
            $st = $pdo->prepare('SELECT * FROM sip_plans WHERE id = ? FOR UPDATE');
            $st->execute([$sid]);
            $plan = $st->fetch();
            if (!$plan || $plan['status'] !== 'active' || $plan['next_run'] > $today) {
                $pdo->rollBack();  // someone else handled it / paused meanwhile
                continue;
            }
            $amount = (float) $plan['amount_inr'];
            $res = execute_buy($pdo, $uid, $plan['metal'], $amount, 'sip_buy', (int) $sid);

            if ($res['ok']) {
                $pdo->prepare('UPDATE sip_plans SET next_run = ?, failed_attempts = 0 WHERE id = ?')
                    ->execute([sip_next_run($plan['frequency']), $sid]);
                $pdo->commit();
                flash_set('success',
                    'SIP: bought ' . grams_fmt($res['grams']) . ' g ' . $plan['metal']
                    . ' for ' . money($amount));
            } else {
                $fails = (int) $plan['failed_attempts'] + 1;
                $newStatus = $fails >= SIP_MAX_FAILS ? 'paused' : 'active';
                $pdo->prepare('UPDATE sip_plans SET next_run = ?, failed_attempts = ?, status = ? WHERE id = ?')
                    ->execute([sip_next_run($plan['frequency']), $fails, $newStatus, $sid]);
                $pdo->prepare('INSERT INTO sip_logs (sip_id, user_id, run_date, status, reason)
                               VALUES (?,?,?,"failed",?)')
                    ->execute([$sid, $uid, $today, $res['msg']]);
                $pdo->commit();
                if ($newStatus === 'paused') {
                    flash_set('error', 'A SIP was paused after ' . SIP_MAX_FAILS
                        . ' failed runs (reason: ' . $res['msg'] . '). Top up your wallet to resume it.');
                }
            }
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('SIP run failed for plan #' . $sid . ': ' . $ex->getMessage());
        }
    }
}

/* ---------------- ICICI Bank PG (no SDK, cURL JSON only) ----------------
 * Hosted checkout (payType=0):
 *   1. icici_initiate_sale() POSTs JSON + secureHash to initiateSale.
 *   2. On R1000 the user is redirected to redirectURI with tranCtx.
 *   3. ICICI redirects the browser back to api/icici-callback.php.
 *   4. Callback hash is checked, then icici_status_check() is the
 *      final truth before the idempotent wallet credit.
 */

function icici_return_url(): string
{
    if (defined('ICICI_RETURN_URL') && ICICI_RETURN_URL !== '') {
        return (string) ICICI_RETURN_URL;
    }
    $scheme = !empty($_SERVER['HTTPS']) ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $base = defined('BASE_URL') ? (string) BASE_URL : '';
    return $scheme . '://' . $host . $base . '/api/icici-callback.php';
}

function icici_amount_str(float $amountInr): string
{
    return number_format(round($amountInr, 2), 2, '.', '');
}

function icici_txn_date(?int $ts = null): string
{
    return date('YmdHis', $ts ?? time());
}

/** Unique merchantTxnNo (<=20 chars, letters/digits). */
function icici_merchant_txn_no(int $uid): string
{
    return 'MG' . date('ymdHis') . $uid . bin2hex(random_bytes(2));
}

/**
 * Initiation secureHash field order (verified against the working
 * reference implementation for this merchant):
 * addlParam1 + addlParam2 + aggregatorID + amount + currencyCode +
 * customerEmailID + customerMobileNo + customerName + merchantId +
 * merchantTxnNo + payType + returnURL + transactionType + txnDate —
 * HMAC-SHA256 with secret key, lowercase hex.
 */
function icici_hash_initiate(array $f): string
{
    $raw = (string) ($f['addlParam1'] ?? '')
        . (string) ($f['addlParam2'] ?? '')
        . (string) ($f['aggregatorID'] ?? '')
        . (string) $f['amount']
        . (string) $f['currencyCode']
        . (string) $f['customerEmailID']
        . (string) ($f['customerMobileNo'] ?? '')
        . (string) ($f['customerName'] ?? '')
        . (string) $f['merchantId']
        . (string) $f['merchantTxnNo']
        . (string) $f['payType']
        . (string) $f['returnURL']
        . (string) $f['transactionType']
        . (string) $f['txnDate'];
    return hash_hmac('sha256', $raw, ICICI_SECRET_KEY);
}

/**
 * Start a hosted sale. Returns ['ok'=>true,'merchantTxnNo','redirectURI','tranCtx',...]
 * or ['ok'=>false,'msg'=>..]. $user = ['name','email','phone'].
 */
function icici_initiate_sale(float $amountInr, array $user, ?string $txnNo = null): array
{
    if (!ICICI_ENABLED) return ['ok' => false, 'msg' => 'Payments are temporarily disabled.'];
    $amount = icici_amount_str($amountInr);
    if ((float) $amount <= 0) return ['ok' => false, 'msg' => 'Invalid amount.'];

    $uid = (int) ($user['id'] ?? 0);
    $merchantTxnNo = $txnNo ?: icici_merchant_txn_no($uid);
    $returnURL = icici_return_url();
    $txnDate = icici_txn_date();

    $fields = [
        'merchantId'      => ICICI_MERCHANT_ID,
        'aggregatorID'    => ICICI_AGGREGATOR_ID,
        'merchantTxnNo'   => $merchantTxnNo,
        'amount'          => $amount,
        'currencyCode'    => ICICI_CURRENCY,
        'payType'         => ICICI_PAYTYPE,
        'customerEmailID' => $user['email'] ?? 'guest@meragullak.in',
        'customerName'    => mb_substr($user['name'] ?? 'MeraGullak User', 0, 45),
        'customerMobileNo'=> preg_replace('/\D/', '', (string) ($user['phone'] ?? '')),
        'transactionType' => 'SALE',
        'returnURL'       => $returnURL,
        'txnDate'         => $txnDate,
        'addlParam1'      => 'wallet_topup',
        'addlParam2'      => 'uid' . $uid,
    ];
    // UAT reference sends 10-digit mobile with 91 prefix when available
    if ($fields['customerMobileNo'] !== '' && strlen($fields['customerMobileNo']) === 10) {
        $fields['customerMobileNo'] = '91' . $fields['customerMobileNo'];
    }
    $fields['secureHash'] = icici_hash_initiate($fields);

    $ch = curl_init(ICICI_INITIATE_URL);
    $payload = json_encode($fields);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Accept: application/json'],
        CURLOPT_TIMEOUT        => 25,
    ]);
    $res  = curl_exec($ch);
    $err  = curl_error($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if ($res === false) {
        return ['ok' => false, 'msg' => 'Could not reach ICICI gateway: ' . $err];
    }
    $data = json_decode($res, true);
    if (!is_array($data)) {
        return ['ok' => false, 'msg' => 'Bad response from ICICI gateway (HTTP ' . $code . ')'];
    }
    if (($data['responseCode'] ?? '') === 'R1000' && !empty($data['redirectURI']) && !empty($data['tranCtx'])) {
        $redirectURI = (string) $data['redirectURI'];
        $tranCtx = (string) $data['tranCtx'];
        return [
            'ok' => true,
            'merchantTxnNo' => $merchantTxnNo,
            'redirectURI'   => $redirectURI,
            'tranCtx'       => $tranCtx,
            'payment_url'   => $redirectURI . '?tranCtx=' . urlencode($tranCtx),
            'amount'        => $amount,
            'returnURL'     => $returnURL,
        ];
    }
    $msg = $data['responseDescription'] ?? ('ICICI error ' . ($data['responseCode'] ?? ('HTTP ' . $code)));
    return ['ok' => false, 'msg' => $msg . ' — check ICICI credentials in config/config.php'];
}

/**
 * Universal ICICI hash (official doc §2.1): sort top-level keys
 * alphabetically, concatenate values (nested arrays JSON-encoded,
 * null/empty skipped, secureHash excluded), HMAC-SHA256, lowercase hex.
 */
function icici_sorted_hash(array $fields): string
{
    $params = [];
    foreach ($fields as $key => $value) {
        if ($key === 'secureHash' || $key === 'secure_hash') continue;
        if (is_array($value)) {
            $value = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }
        if ($value !== null && $value !== '') {
            $params[$key] = (string) $value;
        }
    }
    ksort($params, SORT_STRING);
    return hash_hmac('sha256', implode('', $params), ICICI_SECRET_KEY);
}

/**
 * Status query hash: same universal method over the exact STATUS payload
 * (aggregatorID included). originalTxnNo = bank txnID when known, else
 * merchantTxnNo.
 */
function icici_hash_status(string $merchantTxnNo, string $originalTxnNo = ''): string
{
    return icici_sorted_hash([
        'merchantId'      => ICICI_MERCHANT_ID,
        'aggregatorID'    => ICICI_AGGREGATOR_ID,
        'merchantTxnNo'   => $merchantTxnNo,
        'originalTxnNo'   => $originalTxnNo !== '' ? $originalTxnNo : $merchantTxnNo,
        'transactionType' => 'STATUS',
    ]);
}

/** Query transaction status. Returns array with at least ['ok','status','raw']. */
function icici_status_check(string $merchantTxnNo, string $txnID = ''): array
{
    $payload = json_encode([
        'merchantId'    => ICICI_MERCHANT_ID,
        'aggregatorID'  => ICICI_AGGREGATOR_ID,
        'merchantTxnNo' => $merchantTxnNo,
        'originalTxnNo' => $txnID !== '' ? $txnID : $merchantTxnNo,
        'transactionType' => 'STATUS',
        'secureHash'    => icici_hash_status($merchantTxnNo, $txnID),
    ]);
    $ch = curl_init(ICICI_COMMAND_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Accept: application/json'],
        CURLOPT_TIMEOUT        => 25,
    ]);
    $res  = curl_exec($ch);
    $err  = curl_error($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if ($res === false) {
        return ['ok' => false, 'msg' => 'Status check unreachable: ' . $err, 'status' => 'UNKNOWN', 'raw' => null];
    }
    $data = json_decode($res, true);
    if (!is_array($data)) {
        return ['ok' => false, 'msg' => 'Bad status response (HTTP ' . $code . ')', 'status' => 'UNKNOWN', 'raw' => $res];
    }
    // Normalise: UAT uses responseCode 000/0000 + status SUC for success.
    $rc = strtoupper((string) ($data['responseCode'] ?? ''));
    $st = strtoupper((string) ($data['status'] ?? ($data['txnStatus'] ?? '')));
    if (in_array($rc, ['000', '0000'], true) || $st === 'SUC' || $st === 'SUCCESS') {
        return ['ok' => true, 'status' => 'SUC', 'raw' => $data];
    }
    if (in_array($st, ['REJ', 'FAILED', 'FAILURE'], true) || in_array($rc, ['039'], true)) {
        return ['ok' => true, 'status' => 'REJ', 'raw' => $data];
    }
    return ['ok' => true, 'status' => $st !== '' ? $st : $rc, 'raw' => $data];
}

/**
 * Verify an ICICI callback/return payload hash (verified against the
 * working reference implementation for this merchant):
 * - ICICI sends the response as POST form-urlencoded data
 * - only POST parameters count (query-string ignored)
 * - parameter names sorted alphabetically, secureHash itself excluded
 * - null/empty values ignored ('0' counts as a value)
 * - values concatenated, HMAC-SHA256, case-insensitive compare.
 */
function icici_verify_callback_hash(array $post): bool
{
    $sent = trim((string) ($post['secureHash'] ?? ($post['secure_hash'] ?? '')));
    if ($sent === '') return false;
    $params = [];
    foreach ($post as $key => $value) {
        if ($key === 'secureHash' || $key === 'secure_hash') continue;
        if (is_array($value)) $value = implode('', $value);
        if ($value !== null && $value !== '') {
            $params[$key] = (string) $value;
        }
    }
    ksort($params, SORT_STRING);
    $raw = implode('', $params);
    if ($raw === '') return false;
    $calc = hash_hmac('sha256', $raw, ICICI_SECRET_KEY);
    return hash_equals(strtolower($calc), strtolower($sent));
}

/**
 * Idempotent wallet credit for a verified ICICI merchantTxnNo.
 * Looks up payments by (order_id, user_id), flips created→paid once.
 * Returns ['ok'=>true,'already'=>bool,'amount'=>float] or ['ok'=>false,'msg'=>..].
 */
/**
 * Build the audit-trail meta for a payments row from a bank payload.
 * $post = return-POST fields (or [] when only a status query exists);
 * $statusRaw = decoded status-query array (or null). All values sanitized
 * and truncated to column widths; empty becomes NULL (columns are nullable).
 */
function icici_payment_meta(array $post, $statusRaw = null): array
{
    $str = function ($v, int $len) {
        $v = trim((string) ($v ?? ''));
        return $v === '' ? null : mb_substr($v, 0, $len);
    };
    $txnTime = preg_replace('/\D/', '', (string) ($post['paymentDateTime'] ?? ''));
    return [
        'payment_mode'     => $str($post['paymentMode'] ?? null, 12),
        'payment_sub_inst' => $str($post['paymentSubInstType'] ?? null, 64),
        'card_network'     => $str($post['cardNetwork'] ?? null, 12),
        'masked_card'      => $str($post['paymentInstId'] ?? ($post['maskedCardNo'] ?? null), 32),
        'bank_resp_code'   => $str($post['responseCode'] ?? null, 8),
        'bank_resp_desc'   => $str($post['respDescription'] ?? ($post['respDesc'] ?? null), 128),
        'bank_txn_time'    => ($txnTime !== '' ? substr($txnTime, 0, 14) : null),
        'raw_callback'     => $post ? json_encode($post, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null,
        'raw_status'       => is_array($statusRaw)
            ? json_encode($statusRaw, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null,
    ];
}

/** Bind the 9 meta values in column order for UPDATE statements. */
function icici_meta_params(array $meta): array
{
    return [
        $meta['payment_mode'] ?? null, $meta['payment_sub_inst'] ?? null,
        $meta['card_network'] ?? null, $meta['masked_card'] ?? null,
        $meta['bank_resp_code'] ?? null, $meta['bank_resp_desc'] ?? null,
        $meta['bank_txn_time'] ?? null, $meta['raw_callback'] ?? null,
        $meta['raw_status'] ?? null,
    ];
}

const ICICI_META_SET = 'payment_mode = ?, payment_sub_inst = ?, card_network = ?,
        masked_card = ?, bank_resp_code = ?, bank_resp_desc = ?, bank_txn_time = ?,
        raw_callback = ?, raw_status = ?';

/**
 * Mark a created order failed, retaining the bank's reason + raw payload.
 * No-op (0 rows) unless still created — failed is terminal, never overwrites paid.
 */
function icici_mark_failed(PDO $pdo, int $payId, array $meta): void
{
    $params = icici_meta_params($meta);
    $params[] = $payId;
    $pdo->prepare('UPDATE payments SET status = "failed", ' . ICICI_META_SET . '
                    WHERE id = ? AND status = "created"')->execute($params);
}

function icici_credit_wallet(PDO $pdo, int $uid, string $merchantTxnNo, string $bankTxnID, array $meta = []): array
{
    $st = $pdo->prepare('SELECT * FROM payments WHERE order_id = ? AND user_id = ?');
    $st->execute([$merchantTxnNo, $uid]);
    $pay = $st->fetch();
    if (!$pay) return ['ok' => false, 'msg' => 'Order not found for this account'];
    if ($pay['status'] === 'paid') {
        return ['ok' => true, 'already' => true, 'amount' => (float) $pay['amount']];
    }
    $opened = false;
    if (!$pdo->inTransaction()) { $pdo->beginTransaction(); $opened = true; }
    try {
        $params = array_merge([$bankTxnID !== '' ? $bankTxnID : null], icici_meta_params($meta), [$pay['id']]);
        $st = $pdo->prepare('UPDATE payments SET status = "paid", payment_id = ?, paid_at = NOW(), '
            . ICICI_META_SET . ' WHERE id = ? AND status = "created"');
        $st->execute($params);
        if ($st->rowCount() !== 1) {
            throw new RuntimeException('Payment already processed.');
        }
        $amount = (float) $pay['amount'];
        $st = $pdo->prepare('SELECT balance FROM wallets WHERE user_id = ? FOR UPDATE');
        $st->execute([$uid]);
        $newBal = round((float) $st->fetchColumn() + $amount, 2);
        $pdo->prepare('UPDATE wallets SET balance = ? WHERE user_id = ?')->execute([$newBal, $uid]);
        ledger($pdo, [
            'user_id' => $uid, 'type' => 'deposit', 'amount' => $amount,
            'wallet_delta' => $amount, 'wallet_after' => $newBal,
            'note' => 'Wallet top-up via ICICI (' . $merchantTxnNo . ')',
        ]);
        if ($opened) $pdo->commit();
        return ['ok' => true, 'already' => false, 'amount' => $amount];
    } catch (Throwable $ex) {
        if ($opened && $pdo->inTransaction()) $pdo->rollBack();
        return ['ok' => false, 'msg' => $ex->getMessage()];
    }
}

/* ---------------- icons (Lucide via CDN) ---------------- */

/**
 * Render a Lucide icon placeholder. lucide.createIcons() (loaded from CDN in
 * the footer) replaces the <i> tag with the real SVG and copies the class.
 * Examples:   lucide('gem')            ->  <i data-lucide="gem"></i>
 *             lucide('wallet', 'ic-lg') ->  <i data-lucide="wallet" class="ic-lg"></i>
 */
function lucide(string $name, string $class = ''): string
{
    $name = strtolower(preg_replace('~[^a-z0-9-]~i', '', $name));
    $c    = trim($class);
    return '<i data-lucide="' . e($name) . '"' . ($c !== '' ? ' class="' . e($c) . '"' : '') . '></i>';
}

/* ---------------- view helpers ---------------- */

function txn_label(string $type): array
{
    return [
        'deposit'           => ['Added money',        'in',  '＋'],
        'buy'               => ['Bought metal',       'in',  '＋'],
        'sell'              => ['Sold metal',          'out', '−'],
        'sip_buy'           => ['SIP instalment',      'in',  '＋'],
        'withdraw_request'  => ['Withdrawal requested','out', '−'],
        'withdraw_refund'   => ['Withdrawal refunded','in',  '＋'],
        'withdraw_paid'     => ['Withdrawal paid',     'out', '−'],
        'admin_credit'      => ['Admin credit',        'in',  '＋'],
        'admin_debit'       => ['Admin adjustment',    'out', '−'],
    ][$type] ?? [$type, 'flat', '•'];
}

function icon_coin(bool $gold = true): string
{
    return $gold
        ? '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="12" cy="12" r="9" fill="#FBBF24"/><circle cx="12" cy="12" r="9" stroke="#D97706" stroke-width="2"/><text x="12" y="16" text-anchor="middle" font-size="11" font-weight="bold" fill="#92600A">₹</text></svg>'
        : '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="12" cy="12" r="9" fill="#E5E7EB"/><circle cx="12" cy="12" r="9" stroke="#94A3B8" stroke-width="2"/><text x="12" y="16" text-anchor="middle" font-size="11" font-weight="bold" fill="#64748B">₹</text></svg>';
}
