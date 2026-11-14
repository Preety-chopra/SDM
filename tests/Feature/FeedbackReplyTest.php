<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Feedback;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestWith;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FeedbackReplyTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sends_replies_to_the_original_sender_and_they_are_audited(): void
    {
        $admin = $this->admin();
        $sender = User::factory()->create();
        $otherUser = User::factory()->create();
        $feedback = Feedback::factory()->for($sender)->create();
        $reply = '<script>alert("reply")</script> We fixed the issue.';
        $url = "/admin/feedback/{$feedback->id}";

        $this->actingAs($admin)->get('/admin/feedback')->assertOk()->assertSee($url);
        $this->get($url)->assertOk()->assertSee($feedback->message)->assertSee('Send reply');
        $this->post($url.'/replies', ['reply' => $reply, 'user_id' => $otherUser->id, 'author_id' => $otherUser->id])
            ->assertRedirect($url)->assertSessionHas('status', 'Reply sent.');
        $this->post($url.'/replies', ['reply' => 'Please try again.'])->assertRedirect($url);
        $this->get($url)->assertOk()->assertSeeInOrder([$reply, 'Please try again.'])->assertDontSee($reply, false);

        $this->assertDatabaseHas('feedback_replies', [
            'feedback_id' => $feedback->id,
            'author_id' => $admin->id,
            'message' => $reply,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $admin->id,
            'auditable_id' => $feedback->id,
            'auditable_type' => $feedback->getMorphClass(),
            'event' => 'feedback.replied',
            'after_values->recipient_id' => $sender->id,
        ]);

        $this->actingAs($otherUser)->get('/feedback/replies')->assertOk()->assertDontSee($feedback->message)->assertDontSee($reply);
        $this->actingAs($sender)->get(route('profile.edit'))->assertOk()->assertSee('2 unread feedback replies');
        $this->get('/feedback/replies')->assertOk()->assertSee($feedback->message)->assertSee($reply)->assertDontSee($reply, false);
        $this->get(route('profile.edit'))->assertOk()->assertDontSee('unread feedback replies');
    }

    #[TestWith([UserRole::Station->value])]
    #[TestWith(['station_controller'])]
    public function test_station_controller_can_open_only_their_own_feedback_inbox(string $role): void
    {
        $admin = $this->admin();
        $sender = User::factory()->create();
        $sender->assignRole(Role::findOrCreate($role));
        $feedback = Feedback::factory()->for($sender)->create();
        $feedback->replies()->create(['author_id' => $admin->id, 'message' => 'Reply for this controller.']);
        $otherFeedback = Feedback::factory()->create();
        $otherFeedback->replies()->create(['author_id' => $admin->id, 'message' => 'Reply for another account.']);

        $this->actingAs($sender)->get('/feedback/replies')->assertOk()
            ->assertSee('Reply for this controller.')
            ->assertDontSee('Reply for another account.');
        $this->get(route('feedback.index'))->assertForbidden();
        $this->post(route('feedback.reply', $feedback), ['reply' => 'Forged administrator reply.'])->assertForbidden();
    }

    public function test_non_administrators_cannot_view_admin_feedback_details_or_send_replies_even_to_themselves(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->assignRole(UserRole::CmcStaff->value);
        $feedback = Feedback::factory()->for($user)->create();
        $url = "/admin/feedback/{$feedback->id}";

        $this->post($url.'/replies', ['reply' => 'A forged reply.'])->assertRedirect(route('login'));
        $this->get('/feedback/replies')->assertRedirect(route('login'));
        $this->actingAs($user)->get($url)->assertForbidden();
        $this->post($url.'/replies', ['reply' => 'A forged reply.'])->assertForbidden();

        $this->assertDatabaseCount('feedback_replies', 0);
        $this->assertDatabaseMissing('audit_logs', ['event' => 'feedback.replied']);
    }

    #[TestWith(['  ', 'The reply field is required.'])]
    #[TestWith([['bad'], 'The reply field must be a string.'])]
    #[TestWith([null, 'The reply field must not be greater than 5000 characters.'])]
    public function test_invalid_reply_is_rejected_without_delivering_it(mixed $reply, string $error): void
    {
        $admin = $this->admin();
        $feedback = Feedback::factory()->create();
        $url = "/admin/feedback/{$feedback->id}";

        $this->actingAs($admin)->from($url)->post($url.'/replies', ['reply' => $reply ?? str_repeat('a', 5001)])
            ->assertRedirect($url)->assertSessionHasErrors(['reply' => $error]);

        $this->assertDatabaseCount('feedback_replies', 0);
        $this->assertDatabaseMissing('audit_logs', ['event' => 'feedback.replied']);
    }

    public function test_admin_can_review_feedback_from_a_deleted_sender_but_cannot_reply(): void
    {
        $admin = $this->admin();
        $feedback = Feedback::factory()->create(['user_id' => null]);
        $url = "/admin/feedback/{$feedback->id}";

        $this->actingAs($admin)->get($url)->assertOk()->assertSee($feedback->message)->assertDontSee('Send reply');
        $this->post($url.'/replies', ['reply' => 'No recipient.'])->assertForbidden();

        $this->assertDatabaseCount('feedback_replies', 0);
    }

    public function test_original_sender_can_follow_up_after_an_administrator_replies(): void
    {
        $admin = $this->admin();
        $sender = User::factory()->create();
        $sender->assignRole(UserRole::Station->value);
        $feedback = Feedback::factory()->for($sender)->create();
        $adminUrl = route('feedback.show', $feedback);
        $threadUrl = url("/feedback/{$feedback->id}/replies");
        $message = '<script>alert(1)</script> The issue still occurs.';

        $this->actingAs($admin)->post(route('feedback.reply', $feedback), ['reply' => 'Please try again.'])->assertRedirect($adminUrl);
        $this->actingAs($sender)->get(route('feedback.inbox'))->assertOk()->assertSee($threadUrl);
        $this->get($threadUrl)->assertOk()->assertSee('Please try again.')->assertSee('Send reply');
        $this->from($threadUrl)->post($threadUrl, ['reply' => '  '])->assertRedirect($threadUrl)->assertSessionHasErrors('reply');
        $this->post($threadUrl, ['reply' => $message, 'author_id' => $admin->id])->assertRedirect($threadUrl);
        $this->get($threadUrl)->assertOk()->assertSeeInOrder(['Administrator', 'Please try again.', 'You', $message])->assertDontSee($message, false);
        $this->get(route('profile.edit'))->assertOk()->assertDontSee('unread feedback replies');

        $this->assertDatabaseHas('feedback_replies', ['feedback_id' => $feedback->id, 'author_id' => $sender->id, 'message' => $message]);
        $this->assertDatabaseHas('audit_logs', ['actor_id' => $sender->id, 'auditable_id' => $feedback->id, 'event' => 'feedback.followed_up']);

        $this->actingAs($admin)->get(route('feedback.index'))->assertOk()->assertSee('New follow-up');
        $this->get($adminUrl)->assertOk()->assertSeeInOrder(['Please try again.', $message])->assertDontSee($message, false);
        $this->get(route('feedback.index'))->assertOk()->assertDontSee('New follow-up');
        $this->post(route('feedback.reply', $feedback), ['reply' => 'We are looking into it.'])->assertRedirect($adminUrl);
        $this->actingAs($sender)->get($threadUrl)->assertOk()->assertSeeInOrder(['Please try again.', $message, 'We are looking into it.']);
    }

    public function test_sender_cannot_follow_up_before_an_administrator_has_replied(): void
    {
        $sender = User::factory()->create();
        $feedback = Feedback::factory()->for($sender)->create();

        $this->actingAs($sender)->post("/feedback/{$feedback->id}/replies", ['reply' => 'Premature follow-up.'])->assertForbidden();

        $this->assertDatabaseCount('feedback_replies', 0);
        $this->assertDatabaseMissing('audit_logs', ['event' => 'feedback.followed_up']);
    }

    public function test_other_accounts_cannot_read_or_reply_to_the_senders_conversation(): void
    {
        $admin = $this->admin();
        $feedback = Feedback::factory()->create();
        $feedback->replies()->create(['author_id' => $admin->id, 'message' => 'Private reply.']);
        $threadUrl = "/feedback/{$feedback->id}/replies";

        $this->post($threadUrl, ['reply' => 'Guest reply.'])->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get($threadUrl)->assertNotFound();
        $this->post($threadUrl, ['reply' => 'Forged follow-up.', 'user_id' => $feedback->user_id])->assertNotFound();

        $this->assertDatabaseCount('feedback_replies', 1);
        $this->assertDatabaseMissing('audit_logs', ['event' => 'feedback.followed_up']);
    }

    private function admin(): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole(UserRole::Administrator->value);

        return $admin;
    }
}
