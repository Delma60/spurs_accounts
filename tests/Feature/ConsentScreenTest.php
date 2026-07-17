<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Laravel\Passport\ClientRepository;
use Tests\TestCase;

class ConsentScreenTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorize_redirects_guests_to_login(): void
    {
        $client = app(ClientRepository::class)->createAuthorizationCodeGrantClient(
            'Spurs VTU',
            ['http://localhost:3001/auth/spurs/callback'],
            true,
        );

        $this->get('/oauth/authorize?'.http_build_query([
            'client_id' => $client->getKey(),
            'redirect_uri' => 'http://localhost:3001/auth/spurs/callback',
            'response_type' => 'code',
            'scope' => '',
        ]))->assertRedirect('/login');
    }

    public function test_consent_screen_renders_for_authenticated_user(): void
    {
        $user = User::factory()->create();
        $client = app(ClientRepository::class)->createAuthorizationCodeGrantClient(
            'Spurs VTU',
            ['http://localhost:3001/auth/spurs/callback'],
            true,
        );

        $response = $this->actingAs($user)->get('/oauth/authorize?'.http_build_query([
            'client_id' => $client->getKey(),
            'redirect_uri' => 'http://localhost:3001/auth/spurs/callback',
            'response_type' => 'code',
            'scope' => '',
        ]));

        $response->assertStatus(200);
        $response->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('Auth/Consent')
                ->where('client.name', 'Spurs VTU')
                ->where('user.email', $user->email)
        );
    }
}
