<?php
// index.php — the app shell. Same markup as the Node version's index.html,
// just with a PHP auth gate on top: no setup yet -> setup.php,
// not signed in -> login.php, otherwise render the app normally.

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
if (!auth_is_authenticated()) {
    header('Location: ' . app_base_path() . '/login.php');
    exit;
}

$settings = db_read('settings');
$modules = (isset($settings['modules']) && is_array($settings['modules']))
    ? $settings['modules']
    : ['sheep' => true, 'cows' => true, 'sucklers' => false];
$sheepEnabled = $modules['sheep'] ?? true;
$cowsEnabled = $modules['cows'] ?? true;
$sucklersEnabled = $modules['sucklers'] ?? false;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ItsMy.Farm</title>
<!-- Pico CSS (green theme) — see README.md for why this loads from a CDN
     rather than being bundled locally: this is the genuine official file,
     not a reproduction of it. Requires an internet connection for the
     *styling* to load; all app functionality works without it. -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@2.1.1/css/pico.green.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/dist/tabler-icons.min.css">
<link rel="stylesheet" href="css/style.css">
</head>
<body>
<div id="app-shell">

  <header id="top-bar">
    <button id="nav-toggle" class="icon-btn" aria-label="Menu"><i class="ti ti-menu-2"></i></button>
    <div class="brand"><?php echo ICON_SHEEP_FACE; ?><?php echo ICON_COW_FACE; ?> <span id="farm-name">ItsMy.Farm/</span></div>
    <div class="top-actions">
      <div class="global-search">
        <input id="global-search-input" type="search" placeholder="Search tag or name…" autocomplete="off">
        <div id="global-search-results" class="search-results hidden"></div>
      </div>
      <button id="theme-toggle" class="icon-btn" aria-label="Toggle dark mode"><i class="ti ti-moon"></i></button>
      <button id="logout-btn" class="icon-btn" aria-label="Log out" title="Log out"><i class="ti ti-logout"></i></button>
    </div>
  </header>

  <nav id="side-nav">
    <a href="#/dashboard" data-route="dashboard"><i class="ti ti-layout-dashboard"></i> Dashboard</a>
    <a href="#/scan" data-route="scan"><i class="ti ti-antenna"></i> Scan EID</a>
    <?php if ($sheepEnabled): ?>
    <div class="nav-section">Sheep</div>
    <a href="#/sheep" data-route="sheep"><?php echo ICON_SHEEP_FACE; ?> Flock</a>
    <a href="#/sheep/new" data-route="sheep-new"><i class="ti ti-plus"></i> Add Sheep</a>
    <?php endif; ?>
    <?php if ($cowsEnabled): ?>
    <div class="nav-section">Dairy Cows</div>
    <a href="#/cows" data-route="cows"><?php echo ICON_COW_FACE; ?> Herd</a>
    <a href="#/cows?status=rearing" data-route="cows-rearing"><?php echo ICON_COW_FACE; ?> Calves</a>
    <a href="#/cows/new" data-route="cows-new"><i class="ti ti-plus"></i> Add Cow</a>
    <a href="#/milk" data-route="milk"><i class="ti ti-droplet"></i> Milk Recording</a>
    <?php endif; ?>
    <?php if ($sucklersEnabled): ?>
    <div class="nav-section">Suckler Cows</div>
    <a href="#/sucklers" data-route="sucklers"><?php echo ICON_COW_FACE; ?> Herd</a>
    <a href="#/sucklers?status=rearing" data-route="sucklers-rearing"><?php echo ICON_COW_FACE; ?> Calves</a>
    <a href="#/sucklers/new" data-route="sucklers-new"><i class="ti ti-plus"></i> Add Suckler Cow</a>
    <?php endif; ?>
    <div class="nav-section">Farm</div>
    <a href="#/bulk-treatment" data-route="bulk-treatment"><i class="ti ti-vaccine"></i> Bulk Treatment</a>
    <a href="#/groups" data-route="groups"><i class="ti ti-users"></i> Groups</a>
    <a href="#/bulk-movement" data-route="bulk-movement"><i class="ti ti-truck"></i> Batch Movement</a>
    <a href="#/feed" data-route="feed"><i class="ti ti-package"></i> Feed</a>
    <a href="#/reports" data-route="reports"><i class="ti ti-clipboard-list"></i> Reports</a>
    <a href="#/data" data-route="data"><i class="ti ti-device-floppy"></i> Backup &amp; Restore</a>
    <a href="#/account" data-route="account"><i class="ti ti-user"></i> Account</a>
  </nav>

  <main id="main-content">
    <div id="view-root"></div>
  </main>

</div>

<div id="toast-container"></div>
<div id="modal-root"></div>

<script>window.APP_BASE = <?php echo json_encode(app_base_path(), JSON_UNESCAPED_SLASHES); ?>;</script>
<script src="js/icons.js"></script>
<script src="js/api.js"></script>
<script src="js/components.js"></script>
<script src="js/views.js"></script>
<script src="js/app.js"></script>
</body>
</html>
