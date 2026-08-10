# Local Setup

## 1. Confirmed environment

| Component        | Local value                                                          |
| ---------------- | -------------------------------------------------------------------- |
| Project path     | `<Laragon root>\www\NisheTube` (normally `C:\laragon\www\NisheTube`) |
| Laragon terminal | `C:\laragon\bin\cmder\Cmder.exe`                                     |
| PHP              | 8.3.30 x64                                                           |
| Composer         | 2.9.4                                                                |
| Node.js          | 22.22.0                                                              |
| MySQL            | 8.4.3                                                                |
| Laravel          | 13.8+ within major 13                                                |

### 1.1 Codex and automation tool paths

The Laragon terminal prepares these tools automatically, but a Codex or plain PowerShell process may not inherit the same `PATH`. Laragon itself and the repository may be installed on a drive other than `C:`. First prefer executable discovery so commands follow the active Laragon installation:

```powershell
Get-Command php, composer.bat, node, npm.cmd, mysql.exe, mysqldump.exe
```

If discovery fails, the following paths show the expected layout under the default `C:\laragon` root. Substitute the actual Laragon root and installed version directory when they differ:

| Tool              | Default executable or directory                         |
| ----------------- | ------------------------------------------------------- |
| PHP               | `C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe`  |
| Composer launcher | `C:\laragon\bin\composer\composer.bat`                  |
| Composer PHAR     | `C:\laragon\bin\composer\composer.phar`                 |
| Laragon Node.js   | `C:\laragon\bin\nodejs\node-v22\node.exe`               |
| Laragon npm       | `C:\laragon\bin\nodejs\node-v22\npm.cmd`                |
| MySQL client      | `C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe` |
| Laragon terminal  | `C:\laragon\bin\cmder\Cmder.exe`                        |

For a PowerShell process that cannot resolve the tools, prepend the matching directories to the process-local `PATH`:

```powershell
$env:Path = @(
    'C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64'
    'C:\laragon\bin\composer'
    'C:\laragon\bin\nodejs\node-v22'
    'C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin'
    $env:Path
) -join ';'
```

Then verify resolution before running project checks:

```powershell
php --version
composer.bat --version
node --version
npm.cmd --version
mysql.exe --version
```

Use `composer.bat` and `npm.cmd` in automated PowerShell commands to avoid PowerShell execution-policy ambiguity. This setup is process-local and must not overwrite the user's global `PATH`.

The Codex sandbox may also reject Git commands because the repository is owned by the desktop user. Pass the repository as a command-local safe directory instead of changing global Git configuration:

```powershell
git -c safe.directory=C:/laragon/www/NisheTube status --short
git -c safe.directory=C:/laragon/www/NisheTube diff --check
```

## 2. Application status

The integrated local research expansion is implemented through the `REL-05` release-hardening gate. Search, Analyzer, Explore, Watchlist, Topic Workspaces, Discover validation, History, Export, and Retention share canonical stored evidence and owner-scoped authorization.

The application uses the official Laravel 13 React starter-kit conventions with Inertia 3, React 19, TypeScript, Tailwind CSS 4, shadcn/ui, Wayfinder typed routes, and Fortify authentication. Registration, password reset, email verification routes, password confirmation, timezone, default market, and research preferences are implemented. Optional passkey and two-factor authentication features are not enabled.

## 3. Target local configuration

Recommended `.env` values:

```text
APP_NAME=NisheTube
APP_ENV=local
APP_URL=http://nishetube.test
APP_LOCALE=en
APP_FALLBACK_LOCALE=en

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=nishetube
DB_USERNAME=root
DB_PASSWORD=

QUEUE_CONNECTION=database
CACHE_STORE=database
SESSION_DRIVER=database

YOUTUBE_API_KEY=
YOUTUBE_SEARCH_DAILY_ALLOWANCE=100
YOUTUBE_GENERAL_DAILY_ALLOWANCE=10000
```

Confirm the actual local MySQL username/password instead of assuming Laragon defaults. Never copy a real API key into `.env.example`.

## 4. Database setup

Using Laragon/HeidiSQL or the Laragon terminal:

1. Start MySQL and the selected web server in Laragon.
2. Create a UTF-8 database named `nishetube` with `utf8mb4` collation.
3. Configure `.env`.
4. Generate the application key if needed.
5. Run migrations and seed the three markets.

Do not run destructive migration refresh commands after real research data exists.

