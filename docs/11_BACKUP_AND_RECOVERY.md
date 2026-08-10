# Backup, Restore, and Troubleshooting

## 1. What must be backed up

NisheTube has three local recovery assets:

1. the MySQL `nishetube` database, including users, research history, snapshots, scores, quota events, queue state, export metadata, and cleanup audits;
2. private generated CSV/XLSX files under `storage\app\private\exports` when `EXPORT_DISK=local`;
3. the local `.env`, which contains the application key, database connection, and YouTube API key.

Keep backups outside the repository. The `.env` copy is secret: restrict access, never commit it, and never attach it to logs or support messages. A database dump without the private export directory preserves the research and export records, but previously generated download files will be missing.

Back up before migrations, dependency upgrades, retention execution, or a restore. For the cleanest point-in-time copy, finish or pause active research/export/cleanup jobs and stop the queue worker first.

## 2. Create a consistent local backup

Open PowerShell in the project directory. The example stores the backup beside the repository rather than inside it.

```powershell
$stamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$backupRoot = Join-Path (Split-Path (Get-Location) -Parent) "nishetube-backup-$stamp"
$databaseBackup = Join-Path $backupRoot 'nishetube.sql'
$mysqldump = (Get-Command mysqldump.exe -ErrorAction Stop).Source

New-Item -ItemType Directory -Path $backupRoot -ErrorAction Stop | Out-Null

& $mysqldump `
    --host=127.0.0.1 `
    --port=3306 `
    --user=root `
    --password `
    --default-character-set=utf8mb4 `
    --single-transaction `
    --quick `
    --routines `
    --events `
    --triggers `
    --no-tablespaces `
    --set-gtid-purged=OFF `
    --databases nishetube `
    "--result-file=$databaseBackup"

if ($LASTEXITCODE -ne 0) { throw 'mysqldump failed; do not trust this backup.' }

$exportsSource = Join-Path (Get-Location) 'storage\app\private\exports'
if (Test-Path -LiteralPath $exportsSource) {
    Copy-Item -LiteralPath $exportsSource -Destination (Join-Path $backupRoot 'exports') -Recurse -ErrorAction Stop
}

Copy-Item -LiteralPath '.env' -Destination (Join-Path $backupRoot '.env') -ErrorAction Stop

Get-ChildItem -LiteralPath $backupRoot -File -Recurse | ForEach-Object {
    [pscustomobject]@{
        Path = $_.FullName.Substring($backupRoot.Length + 1)
        Hash = (Get-FileHash -LiteralPath $_.FullName -Algorithm SHA256).Hash
    }
} | Export-Csv -NoTypeInformation -Path (Join-Path $backupRoot 'SHA256SUMS.csv')
```

`--password` prompts without exposing the database password in shell history; press Enter when the local MySQL account has a blank password. Change the host, port, user, and database arguments when the local `.env` differs. Do not paste the password directly into the command.

Verify that `nishetube.sql` is non-empty, the expected `exports` tree is present when downloads exist, `.env` is protected, and `SHA256SUMS.csv` was created. Copy the entire backup directory to a second local disk if the primary drive is the failure scenario being protected against.

Before a restore, verify the copied files against the relative-path manifest:

```powershell
$hashFailures = Import-Csv (Join-Path $backupRoot 'SHA256SUMS.csv') | Where-Object {
    -not (Test-Path -LiteralPath (Join-Path $backupRoot $_.Path)) -or
    (Get-FileHash -LiteralPath (Join-Path $backupRoot $_.Path) -Algorithm SHA256).Hash -ne $_.Hash
}

if ($hashFailures) { throw 'Backup hash verification failed; do not restore it.' }
```

## 3. Restore MySQL and private exports

Restore only into the intended local NisheTube installation. This operation replaces the target database and may replace its export directory.

1. Stop `composer run dev`, standalone `queue:work`, and `schedule:work` processes. Keep MySQL running.
2. Make an emergency backup of the current state with the procedure above.
3. Put the application in maintenance mode with `php artisan down`.
4. Verify the selected backup path and hashes. Never restore an unknown or partial dump.
5. Recreate and import the database using Laragon's HeidiSQL import, or the MySQL client workflow below.

