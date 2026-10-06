<?php
// Download everything as CSV:  /travel-log/export.php?key=YOUR_ADMIN_KEY
require __DIR__ . '/lib.php';
if (!hash_equals(cfg()['admin_key'], (string)($_GET['key'] ?? ''))) { http_response_code(403); exit('Forbidden'); }

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="travel-log-' . date('Ymd') . '.csv"');
$f = fopen('php://output', 'w');
fwrite($f, "\xEF\xBB\xBF");                       // BOM so Excel reads UTF-8
fputcsv($f, array_merge(['Received at (UTC)'], HEAD));
foreach (db()->query('SELECT * FROM trips ORDER BY received_at') as $r) {
    $t = ['hazmon' => $r['hazmon'], 'date' => $r['date'], 'time' => $r['time'], 'purpose' => $r['purpose'],
          'origin' => $r['origin'], 'dest' => $r['dest'], 'legs' => json_decode($r['legs'], true)];
    foreach (trip_rows($t) as $row) fputcsv($f, array_merge([$r['received_at']], $row));
}
