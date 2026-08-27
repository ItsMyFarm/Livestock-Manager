<?php
// seed.php — populates data/ with realistic sample records so the app can
// be explored immediately after install. Run from the command line:
//   php seed.php
// Safe to re-run: it overwrites existing sample data files. Never touches
// your login (auth.json) — only run it again deliberately, since it does
// replace all sample farm records with a fresh set.

define('FARM_APP', true);
if (file_exists(__DIR__ . '/config.php')) require __DIR__ . '/config.php';
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/helpers.php';

db_ensure_dirs();

function seed_id($prefix) { return db_next_id($prefix); }
function seed_iso($y, $m, $d) { return sprintf('%04d-%02d-%02d', $y, $m, $d); }
function seed_days_ago($n) { return date('Y-m-d', strtotime("-$n days")); }
function seed_days_from_now($n) { return date('Y-m-d', strtotime("+$n days")); }
function seed_now() { return date('c'); }
function seed_rand_float($min, $max) { return $min + (mt_rand(0, 100000) / 100000) * ($max - $min); }

$animals = [];
$breeding = [];
$medicine = [];
$weights = [];
$milk = [];
$milktests = [];

// ---- Sheep flock ----
$ewesSeed = [
    ['tagNumber' => 'S001', 'name' => 'Bramble', 'breed' => 'Suffolk', 'dob' => seed_iso(2021, 3, 12)],
    ['tagNumber' => 'S002', 'name' => 'Clover', 'breed' => 'Suffolk', 'dob' => seed_iso(2020, 4, 2)],
    ['tagNumber' => 'S003', 'name' => 'Willow', 'breed' => 'Texel', 'dob' => seed_iso(2022, 2, 20)],
    ['tagNumber' => 'S004', 'name' => 'Hazel', 'breed' => 'Texel', 'dob' => seed_iso(2021, 3, 30)],
    ['tagNumber' => 'S005', 'name' => 'Poppy', 'breed' => 'Suffolk Cross', 'dob' => seed_iso(2019, 3, 15)]
];
$eweRecords = [];
foreach ($ewesSeed as $e) {
    $rec = [
        'id' => seed_id('shp'), 'species' => 'sheep', 'sex' => 'female', 'status' => 'active',
        'tagNumber' => $e['tagNumber'], 'eidNumber' => 'UK' . $e['tagNumber'] . '0001', 'name' => $e['name'],
        'breed' => $e['breed'], 'dob' => $e['dob'], 'notes' => '',
        'createdAt' => seed_now(), 'updatedAt' => seed_now()
    ];
    $eweRecords[] = $rec;
    $animals[] = $rec;
}

$ram = [
    'id' => seed_id('shp'), 'species' => 'sheep', 'sex' => 'male', 'status' => 'active',
    'tagNumber' => 'S900', 'eidNumber' => 'UKS9000001', 'name' => 'Duke', 'breed' => 'Suffolk',
    'dob' => seed_iso(2019, 1, 10), 'notes' => 'Stock ram', 'createdAt' => seed_now(), 'updatedAt' => seed_now()
];
$animals[] = $ram;

$lambData = [
    ['mother' => $eweRecords[0], 'count' => 2, 'dob' => seed_iso(2026, 3, 5), 'type' => 'twin'],
    ['mother' => $eweRecords[1], 'count' => 1, 'dob' => seed_iso(2026, 3, 8), 'type' => 'single'],
    ['mother' => $eweRecords[2], 'count' => 3, 'dob' => seed_iso(2026, 3, 15), 'type' => 'triplet']
];
$lambTagCounter = 101;
$allLambs = [];
foreach ($lambData as $entry) {
    for ($i = 0; $i < $entry['count']; $i++) {
        $tag = 'L' . $lambTagCounter++;
        $lamb = [
            'id' => seed_id('shp'), 'species' => 'sheep', 'sex' => ($i % 2 === 0) ? 'female' : 'male', 'status' => 'active',
            'tagNumber' => $tag, 'name' => '', 'breed' => $entry['mother']['breed'], 'dob' => $entry['dob'],
            'motherId' => $entry['mother']['id'], 'fatherId' => $ram['tagNumber'],
            'birthType' => $entry['type'],
            'birthWeight' => round(seed_rand_float(3.5, 5.0), 1),
            'birthNotes' => 'Normal birth, no assistance needed',
            'notes' => '', 'createdAt' => seed_now(), 'updatedAt' => seed_now()
        ];
        $animals[] = $lamb;
        $allLambs[] = $lamb;
    }
}