```powershell
$backupRoot = 'D:\backups\nishetube-backup-YYYYMMDD-HHMMSS'
$databaseBackup = (Resolve-Path (Join-Path $backupRoot 'nishetube.sql')).Path.Replace('\', '/')
$mysql = (Get-Command mysql.exe -ErrorAction Stop).Source

# Destructive: run only after the emergency backup and after confirming the target name.
& $mysql --host=127.0.0.1 --port=3306 --user=root --password `
    --execute="DROP DATABASE IF EXISTS nishetube; CREATE DATABASE nishetube CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
if ($LASTEXITCODE -ne 0) { throw 'Database recreation failed; stop the restore.' }

& $mysql --host=127.0.0.1 --port=3306 --user=root --password `
    "--execute=SOURCE $databaseBackup"
if ($LASTEXITCODE -ne 0) { throw 'Database import failed; keep maintenance mode enabled.' }
```

Press Enter at each prompt when the local MySQL password is blank. Adjust connection arguments to match `.env`. The backup created by this guide includes the database name, table definitions, routines, triggers, and events.

Restore the private files with a reversible directory swap. The renamed live directory is the file rollback copy; remove it only after verification.

```powershell
$liveExports = Join-Path (Get-Location) 'storage\app\private\exports'
$backupExports = Join-Path $backupRoot 'exports'
$rollbackExports = "$liveExports.before-restore-$(Get-Date -Format 'yyyyMMdd-HHmmss')"

if (Test-Path -LiteralPath $liveExports) {
    Move-Item -LiteralPath $liveExports -Destination $rollbackExports -ErrorAction Stop
}

if (Test-Path -LiteralPath $backupExports) {
    Copy-Item -LiteralPath $backupExports -Destination $liveExports -Recurse -ErrorAction Stop
}
```

Restore `.env` only when recovering the same trusted local installation and only from the protected backup. Confirm `APP_KEY`, database values, `EXPORT_DISK`, and API settings before continuing. Do not expose the file while comparing it.

Finish and verify:

```text
php artisan optimize:clear
php artisan migrate:status
php artisan migrate --force
php artisan queue:restart
php artisan up
```

Run `migrate --force` only when the restored database predates the checked-out code; it is safe to omit when every migration is already shown as run. Do not use `migrate:fresh`, `migrate:refresh`, or `migrate:reset` on recovered research data.

After restarting `composer run dev`, verify login, recent research/history counts, one completed score, the Exports list, and one unexpired download. Review `php artisan queue:failed`; restored in-flight jobs may need investigation before an individual `php artisan queue:retry <job-id>`.

### 3.1 Rehearse expansion migration rollback without user data

The shared-collection and integrated Analyzer expansion consists of the 19 migrations from `2026_08_08_070000_create_shared_collection_foundation.php` through `2026_08_10_040000_create_thumbnail_analysis_profiles.php`. Rehearse their `down` and `up` paths only against a new disposable database. Never point this command sequence at MySQL or a copy containing user research.

From the project directory, use a uniquely named SQLite file under the ignored testing directory:

```powershell
$rehearsalDatabase = Join-Path (Get-Location) 'storage\framework\testing\rel05-migration-rehearsal.sqlite'
$resolvedProject = (Resolve-Path (Get-Location)).Path

if (Test-Path -LiteralPath $rehearsalDatabase) {
    throw 'The rehearsal target already exists; choose a new disposable filename.'
}
if (-not ([System.IO.Path]::GetFullPath($rehearsalDatabase).StartsWith($resolvedProject, [System.StringComparison]::OrdinalIgnoreCase))) {
    throw 'The rehearsal database must stay inside this project testing directory.'
}

New-Item -ItemType File -Path $rehearsalDatabase -ErrorAction Stop | Out-Null
$env:DB_CONNECTION = 'sqlite'
$env:DB_DATABASE = $rehearsalDatabase
$env:CACHE_STORE = 'array'
$env:SESSION_DRIVER = 'array'
$env:QUEUE_CONNECTION = 'sync'

php artisan migrate --force
if ($LASTEXITCODE -ne 0) { throw 'Disposable migration failed.' }

php artisan migrate:rollback --step=19 --force
if ($LASTEXITCODE -ne 0) { throw 'Expansion rollback rehearsal failed.' }

php artisan migrate --force
if ($LASTEXITCODE -ne 0) { throw 'Expansion re-apply rehearsal failed.' }

php artisan migrate:status
if ($LASTEXITCODE -ne 0) { throw 'Disposable migration status failed.' }

Remove-Item -LiteralPath $rehearsalDatabase
```

