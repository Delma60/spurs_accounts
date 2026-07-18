# Spurs Accounts

The identity provider for **Spurs Cloud** — the single sign-on service every Spurs
property (VTU, console, hosting, …) authenticates against, the way
`accounts.google.com` works for Google. One account, one login, everywhere.

It is a **Laravel + Passport** OAuth2 / OpenID Connect server with an
**Inertia + React** frontend styled after Google's account UI.

---

## What it does

- **Single sign-on** — apps redirect users here to sign in, then receive an
  authorization code (OAuth2 Authorization Code flow via Laravel Passport).
- **OpenID Connect** — discovery, JWKS and a UserInfo endpoint so any
  standards-compliant client can "Sign in with Spurs".
- **Account management** (`/me`) — a Google-My-Account-style dashboard: personal
  info, security & sign-in (change password), and connected apps (view / revoke).
- **Full auth surface** — login, register, forgot/reset password.

## Tech stack

| Layer | Choice |
|-------|--------|
| Framework | Laravel 12 (PHP 8.2) |
| OAuth2 / OIDC | Laravel Passport 13 |
| Frontend | Inertia.js + React 19 (Vite 7) |
| Styling | Self-contained CSS (Roboto), no UI-package dependency yet |
| Icons | lucide-react |
| Database | MySQL (`spurs_accounts`) |

> The shared `@spurs-cloud/ui` design system is **not** a dependency here yet
> (it isn't published). The auth/account styling is copied locally in
> `resources/css/` and can be swapped for the package later.

## Quick start

```bash
composer install
npm install
cp .env.example .env        # then set the DB_* values (see INSTRUCTIONS.md)
php artisan key:generate
php artisan migrate
php artisan install:api --passport      # if OAuth tables aren't set up yet
composer run dev            # server + queue + vite
```

Visit http://127.0.0.1:8000 → redirects to `/login`.
Dev test user (created via seeder/tinker): `azuka@spurs.com.ng` / `password`.

See **INSTRUCTIONS.md** for full setup and common tasks, and **AGENTS.md** for
architecture and conventions.

## Key endpoints

| Route | Purpose |
|-------|---------|
| `/login`, `/register` | Sign in / create account |
| `/forgot-password`, `/reset-password/{token}` | Password reset |
| `/me` | My Account dashboard (auth) |
| `/oauth/authorize` | OAuth2 authorization (consent screen) |
| `/oauth/token` | Token exchange |
| `/oauth/userinfo` | OIDC UserInfo (Bearer token) |
| `/oauth/jwks` | Public signing keys (JWKS) |
| `/.well-known/openid-configuration` | OIDC discovery |

## Tests

```bash
php artisan test
```

Covers auth (login/register/reset), the OIDC endpoints, the consent screen, and
the account dashboard (profile update, password change, app revoke).
