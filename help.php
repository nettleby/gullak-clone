<?php
require_once __DIR__ . '/includes/bootstrap.php';
$user = require_login();

$active_tab = 'profile';
$page_title  = 'Help & support';
require __DIR__ . '/includes/header.php';

$faqs = [
  ['life-buoy',      'What is digital gold & silver?',
   'Digital gold and silver let you buy, hold and sell the metals without taking physical delivery. When you buy, an equivalent quantity of 24K gold (999.9 pure) or 999 silver is recorded in your name and stored with the platform. You can sell it back anytime at the prevailing sell rate, and the money lands in your wallet instantly. There is no locker to rent and no purity to double-check — every gram is tracked to four decimal places.'],
  ['badge-indian-rupee', 'How do prices work here?',
   'Rates on this platform are set manually by the team — they do not change on their own. When you buy, you pay the gold or silver "buy rate" per gram, and when you sell you receive the "sell rate", which is slightly lower. The small difference (the spread) covers storage, insurance and platform costs. Any rate change made by the team is logged, and the price chart on the home page shows the full history.'],
  ['gem', 'How do I buy?',
   'First add money to your wallet using ICICI Bank (cards, UPI, net-banking). Then open the Invest tab, choose gold or silver, and enter either a rupee amount (we convert it to grams) or a weight in grams (we convert it to rupees). The metal is credited to your holdings instantly and appears in your portfolio. Purchases start from just ₹10.'],
  ['arrow-up-right', 'How do I sell?',
   'Open the Sell screen from the Invest tab, pick the metal, and enter how many grams you want to sell — or use the 25% / 50% / 75% / Max shortcuts. The proceeds are credited to your wallet immediately at the current sell rate. From there you can withdraw to your bank account whenever you like.'],
  ['repeat', 'How does a SIP work?',
   'A SIP (Systematic Investment Plan) automatically buys a fixed amount of gold or silver every day, week or month from your wallet balance. Create one from the SIP tab, keep your wallet funded, and instalments execute automatically on schedule. If a run fails because your wallet is short, the plan tries again on the next cycle — after 3 straight failures it pauses itself, and you can resume it anytime. Your holdings and returns are always visible in your portfolio.'],
  ['landmark', 'How long do withdrawals take?',
   'A withdrawal request instantly locks the amount from your wallet and goes to the platform team for approval. Once approved, the money is transferred to your bank account, which usually takes 1-2 working days. If a request is rejected, the full amount is refunded to your wallet immediately — nothing is lost.'],
  ['shield-check', 'Is my money and metal safe?',
   'Your wallet is protected by your password, and every money or metal movement is written to a permanent ledger that you can audit in the History tab — nothing can move without leaving a trace. Bank payouts only go to accounts you add and verify yourself. For this educational project the safest habit is to keep balances modest; rotate any API keys you use and change the default admin password.'],
  ['wallet', 'Are there any fees?',
   'The platform charges no separate fee line. The difference between the buy rate and the sell rate (the spread) is effectively the cost of using the service, and it is always visible on the home screen before you transact. There are no charges for holding metal, running SIPs, or keeping money in your wallet.'],
];
?>
<div class="page-title">Help &amp; support</div>
<p class="page-sub">Answers to the questions we hear most often.</p>

<div class="faq">
  <?php foreach ($faqs as [$icon, $q, $a]): ?>
  <details>
    <summary>
      <?= lucide($icon) ?>
      <span><?= e($q) ?></span>
      <span class="chev"><?= lucide('chevron-right') ?></span>
    </summary>
    <div class="faq-body"><?= e($a) ?></div>
  </details>
  <?php endforeach; ?>
</div>

<div class="card">
  <div class="card-title">Still stuck?</div>
  <div class="menu" style="box-shadow:none;margin-bottom:0">
    <a class="menu-item" href="mailto:support@meragullak.example">
      <span class="mi-icon tone-blue"><?= lucide('mail') ?></span>
      <span class="mi-body">
        <span class="mi-title">Email us</span>
        <span class="mi-sub">support@meragullak.example · replies within 1 working day</span>
      </span>
      <span class="mi-end"><?= lucide('chevron-right') ?></span>
    </a>
    <a class="menu-item" href="tel:+919000000000">
      <span class="mi-icon tone-green"><?= lucide('phone') ?></span>
      <span class="mi-body">
        <span class="mi-title">Call support</span>
        <span class="mi-sub">+91 90000 00000 · Mon-Sat, 9am-6pm IST</span>
      </span>
      <span class="mi-end"><?= lucide('chevron-right') ?></span>
    </a>
    <a class="menu-item" href="<?= url('history.php') ?>">
      <span class="mi-icon tone-slate"><?= lucide('receipt-text') ?></span>
      <span class="mi-body">
        <span class="mi-title">Check your history first</span>
        <span class="mi-sub">Most questions are answered by the transaction ledger</span>
      </span>
      <span class="mi-end"><?= lucide('chevron-right') ?></span>
    </a>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
