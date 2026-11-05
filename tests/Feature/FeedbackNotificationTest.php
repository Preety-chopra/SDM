<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Feedback;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class FeedbackNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_login_shows_a_clickable_new_feedback_card_once_without_acknowledging_it(): void
    {
        $admin = $this->admin();
        Feedback::factory()->count(2)->create(['message' => 'Private feedback content']);

        $this->post(route('login'), ['username' => $admin->name, 'password' => 'password'])
            ->assertRedirect()->assertSessionHas('new_feedback_count', 2);

        $response = $this->get(route('dashboard'))->assertOk()
            ->assertSee('New feedback received')->assertDontSee('Private feedback content');
        $this->assertMatchesRegularExpression(
            '/<a[^>]*href="'.preg_quote(route('feedback.index'), '/').'"[^>]*>\s*<dl>\s*<dt[^>]*>New feedback<\/dt>\s*<dd[^>]*>2<\/dd>/s',
            $response->getContent(),
        );
        $this->get(route('dashboard'))->assertOk()->assertDontSee('New feedback received');

        $this->post(route('logout'));
        $this->post(route('login'), ['username' => $admin->name, 'password' => 'password'])
            ->assertSessionHas('new_feedback_count', 2);
    }

    public function test_admin_login_has_no_feedback_popup_when_no_feedback_has_been_received(): void
    {
        $admin = $this->admin();

        $this->post(route('login'), ['username' => $admin->name, 'password' => 'password'])
            ->assertRedirect()->assertSessionMissing('new_feedback_count');

        $this->get(route('dashboard'))->assertOk()->assertDontSee('New feedback received');
    }

    public function test_visiting_feedback_acknowledges_it_only_for_that_admin_and_later_submissions_are_new(): void
    {
        $admin = $this->admin();
        $otherAdmin = $this->admin();
        $feedback = Feedback::factory()->create();

        $this->actingAs($admin)->get(route('feedback.index'))->assertOk()->assertSee($feedback->message);

        $this->assertSame($feedback->id, $admin->refresh()->feedback_last_seen_id);
        $this->assertSame(0, $otherAdmin->refresh()->feedback_last_seen_id);
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $admin->id,
            'event' => 'feedback.notifications_acknowledged',
            'after_values->feedback_last_seen_id' => $feedback->id,
        ]);
        $this->post(route('logout'));
        $this->post(route('login'), ['username' => $admin->name, 'password' => 'password'])
            ->assertSessionMissing('new_feedback_count');
        $this->post(route('logout'));
        $this->post(route('login'), ['username' => $otherAdmin->name, 'password' => 'password'])
            ->assertSessionHas('new_feedback_count', 1);
        $this->post(route('logout'));
        Feedback::factory()->create();

        $this->post(route('login'), ['username' => $admin->name, 'password' => 'password'])
            ->assertSessionHas('new_feedback_count', 1);
    }

    #[TestWith(['assistant_manager'])]
    #[TestWith(['cmc_staff'])]
    public function test_non_admin_login_clears_stale_feedback_notifications(string $role): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->assignRole($role);
        Feedback::factory()->create();

        $this->withSession(['new_feedback_count' => 99])
            ->post(route('login'), ['username' => $user->name, 'password' => 'password'])
            ->assertRedirect()->assertSessionMissing('new_feedback_count');

        $this->get(route('dashboard'))->assertOk()->assertDontSee('New feedback received');
        $this->get(route('feedback.index'))->assertForbidden();
        $this->assertSame(0, $user->refresh()->feedback_last_seen_id);
    }

    public function test_opening_an_older_feedback_page_does_not_acknowledge_new_submissions(): void
    {
        $admin = $this->admin();
        Feedback::factory()->count(26)->create();

        $this->actingAs($admin)->get(route('feedback.index', ['page' => 2]))->assertOk();

        $this->assertSame(0, $admin->refresh()->feedback_last_seen_id);
        $this->assertDatabaseMissing('audit_logs', ['event' => 'feedback.notifications_acknowledged']);
    }

    private function admin(): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole(UserRole::Administrator->value);

        return $admin;
    }
}
