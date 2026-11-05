<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class UserActivityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_only_enabled_administrators_can_view_activity(): void
    {
        $this->get('/admin/user-activity')->assertRedirect(route('login'));

        foreach (UserRole::cases() as $role) {
            $user = $this->user($role);
            $this->assertSame($role === UserRole::Administrator, Gate::forUser($user)->allows('viewAny', AuditLog::class));
        }

        $this->actingAs($this->user(UserRole::AssistantManager))->get('/admin/user-activity')->assertForbidden();
        $this->actingAs($this->user(UserRole::Administrator, ['is_enabled' => false]))
            ->get('/admin/user-activity')->assertRedirect(route('login'));
    }

    public function test_administrator_sees_who_did_what_when_and_the_change_details(): void
    {
        $actor = $this->user(UserRole::AssistantManager, ['name' => 'Activity reviewer', 'employee_number' => 'EMP-234']);
        $this->log($actor, 'memo.updated', '2026-11-01 16:45:12', [
            'before_values' => ['status' => 'draft'],
            'after_values' => ['status' => 'submitted'],
            'ip_address' => '192.0.2.15',
            'location' => 'New Delhi, Delhi, India',
            'request_id' => 'activity-request-123',
        ]);
        $this->actingAs($this->user(UserRole::Administrator))->get('/admin/user-activity')
            ->assertOk()->assertSee('User activity')->assertSee('Activity reviewer')->assertSee('EMP-234')
            ->assertSee('memo.updated')->assertSee('01 Nov 2026, 16:45:12')->assertSee('Asia/Kolkata')
            ->assertSee('draft')->assertSee('submitted')->assertSee('192.0.2.15')->assertSee('activity-request-123')
            ->assertSee('IP address / location')->assertSee('New Delhi, Delhi, India')
            ->assertSee(route('user-activity.index'));
    }

    public function test_filters_can_be_combined_and_include_the_whole_end_date(): void
    {
        $actor = $this->user(UserRole::AssistantManager);
        $other = $this->user(UserRole::StationManager);
        $expected = $this->log($actor, 'memo.updated', '2026-11-01 23:59:59');
        $this->log($actor, 'memo.updated', '2026-10-31 23:59:59');
        $this->log($actor, 'memo.created', '2026-11-01 12:00:00');
        $this->log($other, 'memo.updated', '2026-11-01 12:00:00');

        $this->actingAs($this->user(UserRole::Administrator))->get('/admin/user-activity?'.http_build_query([
            'actor_id' => $actor->id, 'event' => 'memo.updated', 'from' => '2026-11-01', 'to' => '2026-11-01',
        ]))->assertOk()->assertViewHas('activities', fn ($logs) => $logs->pluck('id')->all() === [$expected->id]);
    }

    public function test_search_finds_a_users_employee_number_and_keeps_pagination_filters(): void
    {
        $actor = $this->user(UserRole::AssistantManager, ['employee_number' => 'FIND-USER']);
        for ($i = 0; $i < 51; $i++) {
            $this->log($actor, 'memo.updated', '2026-11-01 12:00:00');
        }
        $this->log($this->user(UserRole::StationManager), 'memo.updated', '2026-11-01 12:00:00');

        $this->actingAs($this->user(UserRole::Administrator))->get('/admin/user-activity?search=FIND-USER')
            ->assertOk()->assertViewHas('activities', fn ($logs) => $logs->total() === 51 && $logs->count() === 50)
            ->assertSee('search=FIND-USER', false);
    }

    public function test_invalid_filters_are_rejected_and_empty_results_are_explained(): void
    {
        $admin = $this->user(UserRole::Administrator);
        $this->actingAs($admin)->get('/admin/user-activity?from=2026-11-02&to=2026-11-01&actor_id=abc&event[]=bad')
            ->assertSessionHasErrors(['to', 'actor_id', 'event']);
        $this->get('/admin/user-activity?from=invalid')->assertSessionHasErrors('from');
        $this->get('/admin/user-activity?event=no.match')->assertOk()->assertSee('No activity matches these filters.');
    }

    public function test_system_and_deleted_subject_activity_renders_and_untrusted_content_is_escaped(): void
    {
        $actor = $this->user(UserRole::AssistantManager, ['name' => '<script>actor()</script>']);
        $this->log($actor, 'memo.updated', '2026-11-01 12:00:00', [
            'auditable_type' => 'App\\Models\\ServiceDeficiencyMemo', 'auditable_id' => 99999,
            'after_values' => ['remarks' => '<script>details()</script>'],
            'location' => '<script>location()</script>',
        ]);
        $this->log(null, 'system.imported', '2026-11-01 12:00:00');

        $this->actingAs($this->user(UserRole::Administrator))->get('/admin/user-activity')
            ->assertOk()->assertSee('System / deleted user')->assertSee('Service Deficiency Memo #99999')
            ->assertSee('<script>actor()</script>')->assertSee('details()')
            ->assertSee('Location unavailable')->assertSee('<script>location()</script>')
            ->assertDontSee('<script>actor()</script>', false)->assertDontSee('<script>details()</script>', false)
            ->assertDontSee('<script>location()</script>', false);
    }

    public function test_authenticated_requests_record_route_resource_status_and_time_without_payloads(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 2)->setTime(10, 15, 30));
        $user = $this->user(UserRole::AssistantManager);
        $this->actingAs($user)->withHeader('X-Request-ID', 'visit-123')
            ->get('/profile?token=secret-query-value')->assertOk();

        $log = AuditLog::query()->where('event', 'activity.request')->sole();
        $this->assertSame($user->id, $log->actor_id);
        $this->assertSame(['method' => 'GET', 'route' => 'profile.edit', 'resource' => 'profile', 'resource_ids' => [], 'status' => 200], $log->after_values);
        $this->assertSame('2026-11-02 10:15:30', $log->created_at->format('Y-m-d H:i:s'));
        $this->assertSame('visit-123', $log->request_id);
        $this->assertStringNotContainsString('secret-query-value', $log->toJson());

        $this->get('/users/'.$user->id.'/edit')->assertForbidden();
        $this->assertDatabaseHas('audit_logs', ['event' => 'activity.request', 'actor_id' => $user->id, 'after_values->status' => 403]);
    }

    public function test_login_failure_login_and_logout_are_audited_without_credentials(): void
    {
        $user = $this->user(UserRole::Administrator);
        $this->withServerVariables(['REMOTE_ADDR' => '192.168.1.24']);
        $this->post('/login', ['username' => $user->name, 'password' => 'wrong-secret'])->assertSessionHasErrors('username');
        $this->assertDatabaseHas('audit_logs', ['event' => 'auth.failed', 'actor_id' => null, 'auditable_id' => $user->id]);
        $this->post('/login', ['username' => $user->name, 'password' => 'password'])->assertRedirect();
        $this->assertDatabaseHas('audit_logs', ['event' => 'auth.login', 'actor_id' => $user->id]);
        $this->post('/logout')->assertRedirect('/');
        $this->assertDatabaseHas('audit_logs', ['event' => 'auth.logout', 'actor_id' => $user->id]);
        foreach (['auth.failed', 'auth.login', 'auth.logout'] as $event) {
            $this->assertDatabaseHas('audit_logs', [
                'event' => $event, 'ip_address' => '192.168.1.24', 'location' => 'Local / private network',
            ]);
        }
        $this->assertStringNotContainsString('wrong-secret', AuditLog::all()->toJson());
        $this->assertStringNotContainsString($user->password, AuditLog::all()->toJson());
    }

    #[TestWith(['192.168.1.24', '192.168.1.24', 'Local / private network'])]
    #[TestWith(['::ffff:192.168.1.24', '192.168.1.24', 'Local / private network'])]
    #[TestWith(['::ffff:c0a8:118', '192.168.1.24', 'Local / private network'])]
    #[TestWith(['127.0.0.1', '127.0.0.1', 'Localhost'])]
    #[TestWith(['::1', '::1', 'Localhost'])]
    #[TestWith(['fd00::24', 'fd00::24', 'Local / private network'])]
    #[TestWith(['192.0.2.15', '192.0.2.15', 'Reserved network'])]
    public function test_activity_captures_client_ip_and_labels_non_public_networks_without_lookups(string $remote, string $ip, string $location): void
    {
        Http::preventStrayRequests();
        $user = $this->user(UserRole::AssistantManager);

        $this->actingAs($user)->withServerVariables(['REMOTE_ADDR' => $remote])
            ->withHeader('X-Forwarded-For', '8.8.8.8')->get('/profile')->assertOk();

        $log = AuditLog::query()->where('event', 'activity.request')->sole();
        $this->assertSame($ip, $log->ip_address);
        $this->assertSame($location, $log->location);
        Http::assertNothingSent();
    }

    #[TestWith(['8.8.8.8'])]
    #[TestWith(['2001:4860:4860::8888'])]
    public function test_public_client_location_is_captured_and_cached_for_subsequent_activity(string $ip): void
    {
        Http::preventStrayRequests();
        Http::fake(['https://ipwho.is/'.$ip.'*' => Http::response([
            'success' => true, 'city' => 'New Delhi', 'region' => 'Delhi', 'country' => 'India',
            'latitude' => 28.61, 'longitude' => 77.21,
        ])]);
        $user = $this->user(UserRole::AssistantManager);

        $this->actingAs($user)->withServerVariables(['REMOTE_ADDR' => $ip])->get('/profile')->assertOk();
        $this->get('/profile')->assertOk();

        $logs = AuditLog::query()->where('event', 'activity.request')->get();
        $this->assertCount(2, $logs);
        foreach ($logs as $log) {
            $this->assertSame($ip, $log->ip_address);
            $this->assertSame('New Delhi, Delhi, India', $log->location);
            $this->assertStringNotContainsString('latitude', $log->toJson());
        }
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request['fields'] === 'success,city,region,country');
    }

    #[TestWith(['timeout'])]
    #[TestWith(['http_error'])]
    #[TestWith(['lookup_error'])]
    #[TestWith(['malformed'])]
    public function test_location_lookup_failures_do_not_prevent_activity_recording(string $failure): void
    {
        Http::preventStrayRequests();
        Http::fake(['https://ipwho.is/8.8.8.8*' => match ($failure) {
            'timeout' => Http::failedConnection(),
            'http_error' => Http::response([], 429),
            'lookup_error' => Http::response(['success' => false]),
            'malformed' => Http::response(['success' => true, 'city' => ['unexpected']]),
        }]);

        $this->actingAs($this->user(UserRole::AssistantManager))
            ->withServerVariables(['REMOTE_ADDR' => '8.8.8.8'])->get('/profile')->assertOk();
        $this->get('/profile')->assertOk();

        $logs = AuditLog::query()->where('event', 'activity.request')->get();
        $this->assertCount(2, $logs);
        $log = $logs->first();
        $this->assertSame('8.8.8.8', $log->ip_address);
        $this->assertNull($log->location);
        Http::assertSentCount(1);
    }

    public function test_location_lookups_can_be_disabled_without_losing_the_client_ip(): void
    {
        config(['services.ip_location.enabled' => false]);
        Http::preventStrayRequests();

        $this->actingAs($this->user(UserRole::AssistantManager))
            ->withServerVariables(['REMOTE_ADDR' => '8.8.8.8'])->get('/profile')->assertOk();

        $log = AuditLog::query()->where('event', 'activity.request')->sole();
        $this->assertSame('8.8.8.8', $log->ip_address);
        $this->assertNull($log->location);
        Http::assertNothingSent();
    }

    public function test_user_account_changes_record_actor_and_safe_before_and_after_values(): void
    {
        $admin = $this->user(UserRole::Administrator);
        $data = [
            'name' => 'Audit Staff', 'email' => 'audit-staff@example.com', 'employee_number' => 'AUD-123',
            'phone' => null, 'designation' => null, 'role' => UserRole::AssistantManager->value,
            'password' => 'Secure-password-123!', 'password_confirmation' => 'Secure-password-123!', 'is_enabled' => true,
        ];
        $this->actingAs($admin)->post(route('users.store'), $data)->assertSessionHasNoErrors()->assertRedirect();
        $user = User::query()->where('name', 'Audit Staff')->sole();
        $this->assertDatabaseHas('audit_logs', ['actor_id' => $admin->id, 'auditable_id' => $user->id, 'event' => 'user.created']);

        $this->put(route('users.update', $user), [...$data, 'name' => 'Renamed Staff', 'password' => '', 'password_confirmation' => ''])
            ->assertSessionHasNoErrors()->assertRedirect();
        $update = AuditLog::query()->where('event', 'user.updated')->sole();
        $this->assertSame('Audit Staff', $update->before_values['name']);
        $this->assertSame('Renamed Staff', $update->after_values['name']);

        $this->patch(route('users.status', $user), ['is_enabled' => false])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseHas('audit_logs', ['actor_id' => $admin->id, 'auditable_id' => $user->id, 'event' => 'user.disabled']);
        $this->assertStringNotContainsString('Secure-password-123!', AuditLog::all()->toJson());
        $this->assertStringNotContainsString($user->password, AuditLog::all()->toJson());
    }

    private function user(UserRole $role, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->assignRole($role->value);

        return $user;
    }

    private function log(?User $actor, string $event, string $at, array $attributes = []): AuditLog
    {
        $log = new AuditLog([
            'actor_id' => $actor?->id, 'event' => $event,
            'auditable_type' => User::class, 'auditable_id' => $actor?->id ?? 99999,
            ...$attributes,
        ]);
        $log->created_at = $at;
        $log->save();

        return $log;
    }
}
