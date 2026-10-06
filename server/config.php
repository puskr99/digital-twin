<?php
// Keep this file (and the database) OUTSIDE the web root if you can.
// If not, block them in Nginx (see README.md).
return [
    // SQLite file. The folder must be writable by the PHP user (e.g. www-data).
    'db_path'    => __DIR__ . '/data/travel-log.sqlite',
    // Secret for export.php. Change this to a long random string!
    'admin_key'  => 'CHANGE-ME-to-a-long-random-string',
    // Google Apps Script web-app URL (apps-script.gs). Leave '' to disable the Sheet mirror.
    'sheet_url'  => '',
];
