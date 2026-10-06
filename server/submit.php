<?php
require __DIR__ . '/lib.php';
header('Content-Type: application/json');

function out(int $code, array $body): never { http_response_code($code); echo json_encode($body); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') out(405, ['ok' => false, 'error' => 'POST only']);
$in = json_decode(file_get_contents('php://input'), true);
if (!is_array($in)) out(400, ['ok' => false, 'error' => 'Invalid JSON']);

$s = fn($v, int $max = 300) => mb_substr(trim((string)($v ?? '')), 0, $max);

$trip = [
    'id' => $s($in['id'], 64), 'hazmon' => $s($in['hazmon'], 40), 'date' => $s($in['date'], 10),
    'time' => $s($in['time'], 5), 'purpose' => $s($in['purpose'], 60),
    'origin' => $s($in['from'] ?? null, 120), 'dest' => $s($in['to'] ?? null, 120), 'legs' => [],
];
foreach (['id','hazmon','date','time','purpose','origin','dest'] as $k)
    if ($trip[$k] === '') out(400, ['ok' => false, 'error' => "Missing $k"]);
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $trip['date']) || !preg_match('/^\d{2}:\d{2}$/', $trip['time']))
    out(400, ['ok' => false, 'error' => 'Bad date/time']);

$legs = is_array($in['legs'] ?? null) ? array_slice($in['legs'], 0, 4) : [];
foreach ($legs as $l) {
    $leg = ['mode' => $s($l['mode'] ?? null, 60), 'km' => $s($l['km'] ?? null, 12), 'min' => $s($l['min'] ?? null, 8),
            'cost' => $s($l['cost'] ?? null, 12), 'reason' => $s($l['reason'] ?? null), 'alt' => $s($l['alt'] ?? null)];
    foreach (['km','min','cost'] as $n)
        if ($leg[$n] !== '' && !is_numeric($leg[$n])) out(400, ['ok' => false, 'error' => "Bad $n"]);
    if ($leg['mode'] === '' || $leg['km'] === '' || $leg['min'] === '') out(400, ['ok' => false, 'error' => 'Incomplete leg']);
    $trip['legs'][] = $leg;
}
if (!$trip['legs']) out(400, ['ok' => false, 'error' => 'No legs']);

try {
    $pdo = db();
    // INSERT OR IGNORE: a retried trip with the same id is stored only once.
    $pdo->prepare('INSERT OR IGNORE INTO trips (id,received_at,hazmon,date,time,purpose,origin,dest,legs)
                   VALUES (?,?,?,?,?,?,?,?,?)')
        ->execute([$trip['id'], gmdate('c'), $trip['hazmon'], $trip['date'], $trip['time'],
                   $trip['purpose'], $trip['origin'], $trip['dest'], json_encode($trip['legs'], JSON_UNESCAPED_UNICODE)]);
} catch (Throwable $e) {
    error_log('travel-log db: ' . $e->getMessage());
    out(500, ['ok' => false, 'error' => 'Server error']);
}

// Data is safely stored. Tell the browser now; mirror to the Sheet afterwards.
echo json_encode(['ok' => true]);
if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();

mirror_to_sheet($pdo);

// Pushes every not-yet-synced trip (this one + any earlier failures) to Google Sheets.
function mirror_to_sheet(PDO $pdo): void {
    $url = cfg()['sheet_url'];
    if ($url === '') return;
    $pending = $pdo->query('SELECT * FROM trips WHERE sheet_synced = 0 ORDER BY received_at LIMIT 50')->fetchAll(PDO::FETCH_ASSOC);
    foreach ($pending as $r) {
        $t = ['hazmon' => $r['hazmon'], 'date' => $r['date'], 'time' => $r['time'], 'purpose' => $r['purpose'],
              'origin' => $r['origin'], 'dest' => $r['dest'], 'legs' => json_decode($r['legs'], true)];
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 15,
            CURLOPT_HTTPHEADER => ['Content-Type: text/plain;charset=utf-8'],
            CURLOPT_POSTFIELDS => json_encode(['header' => HEAD, 'rows' => trip_rows($t)], JSON_UNESCAPED_UNICODE),
        ]);
        $res = curl_exec($ch);
        $ok = $res !== false && curl_getinfo($ch, CURLINFO_RESPONSE_CODE) === 200 && trim($res) === 'ok';
        curl_close($ch);
        if (!$ok) { error_log('travel-log sheet sync failed for ' . $r['id']); break; }  // retry on next submission
        $pdo->prepare('UPDATE trips SET sheet_synced = 1 WHERE id = ?')->execute([$r['id']]);
    }
}
