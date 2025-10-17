Scaling guidance for Agrohub_Engine — quick plan to support ~1000 concurrent users

This document contains safe, prioritized steps to improve throughput and reduce latency.

1) Backup your database (required before any schema change)

Open Powershell and run:

```powershell
$timestamp = (Get-Date).ToString('yyyyMMddHHmmss')
$backupPath = "C:\backups\agrohub_db-$timestamp.sql"
mkdir (Split-Path $backupPath) -Force
mysqldump -u root -p %DBNAME% > $backupPath
```

Replace `%DBNAME%` with your DB name (default: `agrohub_erp`). When prompted, enter the DB password.

2) Add recommended DB indexes (safe)

A small migration script is provided at `tools/add_db_indexes.php`. It will check for indexes and create them only if missing.

Run it from project root (assuming php is in PATH):

```powershell
php tools\add_db_indexes.php
```

3) Reduce synchronous logging and heavy per-request tasks (already applied)

- `api/auth.php` now only logs requests when `ENVIRONMENT === 'development'` or `LOG_LEVEL === 'debug'`.
- PHP error logging (`auth_debug.log`) is enabled only in development or debug.

4) Enable OPcache in PHP (big win)

Edit your `php.ini` and enable OPcache. Typical settings:

```ini
opcache.enable=1
opcache.memory_consumption=128
opcache.interned_strings_buffer=8
opcache.max_accelerated_files=10000
opcache.validate_timestamps=1
opcache.revalidate_freq=2
```

Restart your web server after changing `php.ini`.

5) Serve static assets efficiently

- Enable gzip/deflate and proper cache headers in your web server.
- Resize and compress images in `modules/violations/uploads/`. Consider using WebP for browsers that support it.
- If production, serve static assets from a dedicated static server or CDN.

6) Front-end optimizations

- Debounce heavy state saves (we can patch `index.html` to debounce `saveDetailedAppState()` to e.g. 500ms and avoid reading iframe storage on every event).
- Use browser Performance tab to find long main-thread tasks and target them.

7) Load testing and monitoring

- Use a load tool (ab, wrk, hey, JMeter) to simulate 1000 concurrent users.
- Monitor DB (slow query log), PHP processes, CPU, memory, and disk I/O during the test.

8) Longer-term improvements

- Introduce Redis for session storage and caching.
- Offload long-running work to queues/workers (e.g., image processing).
- Use database connection pooling and tune MySQL (innodb_buffer_pool_size etc.) for larger loads.

If you want, I can:
- Run the migration script (I will need DB credentials or you run it locally),
- Patch front-end debounce for state saves (low risk),
- Add a tiny load-testing script and walk you through running it locally.

Tell me which of the above you'd like me to do next.