### 4.1 Upgrade an existing NisheTube database

Before applying expansion migrations, stop the queue worker, create the database/export/`.env` backup described in [Backup, restore, and troubleshooting](11_BACKUP_AND_RECOVERY.md), and verify its hash manifest. Then run:

```text
php artisan migrate:status
php artisan migrate --force
php artisan optimize:clear
php artisan queue:restart
```

Start the worker again and verify Search → Analyzer → Explore, one Watchlist/Topic Workspace handoff, and Retention preview. Completed research, pinned observations, scores, exports, and cleanup audits must remain readable with their original timestamps.

Never rehearse rollback against the live `nishetube` database. Section 3.1 of the recovery guide provides an isolated SQLite rehearsal for the 19 expansion migrations and the safe restore path if a real upgrade fails.

## 5. Daily development workflow

From the project directory:

```text
composer install
npm install
php artisan migrate
composer run dev
```

`composer run dev` starts Laravel's local PHP server, a database queue listener, and Vite. If Laragon already serves `http://nishetube.test`, use separate `npm run dev` and `php artisan queue:work` terminals instead of starting the additional PHP server. Keep a queue worker running whenever research, discovery, exports, or retention work is queued.

## 6. Scheduler and retention on a local PC

The computer is not expected to run continuously. Retention therefore needs all three entry points:

- an Artisan command with `--dry-run` and execution modes;
- a scheduled definition for periods when `schedule:work` is active;
- a Settings UI reminder/action when cleanup is due.

The default eligibility cutoff is six calendar months before the UTC execution time.

Useful manual commands:

```text
php artisan retention:cleanup --dry-run
php artisan retention:cleanup
php artisan schedule:list
php artisan schedule:work
```

The non-dry-run command queues cleanup work; a queue worker must be running to execute it. Preview the eligible scope first. Favorited runs are preserved by automatic retention, while selective manual deletion remains an explicitly confirmed owner action in Settings.

## 7. YouTube API setup

1. Create or choose a Google Cloud project.
2. Enable YouTube Data API v3.
3. Create an API key for public-data requests.
4. Restrict the key to YouTube Data API v3.
5. Store it only in local `.env`.
6. Use the NisheTube Settings connectivity test.

Do not use browser HTTP-referrer restrictions for server-side Laravel requests. IP restrictions may be impractical on a changing residential connection; API restriction is still required.

## 8. Verification

Full-project verification is manual-only. Codex must not run this aggregate command:

```text
composer ci:check
```

For a manual full verification, this command runs PHP formatting checks, PHPStan, PHPUnit, frontend formatting checks, ESLint, TypeScript checks, and the production Vite build. Tests use SQLite in memory and block external HTTP requests unless a test registers an explicit Laravel HTTP fake.

The equivalent manual commands are:

```text
composer test
vendor/bin/pint --test
npm run types
npm run lint:check
npm run format:check
npm run build
```

Codex may run only checks that explicitly target the current task's exact test files, test filters, or changed source files. It must not run `composer test`, bare `php artisan test`, `npm run check`, or any other full PHP or React/frontend suite. At task completion, Codex provides a manual checklist of at most three relevant items: `composer test` for PHP/backend work, `npm run check` for React/frontend work, and a concise manual UI flow only when the task needs one.

Use `npm run lint` or `npm run format` only when intentionally applying fixes. They modify source files.

For local environment diagnostics, separately run:

```text
php artisan about
php artisan migrate:status
```

Verify manually:

- `http://nishetube.test` loads;
- register/login/logout work;
- queue jobs move from queued to completed;
- database and cache connections work;
- the YouTube connectivity test reports success without revealing the key.

## 9. Git hygiene

- Initialize Git before product implementation if it is not already initialized.
- Confirm `.env`, generated exports, logs, caches, and local database artifacts are ignored.
- Make small commits by complete vertical slice.
- Do not commit downloaded API payloads containing unnecessary user/provider data.

## 10. Backup, recovery, and troubleshooting

Before migrations, dependency upgrades, retention execution, or other material local changes, back up both MySQL and private generated exports. Database rows alone do not contain the CSV/XLSX file bytes.

Follow [Backup, restore, and troubleshooting](11_BACKUP_AND_RECOVERY.md) for the verified PowerShell workflow, isolated expansion-migration rehearsal, restore safety checks, quota reset reference, and common failure recovery.
