<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Feedback;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FeedbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_submit_feedback_with_the_page_url_and_cannot_impersonate_another_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $url = route('profile.edit').'?tab=password#details';

        $this->actingAs($user)->postJson('/feedback', [
            'message' => 'The instructions could be clearer.',
            'page_url' => $url,
            'user_id' => $other->id,
        ])->assertCreated()->assertJsonPath('message', 'Thank you. Your feedback has been submitted.');

        $feedback = Feedback::query()->sole();
        $this->assertSame($user->id, $feedback->user_id);
        $this->assertSame('The instructions could be clearer.', $feedback->message);
        $this->assertSame($url, $feedback->page_url);
        $audit = AuditLog::query()->where('event', 'feedback.submitted')->sole();
        $this->assertSame($user->id, $audit->actor_id);
        $this->assertSame($feedback->getMorphClass(), $audit->auditable_type);
        $this->assertSame($feedback->id, $audit->auditable_id);
        $this->assertSame(['user_id' => $user->id], $audit->after_values);
    }

    public function test_feedback_requires_a_message_and_page_url(): void
    {
        $this->actingAs(User::factory()->create())->postJson('/feedback', [])
            ->assertUnprocessable()->assertJsonValidationErrors(['message', 'page_url']);

        $this->assertDatabaseCount('feedback', 0);
    }

    #[DataProvider('invalidFeedback')]
    public function test_invalid_feedback_is_rejected(array $data, string $field): void
    {
        $this->actingAs(User::factory()->create())->postJson('/feedback', [
            'message' => 'Please improve this page.',
            'page_url' => route('profile.edit'),
            ...$data,
        ])->assertUnprocessable()->assertJsonValidationErrors($field);

        $this->assertDatabaseCount('feedback', 0);
        $this->assertDatabaseMissing('audit_logs', ['event' => 'feedback.submitted']);
    }

    public static function invalidFeedback(): array
    {
        return [
            'blank message' => [['message' => '   '], 'message'],
            'long message' => [['message' => str_repeat('a', 5001)], 'message'],
            'array message' => [['message' => ['bad']], 'message'],
            'external url' => [['page_url' => 'https://example.org/page'], 'page_url'],
            'javascript url' => [['page_url' => 'javascript:alert(1)'], 'page_url'],
            'relative url' => [['page_url' => '/dashboard'], 'page_url'],
            'deceptive host' => [['page_url' => 'http://localhost.evil.test/dashboard'], 'page_url'],
            'long url' => [['page_url' => 'http://localhost/'.str_repeat('a', 2048)], 'page_url'],
        ];
    }

    public function test_guests_and_disabled_users_cannot_submit_or_read_feedback(): void
    {
        $this->postJson('/feedback', [])->assertUnauthorized();
        $this->get('/admin/feedback')->assertRedirect(route('login'));
        $disabled = User::factory()->disabled()->create();
        $this->actingAs($disabled)->postJson('/feedback', [
            'message' => 'Blocked', 'page_url' => route('dashboard'),
        ])->assertRedirect(route('login'));
        $this->actingAs($disabled)->get('/admin/feedback')->assertRedirect(route('login'));

        $this->assertDatabaseCount('feedback', 0);
    }

    #[DataProvider('roles')]
    public function test_feedback_is_available_in_the_header_and_only_administrators_can_review_it(UserRole $role): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->assignRole($role->value);

        $response = $this->actingAs($user)->get(route('sdm-memos.index'))
            ->assertOk()->assertSee('data-feedback-trigger', false)->assertSee('data-feedback-dialog', false);

        $this->postJson('/feedback', ['message' => 'Feedback from my role.', 'page_url' => route('sdm-memos.index')])
            ->assertCreated();

        $this->assertSame($role === UserRole::Administrator, $user->can('viewAny', Feedback::class));
        if ($role === UserRole::Administrator) {
            $response->assertSee('href="'.url('/admin/feedback').'"', false);
            $this->get('/admin/feedback')->assertOk()->assertSee('Feedback from my role.');
        } else {
            $response->assertDontSee('href="'.url('/admin/feedback').'"', false);
            $this->get('/admin/feedback')->assertForbidden();
        }
    }

    public static function roles(): array
    {
        return array_map(fn (UserRole $role): array => [$role], UserRole::cases());
    }

    public function test_administrator_sees_paginated_feedback_newest_first_with_escaped_content(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole(UserRole::Administrator->value);
        $author = User::factory()->create(['name' => '<script>author()</script>']);
        Feedback::factory()->count(25)->for($author)->create(['message' => 'Older feedback', 'created_at' => now()->subDay()]);
        $newest = Feedback::factory()->for($author)->create([
            'message' => '<script>alert("feedback")</script>',
            'page_url' => route('profile.edit').'?tab=password',
        ]);

        $response = $this->actingAs($admin)->get('/admin/feedback')
            ->assertOk()->assertSeeInOrder([$newest->message, 'Older feedback'])
            ->assertSee($author->name)->assertDontSee($author->name, false)
            ->assertDontSee($newest->message, false)->assertSee($newest->page_url);
        $listing = $response->viewData('feedback');
        $this->assertSame(26, $listing->total());
        $this->assertCount(25, $listing->items());
        $this->assertSame($newest->id, $listing->first()->id);
        $this->get('/admin/feedback?page=2')->assertOk()->assertSee('Older feedback')->assertDontSee($newest->message);
    }
}
