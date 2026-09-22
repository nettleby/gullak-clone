/* MeraGullak — shared front-end helpers */

document.addEventListener('DOMContentLoaded', function () {

  /* ---------- Lucide icons (CDN) ---------- */
  if (window.lucide && typeof window.lucide.createIcons === 'function') {
    try { window.lucide.createIcons(); } catch (e) { /* icons are decorative */ }
  }

  /* ---------- confirm-before-submit ---------- */
  document.querySelectorAll('form[data-confirm]').forEach(function (f) {
    f.addEventListener('submit', function (ev) {
      if (!window.confirm(f.getAttribute('data-confirm'))) ev.preventDefault();
    });
  });

  /* ---------- quick-amount chips:  <button class="chip" data-fill="500" data-target="#amount"> ---------- */
  document.querySelectorAll('.chip[data-fill]').forEach(function (chip) {
    chip.addEventListener('click', function () {
      var t = document.querySelector(chip.getAttribute('data-target') || '#amount');
      if (!t) return;
      t.value = chip.getAttribute('data-fill');
      t.dispatchEvent(new Event('input', { bubbles: true }));
      t.focus();
    });
  });

  /* ---------- sell percentage chips: data-fillpct="25" (of max grams) ---------- */
  document.querySelectorAll('.chip[data-fillpct]').forEach(function (chip) {
    chip.addEventListener('click', function () {
      var g = document.getElementById('amt-grams');
      var max = parseFloat((document.getElementById('sell-calc') || {}).getAttribute?.('data-max') || '0');
      if (!g || !max) return;
      var v = max * parseInt(chip.getAttribute('data-fillpct'), 10) / 100;
      g.value = v > 0 ? v.toFixed(4) : '';
      g.dispatchEvent(new Event('input', { bubbles: true }));
    });
  });

  /* ---------- exact decimal calculator (mirrors the server's integer math) ----------
   * The server computes every trade on scaled integers with half-up rounding,
   * so the preview uses the identical formulas and can never drift by a paisa. */
  var HAS_BIGINT = typeof BigInt === 'function';
  var mgCalc = window.mgCalc = {
    scaled: function (str, scale) {          // "10.505",2 → BigInt 1051 (half-up)
      if (!HAS_BIGINT) return null;
      var m = /^(\d+)(?:\.(\d*))?$/.exec(String(str == null ? '' : str).trim());
      if (!m) return null;
      var ip = m[1], fp = m[2] || '';
      if (fp.length > scale) {
        var up = fp[scale] >= '5';
        fp = fp.slice(0, scale);
        return BigInt(ip + fp) + (up ? 1n : 0n);
      }
      return BigInt(ip + (fp + '000000000000').slice(0, scale));
    },
    gramsForAmount: function (amountStr, rateStr) {   // ₹ → grams×10⁴ (BigInt)
      var ii = this.scaled(amountStr, 2), ri = this.scaled(rateStr, 2);
      if (ii === null || ri === null || ri <= 0n) return null;
      return (2n * ii * 10000n + ri) / (2n * ri);     // BigInt division truncates = floor
    },
    paisaForGrams: function (gramsStr, rateStr) {     // grams → paisa (BigInt)
      var gi = this.scaled(gramsStr, 4), ri = this.scaled(rateStr, 2);
      if (gi === null || ri === null) return null;
      return (gi * ri + 5000n) / 10000n;              // half-up to paisa
    },
    inrStringForGrams: function (gramsStr, rateStr) { // grams → amount string for POST
      var p = this.paisaForGrams(gramsStr, rateStr);
      if (p === null) return (parseFloat(gramsStr) * parseFloat(rateStr)).toFixed(2);
      return (Number(p) / 100).toString();
    }
  };

  /* ---------- amount <-> grams calculator on buy/sell pages ---------- */
  var calc = document.getElementById('buy-calc') || document.getElementById('sell-calc');
  if (calc) {
    var mode      = calc.getAttribute('data-mode') || 'inr'; // 'inr' | 'grams'
    var rateStr   = calc.getAttribute('data-rate') || '0';
    var rate      = parseFloat(rateStr) || 0;
    var inInr     = document.getElementById('amt-inr');
    var inG       = document.getElementById('amt-grams');
    var outG      = document.getElementById('out-grams');
    var outI      = document.getElementById('out-inr');
    var errBox    = document.getElementById('calc-error');
    var submitBtn = document.getElementById('calc-submit');
    var balEl     = document.getElementById('calc-balance');
    var balWarn   = document.getElementById('calc-lowbalance');
    var lock      = false;

    function recalc() {
      if (lock) return;
      lock = true;
      var v;
      if (mode === 'inr') {
        v = parseFloat(inInr.value || '0');
        var g = rate > 0 ? (v / rate) : 0;
        if (HAS_BIGINT && inInr.value.trim() !== '' && !isNaN(v)) {
          var g4 = mgCalc.gramsForAmount(inInr.value, rateStr);   // exact integer math
          if (g4 !== null) g = Number(g4) / 10000;
        }
        if (inG) inG.value = v > 0 ? g.toFixed(4) : '';
        if (outG) outG.textContent = g > 0 ? g.toFixed(4) : '0.0000';
        if (outI) outI.textContent = '₹' + (Math.round(v * 100) / 100).toLocaleString('en-IN');
      } else {
        v = parseFloat((inG && inG.value) || '0');
        var ir = (v * rate);
        if (HAS_BIGINT && inG && inG.value.trim() !== '' && !isNaN(v)) {
          var paisa = mgCalc.paisaForGrams(inG.value, rateStr);  // exact integer math
          if (paisa !== null) ir = Number(paisa) / 100;
        }
        if (inInr) inInr.value = v > 0 ? ir.toFixed(2) : '';
        if (outG) outG.textContent = v > 0 ? v.toFixed(4) : '0.0000';
        if (outI) outI.textContent = '₹' + ir.toLocaleString('en-IN', { maximumFractionDigits: 2 });
      }
      if (submitBtn) submitBtn.disabled = !(v > 0);
      if (errBox) errBox.style.display = 'none';
      /* insufficient-balance hint (buy page only) — paisa-exact comparison */
      if (balEl && balWarn) {
        var short = false;
        if (HAS_BIGINT && mode === 'inr') {
          var balP = mgCalc.scaled(balEl.getAttribute('data-balance') || '0', 2);
          var amtP = inInr.value.trim() !== '' ? mgCalc.scaled(inInr.value, 2) : null;
          short = balP !== null && amtP !== null && amtP > balP;
        } else {
          short = (v > parseFloat(balEl.getAttribute('data-balance') || '0') + 0.001);
        }
        balWarn.style.display = short ? '' : 'none';
      }
      lock = false;
    }

    if (inInr) inInr.addEventListener('input', function () { if (mode === 'inr') recalc(); });
    if (inG)   inG.addEventListener('input',   function () { if (mode === 'grams') recalc(); });

    /* ₹ / grams toggle chips */
    document.querySelectorAll('.chip[data-mode]').forEach(function (chip) {
      chip.addEventListener('click', function () {
        document.querySelectorAll('.chip[data-mode]').forEach(function (c) { c.classList.remove('active'); });
        chip.classList.add('active');
        mode = chip.getAttribute('data-mode');
        var wrap = document.getElementById('field-inr');
        var wrapG = document.getElementById('field-grams');
        if (wrap && wrapG) {
          wrap.style.display  = mode === 'inr'  ? '' : 'none';
          wrapG.style.display = mode === 'grams' ? '' : 'none';
        }
        recalc();
      });
    });

    /* range slider (buy page) keeps the number input in sync */
    var slider = document.getElementById('amt-slider');
    if (slider && inInr) {
      slider.addEventListener('input', function () {
        inInr.value = slider.value;
        recalc();
      });
      inInr.addEventListener('input', function () {
        var v = parseFloat(inInr.value || '0');
        if (v >= parseFloat(slider.min) && v <= parseFloat(slider.max)) slider.value = v;
      });
    }

    recalc();
  }

  /* ---------- hide balances (privacy) ---------- */
  var HTOGGLE = 'mg_hide_balances';
  var apply = function (hide) {
    document.body.classList.toggle('hide-balances', !!hide);
  };
  apply(localStorage.getItem(HTOGGLE) === '1');
  document.querySelectorAll('[data-toggle-balance]').forEach(function (el) {
    el.addEventListener('click', function (ev) {
      ev.preventDefault();
      var now = document.body.classList.toggle('hide-balances');
      localStorage.setItem(HTOGGLE, now ? '1' : '0');
    });
  });

  /* ---------- password show/hide toggles ---------- */
  document.querySelectorAll('[data-toggle-pw]').forEach(function (btn) {
    btn.addEventListener('click', function (ev) {
      ev.preventDefault();
      var inp = document.getElementById(btn.getAttribute('data-toggle-pw'));
      if (!inp) return;
      inp.type = inp.type === 'password' ? 'text' : 'password';
      btn.setAttribute('aria-label', inp.type === 'password' ? 'Show password' : 'Hide password');
      var ic = btn.querySelector('[data-lucide], svg');
      if (ic) {
        var want = inp.type === 'password' ? 'eye' : 'eye-off';
        if (ic.tagName === 'I' && ic.getAttribute('data-lucide') !== want) {
          ic.setAttribute('data-lucide', want);
          if (window.lucide) window.lucide.createIcons();
        }
      }
    });
  });

  /* ---------- password strength meter (any .pw-meter[data-for]) ---------- */
  document.querySelectorAll('.pw-meter[data-for]').forEach(function (meter) {
    var inp = document.getElementById(meter.getAttribute('data-for'));
    if (!inp) return;
    function score(v) {
      var s = 0;
      if (v.length >= 6) s++;
      if (v.length >= 10 || /[^a-zA-Z0-9]/.test(v)) s++;
      if (/[A-Z]/.test(v) && /[0-9]/.test(v)) s++;
      return s;
    }
    inp.addEventListener('input', function () {
      meter.classList.remove('s1', 's2', 's3');
      var s = score(inp.value || '');
      if (s > 0) meter.classList.add('s' + s);
    });
  });

  /* ---------- graceful Chart.js fallback (offline dev) ---------- */
  if (document.getElementById('priceChart') && typeof Chart === 'undefined') {
    var box = document.getElementById('priceChart').closest('.chart-box');
    if (box) box.innerHTML = '<p class="muted small center" style="padding-top:70px">Chart unavailable (Chart.js CDN unreachable).</p>';
  }
});
