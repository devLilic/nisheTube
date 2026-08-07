# Local Setup

## 1. Confirmed environment

| Component | Local value |
|---|---|
| Project path | `C:\laragon\www\NisheTube` |
| Laragon terminal | `C:\laragon\bin\cmder\Cmder.exe` |
| PHP | 8.3.30 x64 |
| Composer | 2.9.4 |
| Node.js | 22.22.0 |
| MySQL | 8.4.3 |
| Laravel | 13.8+ within major 13 |

## 2. Foundation status

The folder currently contains the plain Laravel application skeleton and installed PHP/Node dependencies. It does not yet contain the official React/Inertia starter-kit application structure. Backlog task `FND-01` establishes that foundation before product modules.

Because the current repository has no product code, the implementation agent may adopt the official Laravel 13 React starter-kit scaffold. It must preserve this documentation and inspect local changes before replacing framework scaffold files.

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

## 5. Daily development workflow

After the foundation task defines the final scripts:

```text
composer install
npm install
php artisan migrate
composer run dev
```

The development command should run Laravel, the queue worker, logs, and Vite. Laragon may serve the site as `http://nishetube.test`; avoid running a second web server if it conflicts with the Laragon virtual host.

## 6. Scheduler and retention on a local PC

The computer is not expected to run continuously. Retention therefore needs all three entry points:

- an Artisan command with `--dry-run` and execution modes;
- a scheduled definition for periods when `schedule:work` is active;
- a Settings UI reminder/action when cleanup is due.

The default eligibility cutoff is six calendar months before the UTC execution time.

## 7. YouTube API setup

1. Create or choose a Google Cloud project.
2. Enable YouTube Data API v3.
3. Create an API key for public-data requests.
4. Restrict the key to YouTube Data API v3.
5. Store it only in local `.env`.
6. Use the NisheTube Settings connectivity test.

Do not use browser HTTP-referrer restrictions for server-side Laravel requests. IP restrictions may be impractical on a changing residential connection; API restriction is still required.

## 8. Verification

Expected final baseline:

```text
php artisan about
php artisan migrate:status
composer test
vendor/bin/pint --test
npm run types
npm run lint
npm run build
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