$breeding[] = [
    'id' => seed_id('brd'), 'animalId' => $eweRecords[0]['id'], 'species' => 'sheep',
    'eventDate' => seed_iso(2025, 10, 8), 'sireInfo' => 'Duke (S900)',
    'expectedDate' => seed_iso(2026, 3, 5), 'actualDate' => seed_iso(2026, 3, 5),
    'notes' => 'Twins, both thriving', 'createdAt' => seed_now(), 'updatedAt' => seed_now()
];
$breeding[] = [
    'id' => seed_id('brd'), 'animalId' => $eweRecords[3]['id'], 'species' => 'sheep',
    'eventDate' => seed_days_ago(140), 'sireInfo' => 'Duke (S900)', 'expectedDate' => seed_days_from_now(5),
    'notes' => 'First tupping this year', 'createdAt' => seed_now(), 'updatedAt' => seed_now()
];
$breeding[] = [
    'id' => seed_id('brd'), 'animalId' => $eweRecords[4]['id'], 'species' => 'sheep',
    'eventDate' => seed_days_ago(130), 'sireInfo' => 'Duke (S900)', 'expectedDate' => seed_days_from_now(15),
    'notes' => '', 'createdAt' => seed_now(), 'updatedAt' => seed_now()
];

// Weights for the first 3 lambs
$firstThreeLambs = array_slice($allLambs, 0, 3);
foreach ($firstThreeLambs as $lamb) {
    $weights[] = ['id' => seed_id('wgt'), 'animalId' => $lamb['id'], 'date' => $lamb['dob'], 'weight' => $lamb['birthWeight'], 'notes' => 'Birth weight', 'createdAt' => seed_now(), 'updatedAt' => seed_now()];
    $weights[] = ['id' => seed_id('wgt'), 'animalId' => $lamb['id'], 'date' => seed_days_ago(20), 'weight' => $lamb['birthWeight'] + 6, 'notes' => '', 'createdAt' => seed_now(), 'updatedAt' => seed_now()];
}

// A medicine treatment with an active withdrawal, so alerts have something to show
$medicine[] = h_compute_withdrawal([
    'id' => seed_id('med'), 'animalId' => $eweRecords[1]['id'], 'species' => 'sheep',
    'treatmentDate' => seed_days_ago(3), 'medicineName' => 'Footvax', 'dose' => '2ml', 'route' => 'Subcutaneous injection',
    'withdrawalPeriod' => 28, 'meatWithdrawalPeriod' => 28,
    'reason' => 'Preventative footrot vaccination', 'notes' => '',
    'createdAt' => seed_now(), 'updatedAt' => seed_now()
]);
$medicine[] = h_compute_withdrawal([
    'id' => seed_id('med'), 'animalId' => $eweRecords[2]['id'], 'species' => 'sheep',
    'treatmentDate' => seed_days_ago(60), 'medicineName' => 'Dectomax', 'dose' => '1ml/10kg', 'route' => 'Subcutaneous injection',
    'withdrawalPeriod' => 70, 'meatWithdrawalPeriod' => 70,
    'reason' => 'Worming', 'notes' => 'Routine autumn worming', 'createdAt' => seed_now(), 'updatedAt' => seed_now()
]);

