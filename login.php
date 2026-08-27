<?php
define('FARM_APP', true);
if (file_exists(__DIR__ . '/config.php')) require __DIR__ . '/config.php';
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/path.php';
require_once __DIR__ . '/lib/icons.php';

auth_start_session();

if (!auth_is_setup_complete()) {
    header('Location: ' . app_base_path() . '/setup.php');
    exit;
}
if (auth_is_authenticated()) {
    header('Location: ' . app_base_path() . '/');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sign In — ItsMyFarm.Farm</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@2.1.1/css/pico.green.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/dist/tabler-icons.min.css">
<link rel="stylesheet" href="css/style.css">
<style>body { display: flex; align-items: center; justify-content: center; min-height: 100vh; background: var(--bg); }</style>
</head>
<body>
  <div class="card auth-card">
    <h1 class="auth-title"><?php echo ICON_SHEEP_FACE; ?><?php echo ICON_COW_FACE; ?> ItsMy.Farm</h1>
    <p class="auth-subtitle">Sign in to continue</p>
    <div class="auth-error" id="error-box"></div>
    <form id="login-form">
      <div class="auth-field">
        <label for="username">Username</label>
        <input id="username" name="username" type="text" required autocomplete="username" autofocus>
      </div>
      <div class="auth-field">
        <label for="password">Password</label>
        <input id="password" name="password" type="password" required autocomplete="current-password">
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center" id="submit-btn"><i class="ti ti-login-2"></i> Sign In</button>
    </form>
  </div>

<script>
(function () {
  var APP_BASE = <?php echo json_encode(app_base_path(), JSON_UNESCAPED_SLASHES); ?>;
  var theme = localStorage.getItem('farm-theme');
  if (theme) document.documentElement.setAttribute('data-theme', theme);

  var form = document.getElementById('login-form');
  var errorBox = document.getElementById('error-box');
  var submitBtn = document.getElementById('submit-btn');

  function showError(msg) {
    errorBox.textContent = msg;
    errorBox.style.display = 'block';
  }

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    errorBox.style.display = 'none';

    var username = document.getElementById('username').value.trim();
    var password = document.getElementById('password').value;

    submitBtn.disabled = true;
    submitBtn.textContent = 'Signing in…';

    fetch(APP_BASE + '/api/login', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ username: username, password: password })
    }).then(function (res) {
      return res.json().then(function (data) { return { ok: res.ok, data: data }; });
    }).then(function (result) {
      if (!result.ok) { showError(result.data.error || 'Something went wrong.'); submitBtn.disabled = false; submitBtn.textContent = 'Sign In'; return; }
      window.location.href = APP_BASE + '/';
    }).catch(function () {
      showError('Could not reach the server. Please try again.');
      submitBtn.disabled = false;
      submitBtn.textContent = 'Sign In';
    });
  });
})();
</script>
</body>
</html>
