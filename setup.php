<?php
define('FARM_APP', true);
if (file_exists(__DIR__ . '/config.php')) require __DIR__ . '/config.php';
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/path.php';
require_once __DIR__ . '/lib/icons.php';

auth_start_session();

if (auth_is_setup_complete()) {
    header('Location: ' . app_base_path() . '/login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Set Up ItsMy.Farm</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@2.1.1/css/pico.green.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/dist/tabler-icons.min.css">
<link rel="stylesheet" href="css/style.css">
<style>body { display: flex; align-items: center; justify-content: center; min-height: 100vh; background: var(--bg); }</style>
</head>
<body>
  <div class="card auth-card" style="max-width:420px">
    <h1 class="auth-title"><?php echo ICON_SHEEP_FACE; ?><?php echo ICON_COW_FACE; ?> ItsMy.Farm</h1>
    <p class="auth-subtitle">First time setup — create your login</p>
    <div class="auth-error" id="error-box"></div>
    <form id="setup-form">
      <div class="auth-field">
        <label for="username">Username</label>
        <input id="username" name="username" type="text" required autocomplete="username" autofocus>
      </div>
      <div class="auth-field">
        <label for="password">Password</label>
        <input id="password" name="password" type="password" required autocomplete="new-password" minlength="8">
        <div class="auth-hint">At least 8 characters.</div>
      </div>
      <div class="auth-field">
        <label for="confirm">Confirm Password</label>
        <input id="confirm" name="confirm" type="password" required autocomplete="new-password" minlength="8">
      </div>
      <div class="auth-field">
        <label>Which do you keep? <span style="font-weight:400">(you can change this later in Account)</span></label>
        <label style="display:flex;align-items:center;gap:8px;font-weight:400;margin-top:6px">
          <input type="checkbox" id="module-sheep" checked style="width:auto"> <?php echo ICON_SHEEP_FACE; ?> Sheep
        </label>
        <label style="display:flex;align-items:center;gap:8px;font-weight:400;margin-top:4px">
          <input type="checkbox" id="module-cows" checked style="width:auto"> <?php echo ICON_COW_FACE; ?> Dairy cows / cattle
        </label>
        <label style="display:flex;align-items:center;gap:8px;font-weight:400;margin-top:4px">
          <input type="checkbox" id="module-sucklers" style="width:auto"> <?php echo ICON_COW_FACE; ?> Suckler cows / beef cattle
        </label>
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center" id="submit-btn"><i class="ti ti-user-plus"></i> Create Login &amp; Continue</button>
    </form>
  </div>

<script>
(function () {
  var APP_BASE = <?php echo json_encode(app_base_path(), JSON_UNESCAPED_SLASHES); ?>;
  var theme = localStorage.getItem('farm-theme');
  if (theme) document.documentElement.setAttribute('data-theme', theme);

  var form = document.getElementById('setup-form');
  var errorBox = document.getElementById('error-box');
  var submitBtn = document.getElementById('submit-btn');

  function showError(msg) {
    errorBox.textContent = msg;
    errorBox.style.display = 'block';
  }

  function resetButton() {
    submitBtn.disabled = false;
    submitBtn.innerHTML = '<i class="ti ti-user-plus"></i> Create Login &amp; Continue';
  }

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    errorBox.style.display = 'none';

    var username = document.getElementById('username').value.trim();
    var password = document.getElementById('password').value;
    var confirm = document.getElementById('confirm').value;
    var sheep = document.getElementById('module-sheep').checked;
    var cows = document.getElementById('module-cows').checked;
    var sucklers = document.getElementById('module-sucklers').checked;

    if (password !== confirm) { showError('Passwords do not match.'); return; }
    if (password.length < 8) { showError('Password must be at least 8 characters.'); return; }
    if (!sheep && !cows && !sucklers) { showError('Select at least one — sheep, dairy cows, or suckler cows.'); return; }

    submitBtn.disabled = true;
    submitBtn.textContent = 'Creating…';

    fetch(APP_BASE + '/api/setup', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ username: username, password: password })
    }).then(function (res) {
      return res.json().then(function (data) { return { ok: res.ok, data: data }; });
    }).then(function (result) {
      if (!result.ok) { showError(result.data.error || 'Something went wrong.'); resetButton(); return; }
      // Account created (and the session cookie is now set) — save the
      // module choice as a follow-up call before landing on the dashboard.
      return fetch(APP_BASE + '/api/settings', {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ modules: { sheep: sheep, cows: cows, sucklers: sucklers } })
      });
    }).then(function (res) {
      if (!res) return; // showError already handled above, nothing further to do
      window.location.href = APP_BASE + '/';
    }).catch(function () {
      showError('Could not reach the server. Please try again.');
      resetButton();
    });
  });
})();
</script>
</body>
</html>
