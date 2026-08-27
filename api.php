<?php
// api.php — front controller for every /api/* request. .htaccess rewrites
// /api/<anything> to this file with the remainder in $_GET['__path'].
// Routes are protected by default; pass true as the 4th arg to route()
// to exempt one (used for /setup, /login, /logout, /setup-status).

define('FARM_APP', true);
if (file_exists(__DIR__ . '/config.php')) require __DIR__ . '/config.php';
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/helpers.php';

auth_start_session();

$method = $_SERVER['REQUEST_METHOD'];
$path = '/' . trim($_GET['__path'] ?? '', '/');

// ---------- parse JSON body once ----------
$body = [];
if (in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
    $rawBody = file_get_contents('php://input');
    if ($rawBody !== '' && $rawBody !== false) {
        $decoded = json_decode($rawBody, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Invalid JSON body']);
            exit;
        }
        if (is_array($decoded)) $body = $decoded;
    }
}

function json_response($status, $data) {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function require_auth() {
    if (!auth_is_authenticated()) {
        json_response(401, ['error' => 'Not authenticated']);
    }
}

// ---------- tiny router ----------
$GLOBALS['__routes'] = [];

function route($method, $pattern, $handler, $public = false) {
    $regex = preg_replace('#:([a-zA-Z_]+)#', '(?P<$1>[^/]+)', $pattern);
    $regex = '#^' . $regex . '$#';
    $GLOBALS['__routes'][] = ['method' => $method, 'regex' => $regex, 'handler' => $handler, 'public' => $public];
}

function dispatch($method, $path) {
    foreach ($GLOBALS['__routes'] as $r) {
        if ($r['method'] !== $method) continue;
        if (preg_match($r['regex'], $path, $m)) {
            $params = [];
            foreach ($m as $k => $v) {
                if (!is_int($k)) $params[$k] = urldecode($v);
            }
            if (!$r['public']) require_auth();
            call_user_func($r['handler'], $params);
            return true;
        }
    }
    return false;
}

// ---------- generic CRUD factory for simple collections ----------
function crud($name, $onCreate = null, $onUpdate = null) {
    route('GET', "/$name", function ($p) use ($name) {
        json_response(200, db_read($name));
    });

    route('GET', "/$name/:id", function ($p) use ($name) {
        foreach (db_read($name) as $item) {
            if (($item['id'] ?? null) === $p['id']) json_response(200, $item);
        }
        json_response(404, ['error' => 'Not found']);
    });

    route('POST', "/$name", function ($p) use ($name, $onCreate) {
        global $body;
        $items = db_read($name);
        $now = date('c');
        $record = array_merge(['id' => db_next_id(substr($name, 0, 3)), 'createdAt' => $now, 'updatedAt' => $now], $body);
        if ($onCreate) $record = $onCreate($record);
        $items[] = $record;
        db_write($name, $items);
        json_response(201, $record);
    });

    route('PUT', "/$name/:id", function ($p) use ($name, $onUpdate) {
        global $body;
        $items = db_read($name);
        foreach ($items as $i => $item) {
            if (($item['id'] ?? null) === $p['id']) {
                $record = array_merge($item, $body, ['id' => $item['id'], 'updatedAt' => date('c')]);
                if ($onUpdate) $record = $onUpdate($record);
                $items[$i] = $record;
                db_write($name, $items);
                json_response(200, $record);
            }
        }
        json_response(404, ['error' => 'Not found']);
    });

    route('DELETE', "/$name/:id", function ($p) use ($name) {
        $items = db_read($name);
        $found = false;
        $filtered = [];
        foreach ($items as $item) {
            if (($item['id'] ?? null) === $p['id']) { $found = true; continue; }
            $filtered[] = $item;
        }
        if (!$found) json_response(404, ['error' => 'Not found']);
        db_write($name, $filtered);
        json_response(200, ['ok' => true]);
    });
}

// ============================================================
// Routes
// ============================================================

// ---------- authentication (public) ----------
route('GET', '/setup-status', function ($p) {
    json_response(200, ['setupComplete' => auth_is_setup_complete()]);
}, true);

route('POST', '/setup', function ($p) {
    global $body;
    if (auth_is_setup_complete()) json_response(403, ['error' => 'Setup has already been completed.']);
    try {
        auth_create_account($body['username'] ?? '', $body['password'] ?? '');
        auth_login_session();
        json_response(201, ['ok' => true]);
    } catch (Exception $e) {
        json_response(400, ['error' => $e->getMessage()]);
    }
}, true);

route('POST', '/login', function ($p) {
    global $body;
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $username = $body['username'] ?? '';
    $password = $body['password'] ?? '';
    $key = $ip . ':' . $username;
    if (auth_is_locked($key)) {
        json_response(429, ['error' => 'Too many failed attempts. Try again in a few minutes.']);
    }
    if (!auth_is_setup_complete()) {
        json_response(400, ['error' => 'Account not set up yet.']);
    }
    if (auth_verify_credentials($username, $password)) {
        auth_record_success($key);
        auth_login_session();
        json_response(200, ['ok' => true]);
    } else {
        auth_record_failure($key);
        json_response(401, ['error' => 'Incorrect username or password.']);
    }
}, true);

route('POST', '/logout', function ($p) {
    auth_logout();
    json_response(200, ['ok' => true]);
}, true);

route('GET', '/account', function ($p) {
    $account = auth_get_account();
    json_response(200, ['username' => $account ? $account['username'] : null]);
});

route('POST', '/account', function ($p) {
    global $body;
    $current = $body['currentPassword'] ?? '';
    $newUsername = $body['newUsername'] ?? null;
    $newPassword = $body['newPassword'] ?? null;
    $confirm = $body['confirmNewPassword'] ?? null;
    if (!auth_verify_password($current)) {
        json_response(401, ['error' => 'Current password is incorrect.']);
    }
    if ($newPassword && $newPassword !== $confirm) {
        json_response(400, ['error' => 'New password and confirmation do not match.']);
    }
    try {
        $username = auth_update_account($newUsername, $newPassword);
        json_response(200, ['ok' => true, 'username' => $username]);
    } catch (Exception $e) {
        json_response(400, ['error' => $e->getMessage()]);
    }
});

// ---------- collections ----------
crud('medicine',
    function ($r) { return h_compute_withdrawal($r); },
    function ($r) { return h_compute_withdrawal($r); }
);
crud('breeding');
crud('weights');

// NOTE: these two must be registered before crud('milk') below — the
// generic GET /milk/:id route it registers would otherwise swallow
// /milk/herd-daily (matching "herd-daily" as an :id), since routes are
// matched in registration order and the first match wins.
route('GET', '/milk/summary/:cowId', function ($p) {
    $milk = array_values(array_filter(db_read('milk'), function ($m) use ($p) {
        return ($m['cowId'] ?? null) === $p['cowId'];
    }));
    json_response(200, h_milk_summary_for_cow($milk));
});
route('GET', '/milk/herd-daily', function ($p) {
    json_response(200, h_herd_daily_totals(db_read('milk')));
});

crud('milk');
crud('milktests');
crud('movements');
crud('feedproducts');
crud('feedpurchases');
crud('feedusage');

// NOTE: registered before the animals block below only for readability —
// no ordering hazard here since these paths (/feed/summary) don't share a
// prefix with any crud()-registered :id route the way /milk/herd-daily
// once did with /milk/:id.
route('GET', '/feed/summary', function ($p) {
    $stats = h_feed_product_stats(db_read('feedproducts'), db_read('feedpurchases'), db_read('feedusage'));
    json_response(200, array_values($stats));
});

// ---------- animals (custom: species-based id prefix, archive, offspring, timeline) ----------
route('GET', '/animals', function ($p) {
    $animals = db_read('animals');
    $species = $_GET['species'] ?? null;
    $status = $_GET['status'] ?? null;
    $sex = $_GET['sex'] ?? null;
    $breed = $_GET['breed'] ?? null;
    $group = $_GET['group'] ?? null;
    $q = $_GET['q'] ?? null;

    if ($species) $animals = array_values(array_filter($animals, function ($a) use ($species) { return ($a['species'] ?? '') === $species; }));
    if ($status) $animals = array_values(array_filter($animals, function ($a) use ($status) { return ($a['status'] ?? '') === $status; }));
    if ($sex) $animals = array_values(array_filter($animals, function ($a) use ($sex) { return ($a['sex'] ?? '') === $sex; }));
    if ($breed) $animals = array_values(array_filter($animals, function ($a) use ($breed) { return strtolower($a['breed'] ?? '') === strtolower($breed); }));
    if ($group) $animals = array_values(array_filter($animals, function ($a) use ($group) { return strtolower($a['group'] ?? '') === strtolower($group); }));
    if ($q) {
        $needle = strtolower($q);
        $animals = array_values(array_filter($animals, function ($a) use ($needle) {
            return strpos(strtolower($a['tagNumber'] ?? ''), $needle) !== false
                || strpos(strtolower($a['name'] ?? ''), $needle) !== false
                || strpos(strtolower($a['eidNumber'] ?? ''), $needle) !== false;
        }));
    }
    json_response(200, $animals);
});

route('GET', '/animals/:id', function ($p) {
    foreach (db_read('animals') as $a) {
        if (($a['id'] ?? null) === $p['id']) json_response(200, $a);
    }
    json_response(404, ['error' => 'Not found']);
});

route('POST', '/animals', function ($p) {
    global $body;
    $animals = db_read('animals');
    $now = date('c');
    $prefix = (($body['species'] ?? '') === 'cow') ? 'cow' : ((($body['species'] ?? '') === 'sucklercow') ? 'suc' : 'shp');
    $record = array_merge(['id' => db_next_id($prefix), 'createdAt' => $now, 'updatedAt' => $now, 'status' => 'active'], $body);
    $animals[] = $record;
    db_write('animals', $animals);
    json_response(201, $record);
});

route('PUT', '/animals/:id', function ($p) {
    global $body;
    $animals = db_read('animals');
    foreach ($animals as $i => $a) {
        if (($a['id'] ?? null) === $p['id']) {
            $record = array_merge($a, $body, ['id' => $a['id'], 'updatedAt' => date('c')]);
            $animals[$i] = $record;
            db_write('animals', $animals);
            json_response(200, $record);
        }
    }
    json_response(404, ['error' => 'Not found']);
});

route('POST', '/animals/:id/archive', function ($p) {
    $animals = db_read('animals');
    foreach ($animals as $i => $a) {
        if (($a['id'] ?? null) === $p['id']) {
            $animals[$i]['status'] = 'archived';
            $animals[$i]['updatedAt'] = date('c');
            db_write('animals', $animals);
            json_response(200, $animals[$i]);
        }
    }
    json_response(404, ['error' => 'Not found']);
});

route('GET', '/animals/:id/offspring', function ($p) {
    $offspring = array_values(array_filter(db_read('animals'), function ($a) use ($p) {
        return ($a['motherId'] ?? null) === $p['id'] || ($a['fatherId'] ?? null) === $p['id'];
    }));
    json_response(200, $offspring);
});

route('GET', '/animals/:id/timeline', function ($p) {
    $animals = db_read('animals');
    $animal = h_find_by_id($animals, $p['id']);
    if (!$animal) json_response(404, ['error' => 'Not found']);
    $data = [
        'animals' => $animals,
        'breeding' => db_read('breeding'),
        'medicine' => db_read('medicine'),
        'weights' => db_read('weights'),
        'milk' => db_read('milk'),
        'milktests' => db_read('milktests'),
        'movements' => db_read('movements'),
        'feedusage' => db_read('feedusage'),
        'feedproducts' => db_read('feedproducts')
    ];
    json_response(200, h_build_timeline($p['id'], $animal, $data));
});

route('GET', '/animals/:id/feed-cost', function ($p) {
    $productStats = h_feed_product_stats(db_read('feedproducts'), db_read('feedpurchases'), db_read('feedusage'));
    json_response(200, h_animal_feed_cost($p['id'], db_read('feedusage'), $productStats));
});

// ---------- dashboard / alerts ----------
function load_all_collections() {
    return [
        'animals' => db_read('animals'),
        'breeding' => db_read('breeding'),
        'medicine' => db_read('medicine'),
        'weights' => db_read('weights'),
        'milk' => db_read('milk'),
        'milktests' => db_read('milktests'),
        'movements' => db_read('movements'),
        'feedproducts' => db_read('feedproducts'),
        'feedpurchases' => db_read('feedpurchases'),
        'feedusage' => db_read('feedusage')
    ];
}

route('GET', '/dashboard', function ($p) { json_response(200, h_build_dashboard(load_all_collections())); });
route('GET', '/alerts', function ($p) { json_response(200, h_build_alerts(load_all_collections())); });

// ---------- reports ----------
route('GET', '/reports/:report', function ($p) {
    $result = h_build_report($p['report'], load_all_collections());
    if (!$result) json_response(404, ['error' => 'Unknown report']);
    json_response(200, $result);
});

// ---------- CSV export ----------
route('GET', '/export/csv/:collection', function ($p) {
    $name = $p['collection'];
    if (!in_array($name, DB_COLLECTIONS, true)) json_response(404, ['error' => 'Unknown collection']);
    $csv = h_to_csv(db_read($name));
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $name . '.csv"');
    echo $csv;
    exit;
});

// ---------- full JSON backup export / import ----------
route('GET', '/export/json', function ($p) {
    $bundle = [];
    foreach (DB_COLLECTIONS as $name) $bundle[$name] = db_read($name);
    $bundle['_exportedAt'] = date('c');
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="farm-backup-' . time() . '.json"');
    echo json_encode($bundle, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
});

route('POST', '/import/json', function ($p) {
    global $body;
    if (!is_array($body)) json_response(400, ['error' => 'Invalid backup file']);
    $imported = [];
    foreach (DB_COLLECTIONS as $name) {
        if (isset($body[$name]) && is_array($body[$name])) {
            db_write($name, $body[$name]);
            $imported[] = $name;
        }
    }
    json_response(200, ['ok' => true, 'imported' => $imported]);
});

// ---------- settings ----------
route('GET', '/settings', function ($p) { json_response(200, db_read('settings')); });
route('PUT', '/settings', function ($p) {
    global $body;
    $updated = array_merge(db_read('settings'), $body);
    db_write('settings', $updated);
    json_response(200, $updated);
});

// ============================================================
if (!dispatch($method, $path)) {
    json_response(404, ['error' => 'Not found']);
}
