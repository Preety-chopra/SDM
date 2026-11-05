<?php

namespace Tests\Feature\Auth;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\TestWith;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StationControllerPasswordTest extends TestCase
{
    use RefreshDatabase;

    #[TestWith(['station'])]
    #[TestWith(['station_controller'])]
    public function test_station_controller_can_open_the_shared_change_password_form(string $role): void
    {
        $user = $this->controller($role);

        $this->actingAs($user)->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('Update Password')
            ->assertSee('Current Password')
            ->assertSee('New Password')
            ->assertSee('Confirm Password')
            ->assertSee('action="'.route('password.update').'"', false)
            ->assertDontSee('data-app-sidebar', false);
    }

    #[TestWith(['station'])]
    #[TestWith(['station_controller'])]
    #[TestWith(['station_manager'])]
    #[TestWith(['assistant_manager'])]
    public function test_password_change_is_saved_and_audited_without_secrets(string $role): void
    {
        $user = $this->controller($role);
        $otherUser = User::factory()->create();
        $otherPassword = $otherUser->password;

        $this->actingAs($user)->from(route('profile.edit'))
            ->put(route('password.update'), [
                'current_password' => 'password',
                'password' => 'Abcdef1!',
                'password_confirmation' => 'Abcdef1!',
                'user_id' => $otherUser->id,
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'password-updated')
            ->assertRedirect(route('profile.edit'));

        $this->assertTrue(Hash::check('Abcdef1!', $user->refresh()->password));
        $this->assertSame($otherPassword, $otherUser->refresh()->password);

        $audit = AuditLog::query()->where('event', 'user.password_changed')->sole();
        $this->assertSame($user->id, $audit->actor_id);
        $this->assertSame($user->getMorphClass(), $audit->auditable_type);
        $this->assertSame($user->id, $audit->auditable_id);
        $this->assertNull($audit->before_values);
        $this->assertSame(['password_changed' => true], $audit->after_values);
    }

    #[TestWith(['station', 'wrong-password', 'Abcdef1!', 'Abcdef1!', 'current_password'])]
    #[TestWith(['station_controller', 'wrong-password', 'Abcdef1!', 'Abcdef1!', 'current_password'])]
    #[TestWith(['station', 'password', 'Abcdef1!', 'different-password', 'password'])]
    #[TestWith(['station_controller', 'password', 'Abcdef1!', 'different-password', 'password'])]
    #[TestWith(['station', 'password', 'short', 'short', 'password'])]
    public function test_invalid_password_changes_leave_the_password_unchanged(
        string $role,
        string $currentPassword,
        string $password,
        string $confirmation,
        string $error,
    ): void {
        $user = $this->controller($role);
        $originalPassword = $user->password;

        $this->actingAs($user)->from(route('profile.edit'))
            ->put(route('password.update'), [
                'current_password' => $currentPassword,
                'password' => $password,
                'password_confirmation' => $confirmation,
            ])
            ->assertSessionHasErrorsIn('updatePassword', $error)
            ->assertRedirect(route('profile.edit'));

        $this->assertSame($originalPassword, $user->refresh()->password);
        $this->assertDatabaseMissing('audit_logs', ['event' => 'user.password_changed']);
    }

    private function controller(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate($role));

        return $user;
    }
}
