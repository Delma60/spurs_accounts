<?php

return [

    // Shared secret that signs the SSO cookie. MUST be identical across every
    // first-party Spurs app so they can all verify the same session.
    'secret' => env('SPURS_SESSION_SECRET'),

    // Name of the shared session cookie.
    'cookie' => env('SPURS_SESSION_COOKIE', 'spurs_session'),

    // Cookie domain. Null in dev = host-only (shared across localhost:* apps).
    // In production set to ".spurs.com.ng" so every subdomain receives it.
    'cookie_domain' => env('SPURS_COOKIE_DOMAIN'),
    'cookie_secure' => env('SPURS_COOKIE_SECURE', false),

    // First-party OIDC client IDs. These use the shared cookie, not tokens, so
    // they are hidden from the user's "Connected apps" list (third-party only).
    'first_party_client_ids' => array_filter(explode(',', (string) env('SPURS_FIRST_PARTY_CLIENTS', ''))),

];