// ---- Dairy cows ----
$cowsSeed = [
    ['tagNumber' => 'C010', 'name' => 'Daisy', 'breed' => 'Holstein', 'dob' => seed_iso(2019, 5, 1), 'status' => 'milking'],
    ['tagNumber' => 'C011', 'name' => 'Buttercup', 'breed' => 'Jersey', 'dob' => seed_iso(2020, 6, 14), 'status' => 'milking'],
    ['tagNumber' => 'C012', 'name' => 'Rosie', 'breed' => 'Holstein Friesian', 'dob' => seed_iso(2018, 9, 22), 'status' => 'dry'],
    ['tagNumber' => 'C013', 'name' => 'Marigold', 'breed' => 'Jersey', 'dob' => seed_iso(2021, 1, 30), 'status' => 'milking']
];
$cowRecords = [];
foreach ($cowsSeed as $c) {
    $rec = [
        'id' => seed_id('cow'), 'species' => 'cow', 'sex' => 'female', 'status' => $c['status'],
        'tagNumber' => $c['tagNumber'], 'name' => $c['name'], 'breed' => $c['breed'], 'dob' => $c['dob'], 'notes' => '',
        'createdAt' => seed_now(), 'updatedAt' => seed_now()
    ];
    $cowRecords[] = $rec;
    $animals[] = $rec;
}

$bull = [
    'id' => seed_id('cow'), 'species' => 'cow', 'sex' => 'male', 'status' => 'active',
    'tagNumber' => 'C900', 'name' => 'Samson', 'breed' => 'Holstein', 'dob' => seed_iso(2018, 2, 1),
    'notes' => 'AI records kept for most services', 'createdAt' => seed_now(), 'updatedAt' => seed_now()
];
$animals[] = $bull;

$calf = [
    'id' => seed_id('cow'), 'species' => 'cow', 'sex' => 'female', 'status' => 'active', 'tagNumber' => 'C101', 'name' => 'Buttercup Jr',
    'breed' => 'Holstein', 'dob' => seed_days_ago(45), 'motherId' => $cowRecords[0]['id'], 'fatherId' => 'AI - Semex 4021',
    'birthWeight' => 38, 'birthNotes' => 'Easy calving, no assistance', 'notes' => '',
    'createdAt' => seed_now(), 'updatedAt' => seed_now()
];
$animals[] = $calf;

$breeding[] = [
    'id' => seed_id('brd'), 'animalId' => $cowRecords[0]['id'], 'species' => 'cow',
    'eventDate' => seed_days_ago(325), 'method' => 'AI', 'sireInfo' => 'Semex 4021',
    'expectedDate' => seed_days_ago(45), 'actualDate' => seed_days_ago(45),
    'pregnancyCheckResult' => 'Confirmed in calf', 'notes' => '', 'createdAt' => seed_now(), 'updatedAt' => seed_now()
];
$breeding[] = [
    'id' => seed_id('brd'), 'animalId' => $cowRecords[1]['id'], 'species' => 'cow',
    'eventDate' => seed_days_ago(50), 'method' => 'natural', 'sireInfo' => 'Samson (C900)', 'expectedDate' => seed_days_from_now(230),
    'pregnancyCheckResult' => '', 'notes' => 'Due for pregnancy check', 'createdAt' => seed_now(), 'updatedAt' => seed_now()
];
$breeding[] = [
    'id' => seed_id('brd'), 'animalId' => $cowRecords[3]['id'], 'species' => 'cow',
    'eventDate' => seed_days_ago(280), 'method' => 'AI', 'sireInfo' => 'Semex 3110', 'expectedDate' => seed_days_from_now(5),
    'pregnancyCheckResult' => 'Confirmed in calf', 'notes' => '', 'createdAt' => seed_now(), 'updatedAt' => seed_now()
];

// Milk records: last 30 days, twice a day, for the 3 milking cows
$milkingCows = array_values(array_filter($cowRecords, function ($c) { return $c['status'] === 'milking'; }));
$baseYield = [
    $milkingCows[0]['id'] => 14,
    $milkingCows[1]['id'] => 11,
    $milkingCows[2]['id'] => 12.5
];
for ($i = 30; $i >= 0; $i--) {
    $date = seed_days_ago($i);
    foreach ($milkingCows as $cow) {
        $base = $baseYield[$cow['id']] ?? 12;
        $dropFactor = 1;
        // Simulate a yield dip for Buttercup in the last 5 days, to trigger the drop alert
        if ($cow['id'] === $milkingCows[1]['id'] && $i <= 5) $dropFactor = 0.6;
        foreach (['AM', 'PM'] as $session) {
            $litres = max(0, $base / 2 * $dropFactor + (seed_rand_float(0, 1) - 0.5) * 1.2);
            $milk[] = [
                'id' => seed_id('mlk'), 'cowId' => $cow['id'], 'date' => $date, 'session' => $session,
                'litres' => round($litres, 1), 'createdAt' => seed_now(), 'updatedAt' => seed_now()
            ];
        }
    }
}

