// automatically logs user out after 5 minutes of inactivity
(function() {
  let timeout;

  const redirectToLogin = () => {
    fetch('/logout.php', { method: 'POST' }).finally(() => {
      window.location.href = '/login.php?reason=timeout';
    });
  };

  const resetTimer = () => {
    clearTimeout(timeout);
    timeout = setTimeout(redirectToLogin, 30 * 60 * 1000); // 5 minutes
  };

  ['load', 'mousemove', 'mousedown', 'click', 'scroll', 'keypress'].forEach(evt => {
    window.addEventListener(evt, resetTimer, true);
  });

  resetTimer();
})();
