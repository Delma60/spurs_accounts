# AGENTS.md — Spurs Accounts

Guidance for AI agents (and humans) working in this repo. Read this before
making changes.

## What this is

The **Spurs Cloud identity provider** — Laravel 12 + Passport (OAuth2/OIDC) with
an Inertia + React frontend. Every other Spurs app trusts this service for login.
Think `accounts.google.com`. Part of the wider Spurs Cloud platform (separate
repos per app, protocol-based integration via OIDC).

## Architecture

- **Backend**: Laravel 12, MySQL (`spurs_accounts`), Laravel Passport 13.
  - OAuth2 is Passport's; the **OIDC layer is custom** (`OidcController`):
    discovery, JWKS (from `storage/oauth-public.key`), and UserInfo.
  - `api` guard (driver `passport`) protects `/oauth/userinfo`.
  - OIDC scopes declared in `AppServiceProvider::boot()` via `Passport::tokensCan`.
  - The consent screen is a branded Inertia page wired through
    `Passport::authorizationView()` (also in `AppServiceProvider`).
- **Frontend**: Inertia + React 19, Vite 7, `@vitejs/plugin-react@5`
  (v6 needs Vite 8 — do not upgrade past 5 unless Vite is upgraded too).
  - `@` alias → `resources/js` (configured in `vite.config.js`).
  - Pages in `resources/js/Pages`, shared components in `resources/js/Components`.
- **Styling**: self-contained CSS in `resources/css/` (`auth.css`, `account.css`),
  Google-account-inspired, Roboto webfont, full dark mode via CSS vars.
  Does **not** depend on `@spurs-cloud/ui` (unpublished). Swap later.

## Key files

| File | Role |
|------|------|
| `routes/web.php` | All routes (auth, `/me`, OIDC endpoints) |
| `app/Http/Controllers/Auth/*` | Login, register, password reset |
| `app/Http/Controllers/AccountController.php` | `/me` dashboard + profile/password/app-revoke |
| `app/Http/Controllers/OidcController.php` | discovery / jwks / userinfo |
| `app/Providers/AppServiceProvider.php` | Passport scopes + consent view |
| `bootstrap/app.php` | Inertia middleware, CSRF exceptions, userinfo 401 handler |
| `resources/js/Pages/Account/Home.jsx` | My Account UI (sidebar md/lg, card menu sm/xs) |
| `resources/js/Components/Field.jsx` | Floating-label input |

## Conventions & gotchas

- **Windows dev**: `composer run dev` had `php artisan pail` removed — Pail needs
  the `pcntl` extension which Windows lacks, and `--kill-others` would take the
  whole dev stack down. Keep it out.
- **Composer is slow here** — installs can take minutes; a timeout usually still
  completed. Check `vendor/` before re-running.
- **Rebuild after CSS/JS edits** if viewing built assets: `npm run build`.
  With `composer run dev`, Vite HMR handles it live — but a plain `php artisan
  serve` (no Vite) serves the last `npm run build`, so stale UI = needs rebuild.
- **`/me` responsive rule**: md/lg shows the sidebar (`.acct-nav`) + summary
  cards (`.only-desktop`); sm/xs (≤768px) hides the sidebar and shows the nav as
  a card of rows (`.navlist-card.only-mobile`) with a back button. When editing
  `account.css`, keep base show/hide utilities BEFORE the `@media` block so the
  cascade resolves (media query must win on mobile).
- **`Field` layout**: the input + floating label + toggle live in an inner
  `.field__box`; the error message sits outside it — otherwise the error's height
  shifts the centered label. Don't collapse that structure.
- **UserInfo returns 401**, not a login redirect, when unauthenticated — handled
  by an `AuthenticationException` renderer in `bootstrap/app.php` scoped to
  `oauth/userinfo`. `/oauth/authorize` intentionally still redirects to login.
- **Issuer consistency**: OIDC `issuer` and endpoints are derived from the same
  base (`URL::to('/')`) so they always share an origin.

## Testing

`php artisan test` (in-memory sqlite via `phpunit.xml`, `RefreshDatabase`).
Create OAuth clients in tests with
`app(ClientRepository::class)->createAuthorizationCodeGrantClient(name, [uris], true)`.
Verify OIDC scope filtering with `Passport::actingAs($user, ['profile','email'])`.
Always run the suite after backend changes.

## Do not

- Publish or hard-depend on `@spurs-cloud/ui` until it's on npm.
- Build relying-party (client app) integrations here unless asked — this repo is
  the provider only.