// Milk tests, roughly monthly, with one high SCC to demo the alert
foreach ($milkingCows as $idx => $cow) {
    foreach ([60, 30, 2] as $daysBack) {
        $milktests[] = [
            'id' => seed_id('mlt'), 'cowId' => $cow['id'], 'date' => seed_days_ago($daysBack),
            'butterfat' => round(seed_rand_float(3.8, 4.6), 1),
            'protein' => round(seed_rand_float(3.1, 3.6), 1),
            'scc' => ($daysBack === 2 && $idx === 1) ? 285000 : (int) round(seed_rand_float(80000, 170000)),
            'notes' => '', 'createdAt' => seed_now(), 'updatedAt' => seed_now()
        ];
    }
}

// A medicine record for a dry cow, withdrawal already expired (treatment history variety)
$medicine[] = h_compute_withdrawal([
    'id' => seed_id('med'), 'animalId' => $cowRecords[2]['id'], 'species' => 'cow',
    'treatmentDate' => seed_days_ago(90), 'medicineName' => 'Betamox LA', 'dose' => '15ml', 'route' => 'Intramuscular injection',
    'withdrawalPeriod' => 18, 'milkWithdrawalPeriod' => 5, 'meatWithdrawalPeriod' => 18,
    'reason' => 'Mild mastitis', 'notes' => 'Resolved well', 'createdAt' => seed_now(), 'updatedAt' => seed_now()
]);
$medicine[] = h_compute_withdrawal([
    'id' => seed_id('med'), 'animalId' => $milkingCows[1]['id'], 'species' => 'cow',
    'treatmentDate' => seed_days_ago(2), 'medicineName' => 'Ubrolexin', 'dose' => '1 tube per quarter', 'route' => 'Intramammary',
    'withdrawalPeriod' => 5, 'milkWithdrawalPeriod' => 5, 'meatWithdrawalPeriod' => 7,
    'reason' => 'Clinical mastitis, front left quarter', 'notes' => 'Monitor for recurrence',
    'createdAt' => seed_now(), 'updatedAt' => seed_now()
]);

// Some weights for cows + the calf
$cowsAndCalf = array_merge($cowRecords, [$calf]);
foreach ($cowsAndCalf as $cow) {
    $isCalf = ($cow['id'] === $calf['id']);
    $weights[] = [
        'id' => seed_id('wgt'), 'animalId' => $cow['id'], 'date' => seed_days_ago(60),
        'weight' => $isCalf ? 38 : (int) round(seed_rand_float(520, 610)),
        'notes' => '', 'createdAt' => seed_now(), 'updatedAt' => seed_now()
    ];
    $weights[] = [
        'id' => seed_id('wgt'), 'animalId' => $cow['id'], 'date' => seed_days_ago(5),
        'weight' => $isCalf ? 62 : (int) round(seed_rand_float(540, 630)),
        'notes' => '', 'createdAt' => seed_now(), 'updatedAt' => seed_now()
    ];
}

db_write('animals', $animals);
db_write('breeding', $breeding);
db_write('medicine', $medicine);
db_write('weights', $weights);
db_write('milk', $milk);
db_write('milktests', $milktests);
db_write('settings', ['theme' => 'light', 'farmName' => 'Sample Farm']);

echo 'Seeded: ' . count($animals) . ' animals, ' . count($breeding) . ' breeding records, '
    . count($medicine) . ' medicine records, ' . count($weights) . ' weight records, '
    . count($milk) . ' milk records, ' . count($milktests) . " milk tests.\n";
