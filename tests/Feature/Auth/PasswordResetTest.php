<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_password_link_screen_can_be_rendered(): void
    {
        $response = $this->get('/forgot-password');

        $response->assertStatus(200);
    }

    public function test_reset_password_link_can_be_requested(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_reset_password_screen_can_be_rendered(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) {
            $response = $this->get('/reset-password/'.$notification->token);

            $response->assertStatus(200);

            return true;
        });
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            $response = $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'Abcdef1!',
                'password_confirmation' => 'Abcdef1!',
            ]);

            $response
                ->assertSessionHasNoErrors()
                ->assertRedirect(route('login'));

            $this->assertTrue(Hash::check('Abcdef1!', $user->refresh()->password));

            return true;
        });
    }

    public function test_password_reset_rejects_a_password_without_a_symbol_and_preserves_the_token(): void
    {
        $user = User::factory()->create();
        $originalPassword = $user->password;
        $token = Password::createToken($user);

        $this->from(route('password.reset', $token))->post(route('password.store'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'Abcdef12',
            'password_confirmation' => 'Abcdef12',
        ])->assertSessionHasErrors(['password' => 'The password field must contain at least one symbol.'])
            ->assertRedirect(route('password.reset', $token));

        $this->assertSame($originalPassword, $user->refresh()->password);
        $this->assertTrue(Password::tokenExists($user, $token));
    }
}
