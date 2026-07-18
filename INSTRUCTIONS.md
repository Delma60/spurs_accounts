# INSTRUCTIONS — setup & common tasks

Step-by-step setup for Spurs Accounts and the day-to-day commands.

## 1. Prerequisites

- PHP 8.2+, Composer 2
- Node 20+ / npm
- MySQL (XAMPP is fine on Windows)

## 2. First-time setup

```bash
# 1. Dependencies
composer install
npm install

# 2. Environment
cp .env.example .env
php artisan key:generate
```

Set the database in `.env`:

```ini
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=spurs_accounts
DB_USERNAME=root
DB_PASSWORD=
```

Create the database and run migrations:

```bash
# XAMPP MySQL example:
/c/xampp/mysql/bin/mysql.exe -u root -e "CREATE DATABASE IF NOT EXISTS spurs_accounts CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

php artisan migrate
php artisan install:api --passport --no-interaction   # OAuth tables + keys
```

Create a dev user:

```bash
php artisan tinker --execute="\App\Models\User::updateOrCreate(['email'=>'azuka@spurs.com.ng'],['name'=>'Azuka','password'=>bcrypt('password')]);"
```

## 3. Run it

```bash
composer run dev        # runs server + queue + vite (Windows-safe, no Pail)
```

- App: http://127.0.0.1:8000
- Sign in with `azuka@spurs.com.ng` / `password`

> If you run only `php artisan serve` (no Vite), the app serves the last
> `npm run build`. Run `npm run build` after editing React/CSS, or use
> `composer run dev` for live reload.

## 4. Tests

```bash
php artisan test                       # everything
php artisan test --filter=OidcTest     # one suite
```

## 5. Common tasks

**Register an OAuth client (for an app that will "Sign in with Spurs")**

```bash
php artisan passport:client \
  --name="My App" \
  --redirect_uri="https://myapp.example/auth/callback" \
  --no-interaction
```

Prints the client id + secret (secret shown once). The app then uses the standard
Authorization Code flow against:

- authorize: `GET /oauth/authorize`
- token: `POST /oauth/token`
- userinfo: `GET /oauth/userinfo` (Bearer token)
- discovery: `GET /.well-known/openid-configuration`

**Regenerate Passport keys** (if `storage/oauth-*.key` are missing)

```bash
php artisan passport:keys
```

**Rebuild frontend assets**

```bash
npm run build
```

## 6. Troubleshooting

| Symptom | Fix |
|---------|-----|
| UI looks stale / wrong layout | `npm run build`, then hard-refresh (Ctrl+Shift+R) |
| `composer run dev` crashes on Pail / pcntl | Already removed from the `dev` script; don't re-add |
| `/oauth/userinfo` redirects instead of 401 | Ensure the `bootstrap/app.php` exception renderer for `oauth/userinfo` is present |
| Composer command "times out" | It's slow on Windows; it usually finished — check `vendor/` |
