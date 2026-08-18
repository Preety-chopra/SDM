<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_homepage_displays_the_login_screen(): void
    {
        $response = $this->get('/');

        $response
            ->assertOk()
            ->assertSee('Welcome back')
            ->assertSee('Sign in to your account')
            ->assertDontSee('Every decision starts with a clearer view')
            ->assertSee('name="username"', false)
            ->assertSee('name="password"', false)
            ->assertDontSee('name="line_id"', false)
            ->assertSee('action="'.route('login').'"', false);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create(['name' => 'Central Station']);

        $response = $this->post('/login', [
            'username' => $user->name,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'username' => $user->name,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_disabled_users_can_not_authenticate(): void
    {
        $user = User::factory()->disabled()->create();

        $this->post('/login', [
            'username' => $user->name,
            'password' => 'password',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_not_authenticate_using_their_email_address(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'username' => $user->email,
            'password' => 'password',
        ])->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    public function test_disabled_authenticated_users_are_logged_out_on_their_next_request(): void
    {
        $user = User::factory()->disabled()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }
}
