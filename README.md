# Goat Health and Deworming Survey

Offline-first field survey for gastrointestinal nematode research in goats in Sundarbazar Municipality, Lamjung, Nepal.

Researchers sign in while online, then record farm (Section A) and individual goat (Section B) surveys on the device. Every answer is stored in IndexedDB. When the phone can reach the server again, the app sends those records to the Laravel API, which stores them in MySQL. A record is marked synced only after the server confirms it. Repeating a sync does not create duplicates.

## Stack

- Laravel 12, Sanctum token authentication, MySQL
- Vue 3, Vue Router, Pinia, Tailwind CSS
- IndexedDB through Dexie.js
- Installable PWA with a Workbox service worker

## Requirements

- PHP 8.2 or newer, with `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, and `curl`
- Composer 2
- Node.js 20 or newer
- MySQL 8

## MySQL

Create the database:

```sql
CREATE DATABASE goat_survey CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Copy the environment file and set the database password:

```bash
cp .env.example .env
php artisan key:generate
```

`.env.example` is set to MySQL. The local `.env` created with this project uses SQLite so the app can run before a MySQL password is available. For the research database, comment out the SQLite lines in `.env`, uncomment the MySQL lines from `.env.example`, set `DB_PASSWORD`, then run the migrate and seed commands again. Do not point fieldwork at SQLite.

## Install

```bash
composer install
npm install
php artisan migrate --force
php artisan db:seed --force
npm run build
php artisan serve --host=0.0.0.0 --port=8000
```

Open `http://127.0.0.1:8000`.

During development, `composer run dev` starts the PHP server and Vite together. Offline installation and the service worker are produced by `npm run build`, so use the built assets when testing the field workflow.

## Seeded accounts

Change these before any real fieldwork or before placing the server on a network other people can reach.

| Role | Email | Password |
| --- | --- | --- |
| Administrator | admin@sundarbazar.test | ChangeMe-Admin-2026 |
| Researcher | researcher@sundarbazar.test | ChangeMe-Field-2026 |

Administrators can add and deactivate researcher accounts. Deactivating an account revokes its API tokens. Survey rows already stored are not deleted.

## Field workflow

1. Sign in while the device has internet.
2. In the browser menu, choose **Add to Home Screen** (iPhone: Share, then Add to Home Screen). Open the installed app once while online so the shell is cached.
3. Create a farm. Section A is entered once.
4. Open the farm and add one record for each sampled goat. Section B, including last deworming, medicine, and treatments in the past 12 months, is stored on the goat.
5. The farm can be saved before any goat is added.
6. Drafts and finished records stay in IndexedDB if the browser closes, the phone restarts, or the network disappears.
7. Use **Sync** when the server is reachable, or leave the app open and it will try about every 30 seconds. The app checks `/api/health` before sending data. It does not treat the browser “online” flag as proof of a connection.
8. Export the farm dataset and the goat dataset from Reports. Device exports include records that are not synced yet. Server exports read MySQL and need a connection.

Ward numbers are limited to 1–11, the wards of Sundarbazar Municipality.

## What “synced” means

Local statuses are `local` (draft), `pending`, `syncing`, `synced`, `failed`, and `conflict`.

- Farms are sent before their goats.
- The server upserts by UUID inside a transaction. Sending the same UUID again updates that row instead of inserting another one.
- The server version token is `updated_at`. A device that has an older token, and whose answers differ, receives `conflict`. Nothing on the server is overwritten until someone chooses **Keep this device** or **Use server copy**.
- Deleting a record that has never been submitted removes it from the device only. Deleting a submitted record archives it (`deleted_at`) on the server at the next sync, together with that farm’s goats.

## Counts

```bash
php artisan survey:counts
```

## Tests

```bash
php artisan test
```

The feature tests cover sign-in, ward validation, parent-farm ordering, conflict handling, farm deletion, CSV and Excel export, and an idempotent sync of 10 farms with 50 goats.

## Deployment

Use HTTPS. A phone will only install the PWA and keep the service worker on a secure origin. `localhost` is the exception used for development.

Point the web root at `public`. Do not expose `.env`.

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

The sync API is synchronous. A queue worker is not required for survey upload. Schedule nothing that runs `migrate:fresh` against the research database.

After deployment, sign in once from each field phone while it is online, install the app, and confirm the home screen opens the survey.

## Project layout

- `app/Services/SyncService.php` — idempotent MySQL upsert and conflict rules
- `app/Http/Controllers/Api` — authentication, farms, goats, sync, reports, users
- `resources/js/db` — Dexie schema
- `resources/js/services/sync.js` — connectivity check, push, pull, and retry
- `resources/sw.js` — app-shell cache; API requests are never served from cache
