# Travel Log server (PHP + SQLite)

Files: `submit.php` (receives trips), `export.php` (CSV download), `lib.php`, `config.php`.
Needs PHP 8.1+ with `pdo_sqlite` and `curl`.

1. Upload `index.html` and these PHP files to the same folder, e.g. `/travel-log/`
   (the page posts to `submit.php` next to it).
2. Edit `config.php`: set `admin_key` and (optional) `sheet_url` (Apps Script URL).
3. Make `data/` writable: `mkdir data && chown www-data data && chmod 750 data`.
4. **Nginx does not read .htaccess.** Block direct access to the DB and config:

       location ~ ^/travel-log/(data/|config\.php|lib\.php) { deny all; return 404; }

   (Better: move `data/` and `config.php` outside the web root and fix the paths in `config.php`/`lib.php`.)
5. Download data: `https://yoursite.com/travel-log/export.php?key=YOUR_ADMIN_KEY`
