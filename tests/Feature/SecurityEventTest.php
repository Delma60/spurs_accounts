<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityEventTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_records_a_security_event(): void
    {
        $user = User::factory()->create(['password' => bcrypt('Password123!')]);

        $this->post('/login', ['email' => $user->email, 'password' => 'Password123!']);

        $this->assertDatabaseHas('security_events', ['user_id' => $user->id, 'type' => 'login']);
    }

    public function test_registration_records_a_security_event(): void
    {
        $this->post('/register', [
            'name' => 'Grace',
            'email' => 'grace@spurs.com.ng',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $user = User::whereEmail('grace@spurs.com.ng')->first();
        $this->assertDatabaseHas('security_events', ['user_id' => $user->id, 'type' => 'registered']);
    }

    public function test_password_change_records_a_security_event(): void
    {
        $user = User::factory()->create(['password' => bcrypt('OldPassword1!')]);

        $this->actingAs($user)->put('/me/password', [
            'current_password' => 'OldPassword1!',
            'password' => 'BrandNew123!',
            'password_confirmation' => 'BrandNew123!',
        ]);

        $this->assertDatabaseHas('security_events', ['user_id' => $user->id, 'type' => 'password_changed']);
    }

    public function test_account_page_includes_recent_security_events(): void
    {
        $user = User::factory()->create();
        $user->securityEvents()->create(['type' => 'login', 'ip' => '127.0.0.1', 'device' => 'Chrome on Windows']);

        $this->actingAs($user)->get('/me')
            ->assertInertia(fn ($page) => $page->has('securityEvents', 1)
                ->where('securityEvents.0.type', 'login')
                ->where('securityEvents.0.label', 'Signed in'));
    }
}
