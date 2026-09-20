// Auto-dismiss success/info alerts after a few seconds
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('.alert-success').forEach(function (alertEl) {
    setTimeout(function () {
      alertEl.style.transition = 'opacity 0.4s ease';
      alertEl.style.opacity = '0';
      setTimeout(function () { alertEl.remove(); }, 400);
    }, 4000);
  });

  // Prevent picking an expiry date/time in the past on the donate form
  const expiryInput = document.querySelector('input[name="expiry_time"]');
  if (expiryInput) {
    const now = new Date();
    now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
    expiryInput.min = now.toISOString().slice(0, 16);
  }
});
