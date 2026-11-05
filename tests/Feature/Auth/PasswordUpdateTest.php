<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class PasswordUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_can_be_updated(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->put('/password', [
                'current_password' => 'password',
                'password' => 'Abcdef1!',
                'password_confirmation' => 'Abcdef1!',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertTrue(Hash::check('Abcdef1!', $user->refresh()->password));
    }

    #[TestWith(['Abcde1!', 'The password field must be at least 8 characters.'])]
    #[TestWith(['abcdef1!', 'The password field must contain at least one uppercase and one lowercase letter.'])]
    #[TestWith(['ABCDEF1!', 'The password field must contain at least one uppercase and one lowercase letter.'])]
    #[TestWith(['Abcdefg!', 'The password field must contain at least one number.'])]
    #[TestWith(['Abcdef12', 'The password field must contain at least one symbol.'])]
    #[TestWith(['Abcdef1 ', 'The password field must contain at least one symbol.'])]
    public function test_password_changes_require_all_password_requirements(string $password, string $message): void
    {
        $user = User::factory()->create();
        $originalPassword = $user->password;

        $this->actingAs($user)->from('/profile')->put('/password', [
            'current_password' => 'password',
            'password' => $password,
            'password_confirmation' => $password,
        ])->assertSessionHasErrorsIn('updatePassword', ['password' => $message])
            ->assertRedirect('/profile');

        $this->assertSame($originalPassword, $user->refresh()->password);
        $this->assertDatabaseMissing('audit_logs', ['event' => 'user.password_changed']);
    }

    public function test_correct_password_must_be_provided_to_update_password(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->put('/password', [
                'current_password' => 'wrong-password',
                'password' => 'Abcdef1!',
                'password_confirmation' => 'Abcdef1!',
            ]);

        $response
            ->assertSessionHasErrorsIn('updatePassword', 'current_password')
            ->assertRedirect('/profile');
    }
}
