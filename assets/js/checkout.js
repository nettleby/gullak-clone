/* Razorpay Checkout — opens the payment modal and posts the
 * result to api/verify-payment.php for server-side signature check. */

function rzpStart(btn) {
  var orderId   = btn.getAttribute('data-order');
  var amount    = parseInt(btn.getAttribute('data-amount'), 10);   // already in paise
  var keyId     = btn.getAttribute('data-key');
  var uname     = btn.getAttribute('data-uname');
  var uemail    = btn.getAttribute('data-uemail');
  var uphone    = btn.getAttribute('data-uphone');
  var csrf      = document.getElementById('csrf-token').value;

  if (typeof Razorpay === 'undefined') {
    alert('Razorpay Checkout could not load. Check your internet connection and try again.');
    return;
  }

  var rzp = new Razorpay({
    key: keyId,
    amount: amount,
    currency: 'INR',
    name: 'MeraGullak',
    description: 'Wallet top-up',
    order_id: orderId,
    theme: { color: '#F59E0B' },
    prefill: { name: uname, email: uemail, contact: uphone },
    notes: { purpose: 'wallet_topup' },
    handler: function (resp) {
      btn.disabled = true;
      btn.textContent = 'Verifying payment…';

      fetch(document.querySelector('meta[name="base-url"]').content + '/api/verify-payment.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          razorpay_order_id:   resp.razorpay_order_id,
          razorpay_payment_id: resp.razorpay_payment_id,
          razorpay_signature:  resp.razorpay_signature,
          csrf: csrf
        })
      })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (d.ok) {
          btn.textContent = 'Success — redirecting…';
          window.location.href = d.redirect;
        } else {
          btn.disabled = false;
          btn.textContent = 'Pay securely';
          alert('Verification failed: ' + (d.msg || 'unknown error'));
        }
      })
      .catch(function () {
        btn.disabled = false;
        btn.textContent = 'Pay securely';
        alert('Could not verify the payment. If money was deducted it will be auto-refunded by Razorpay.');
      });
    },
    modal: {
      ondismiss: function () {
        /* user closed the modal — order stays 'created'; they may retry */
      }
    }
  });
  rzp.open();
}
