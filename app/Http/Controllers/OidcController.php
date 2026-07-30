<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

class OidcController extends Controller
{
    /**
     * OpenID Connect discovery document.
     * Lets relying parties auto-configure via /.well-known/openid-configuration.
     */
    public function configuration()
    {
        // Issuer must share the origin of the endpoints below, so derive both
        // from the same base (respects APP_URL in production, request host in dev).
        $issuer = rtrim(URL::to('/'), '/');

        return response()->json([
            'issuer' => $issuer,
            'authorization_endpoint' => URL::to('/oauth/authorize'),
            'token_endpoint' => URL::to('/oauth/token'),
            'userinfo_endpoint' => URL::to('/oauth/userinfo'),
            'jwks_uri' => URL::to('/oauth/jwks'),
            'response_types_supported' => ['code'],
            'grant_types_supported' => ['authorization_code', 'refresh_token', 'client_credentials'],
            'subject_types_supported' => ['public'],
            'id_token_signing_alg_values_supported' => ['RS256'],
            'scopes_supported' => ['openid', 'profile', 'email', 'roles'],
            'claims_supported' => ['sub', 'name', 'phone_number', 'country', 'currency', 'email', 'email_verified', 'roles', 'permissions'],
            'token_endpoint_auth_methods_supported' => ['client_secret_basic', 'client_secret_post'],
            'code_challenge_methods_supported' => ['S256', 'plain'],
        ]);
    }

    /**
     * JSON Web Key Set — publishes the RSA public key so clients can verify
     * the RS256 tokens Passport signs with the matching private key.
     */
    public function jwks()
    {
        $pem = file_get_contents(storage_path('oauth-public.key'));
        $details = openssl_pkey_get_details(openssl_pkey_get_public($pem));
        $rsa = $details['rsa'];

        $b64url = fn (string $bin) => rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');

        return response()->json([
            'keys' => [[
                'kty' => 'RSA',
                'use' => 'sig',
                'alg' => 'RS256',
                'kid' => substr(hash('sha256', $details['key']), 0, 20),
                'n' => $b64url($rsa['n']),
                'e' => $b64url($rsa['e']),
            ]],
        ]);
    }

    /**
     * UserInfo endpoint — returns the signed-in user's claims for a valid
     * access token, filtered by the scopes that token was granted.
     * This is what powers "Sign in with Spurs".
     */
    public function userinfo(Request $request)
    {
        $user = $request->user();

        $claims = ['sub' => (string) $user->getKey()];

        if ($user->tokenCan('profile')) {
            $claims['name'] = $user->name;
            $claims['phone_number'] = $user->phone;
            // Home country + default currency, so apps like Wallet can start a
            // new user on their own currency without asking.
            $claims['country'] = $user->country;
            $claims['currency'] = $user->currency;
        }

        if ($user->tokenCan('email')) {
            $claims['email'] = $user->email;
            $claims['email_verified'] = $user->hasVerifiedEmail();
        }

        if ($user->tokenCan('roles')) {
            $claims['roles'] = $user->roleNames();
            $claims['permissions'] = $user->permissionKeys();
            $claims['kyc_level'] = $user->kycLevel();
            $claims['kyc_status'] = $user->kycStatus();
        }

        return response()->json($claims);
    }
}
