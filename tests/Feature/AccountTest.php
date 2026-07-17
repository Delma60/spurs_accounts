<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class AccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_root_redirects_signed_in_user_to_me(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/')->assertRedirect('/me');
    }

    public function test_me_requires_authentication(): void
    {
        $this->get('/me')->assertRedirect('/login');
    }

    public function test_account_page_renders_with_user(): void
    {
        $user = User::factory()->create(['name' => 'Azuka Genius']);

        $this->actingAs($user)->get('/me')
            ->assertStatus(200)
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Account/Home')
                ->where('user.name', 'Azuka Genius')
                ->has('connectedApps'));
    }

    public function test_user_can_update_their_name(): void
    {
        $user = User::factory()->create(['name' => 'Old Name']);

        $this->actingAs($user)->put('/me/profile', ['name' => 'New Name'])
            ->assertRedirect();

        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'New Name']);
    }

    public function test_user_can_change_password_with_correct_current_password(): void
    {
        $user = User::factory()->create(['password' => bcrypt('OldPassword1!')]);

        $this->actingAs($user)->put('/me/password', [
            'current_password' => 'OldPassword1!',
            'password' => 'BrandNew123!',
            'password_confirmation' => 'BrandNew123!',
        ])->assertRedirect()->assertSessionHasNoErrors();
    }

    public function test_password_change_rejects_wrong_current_password(): void
    {
        $user = User::factory()->create(['password' => bcrypt('OldPassword1!')]);

        $this->actingAs($user)->put('/me/password', [
            'current_password' => 'wrong',
            'password' => 'BrandNew123!',
            'password_confirmation' => 'BrandNew123!',
        ])->assertSessionHasErrors('current_password');
    }
}
