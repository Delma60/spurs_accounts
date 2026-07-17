<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class OidcTest extends TestCase
{
    use RefreshDatabase;

    public function test_discovery_document_advertises_endpoints(): void
    {
        $this->getJson('/.well-known/openid-configuration')
            ->assertStatus(200)
            ->assertJsonStructure([
                'issuer',
                'authorization_endpoint',
                'token_endpoint',
                'userinfo_endpoint',
                'jwks_uri',
                'scopes_supported',
            ])
            ->assertJsonPath('scopes_supported', ['openid', 'profile', 'email']);
    }

    public function test_jwks_publishes_an_rsa_key(): void
    {
        $this->getJson('/oauth/jwks')
            ->assertStatus(200)
            ->assertJsonStructure(['keys' => [['kty', 'use', 'alg', 'kid', 'n', 'e']]])
            ->assertJsonPath('keys.0.kty', 'RSA');
    }

    public function test_userinfo_requires_a_token(): void
    {
        $this->getJson('/oauth/userinfo')->assertStatus(401);
    }

    public function test_userinfo_returns_only_sub_without_profile_or_email_scope(): void
    {
        $user = User::factory()->create();
        Passport::actingAs($user, ['openid']);

        $this->getJson('/oauth/userinfo')
            ->assertStatus(200)
            ->assertJsonPath('sub', (string) $user->getKey())
            ->assertJsonMissingPath('name')
            ->assertJsonMissingPath('email');
    }

    public function test_userinfo_returns_claims_for_granted_scopes(): void
    {
        $user = User::factory()->create(['name' => 'Azuka', 'email' => 'azuka@spurs.com.ng']);
        Passport::actingAs($user, ['openid', 'profile', 'email']);

        $this->getJson('/oauth/userinfo')
            ->assertStatus(200)
            ->assertJsonPath('sub', (string) $user->getKey())
            ->assertJsonPath('name', 'Azuka')
            ->assertJsonPath('email', 'azuka@spurs.com.ng')
            ->assertJsonPath('email_verified', true); // factory sets email_verified_at
    }
}