Run this in a fresh PowerShell process so the temporary database environment variables disappear when the terminal closes. If a step fails, keep the disposable file for diagnosis, do not continue a live upgrade, and restore the verified pre-upgrade MySQL/export backup using Section 3. Do not use `migrate:fresh`, `migrate:refresh`, or `migrate:reset` as recovery shortcuts.

After a successful real upgrade or restore, sign in as two local users and verify that each sees only their own Analyzer, Watchlist, Topic Workspace, Export, and Retention records. Open one Romanian or Russian long-title result at 1440px and 768px, confirm the exact-value tables remain usable, and confirm that loading Explore does not add a quota-ledger event.

## 4. Export lifecycle and recovery limits

- With the default `EXPORT_DISK=local`, generated files live at `storage\app\private\exports\<user-id>\<export-public-id>.<csv|xlsx>` and are never public web assets.
- The database stores disk, relative path, size, SHA-256 checksum, completion time, and expiry. Default expiry is `EXPORT_EXPIRY_DAYS=7`.
- Expired export files are eligible for retention cleanup. Back up files that must outlive that download window or regenerate them from preserved research runs.
- A database-only restore can show a completed export whose file was not backed up. Regenerate it from the Exports UI instead of manually changing export metadata.
- If an export is pending or processing after restore, start the database queue worker and inspect failed jobs. Retry through the UI when possible.

## 5. Quota and reset reference

The header and Settings show a **NisheTube estimate**, not the authoritative Google Cloud quota balance.

- `YOUTUBE_SEARCH_DAILY_ALLOWANCE` and `YOUTUBE_GENERAL_DAILY_ALLOWANCE` configure local bucket guardrails only.
- Every NisheTube provider attempt is recorded in `api_usage_events`, including failed attempts.
- The ledger resets at midnight in `YOUTUBE_QUOTA_RESET_TIMEZONE` (`America/Los_Angeles` by default), with daylight-saving-aware conversion to UTC and the user's display timezone.
- Requests by another application or key consumer are invisible to NisheTube, so Google Cloud Console remains authoritative.
- A Google quota-exhausted response keeps the local bucket marked exhausted for that ledger day. Do not delete usage rows or increase the displayed allowance as a substitute for provider quota.

After changing quota environment values, run `php artisan optimize:clear` and restart long-running workers with `php artisan queue:restart`.

## 6. Troubleshooting

### Tool command is not found

Use the Laragon terminal or run `Get-Command php, composer.bat, node, npm.cmd, mysql.exe, mysqldump.exe`. If a tool is unresolved, add the matching Laragon version's directory to the current PowerShell `PATH`; do not overwrite the global `PATH`.

### Database connection fails

Confirm MySQL is running in Laragon and that `.env` host, port, database, username, and password match the local server. Then run `php artisan optimize:clear` and `php artisan migrate:status`. Do not solve a connection problem with a destructive migration command.

### Page is unavailable or frontend assets are missing

Confirm the Laragon virtual host is serving `http://nishetube.test`, dependencies are installed, and `composer run dev` is active for Vite and the background processes. Check `storage\logs\laravel.log` without copying secrets into reports.

### Research, discovery, export, or cleanup stays queued

Confirm `QUEUE_CONNECTION=database`, migrations are current, and a queue worker is running through `composer run dev` or `php artisan queue:work`. Inspect `php artisan queue:failed`. Fix the underlying error before retrying a specific job with `php artisan queue:retry <job-id>`; never use `queue:flush` as a repair step.

### YouTube connectivity or quota fails

Use the Settings connectivity test. For a missing/invalid key, verify the API is enabled and the key is restricted to YouTube Data API v3. For provider exhaustion, check Google Cloud Console and wait for its reset; the local Pacific-time ledger is explanatory, not authoritative. Never paste the key into logs, screenshots, or support messages.

### Export is missing, expired, or failed

Check that the queue worker is running and that `storage\app\private\exports` is writable. An expired file can be regenerated while its source research remains. A completed database record with no file indicates an incomplete backup/restore; restore the matching file set or regenerate through the UI.

### Retention did not run

Preview with `php artisan retention:cleanup --dry-run`. Automatic execution requires `php artisan schedule:work` during the scheduled window, while both scheduled and manual execution require a queue worker. The Settings retention page provides the local-PC fallback and audit history.

### Restore validation fails

Leave maintenance mode enabled, stop workers, and do not continue using the partially restored database. Review the MySQL error, backup hash, target connection, and dump version. Restore the emergency backup if necessary, then repeat validation before `php artisan up`.
