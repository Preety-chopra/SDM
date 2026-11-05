<?php

namespace Tests\Feature;

use App\Enums\DeficiencyType;
use App\Enums\SdmType;
use App\Enums\UserRole;
use App\Models\Line;
use App\Models\ServiceDeficiencyMemo;
use App\Models\Station;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SdmActionFeedbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_saving_and_editing_a_draft_display_the_result_in_a_popup(): void
    {
        $user = $this->stationUser();

        $response = $this->actingAs($user)->post(route('sdm-memos.store'), ['service_type' => SdmType::Cfs->value]);
        $response->assertSessionHasNoErrors();

        $memo = ServiceDeficiencyMemo::query()->sole();
        $response->assertRedirect(route('sdm-memos.edit', $memo))->assertSessionHas('status', 'Draft saved.');
        $this->get($response->headers->get('Location'))->assertOk()
            ->assertSee('data-sdm-action-feedback', false)
            ->assertSee('SDM action successful')
            ->assertSee('Draft saved.');

        $response = $this->post(route('sdm-memos.update', $memo), [
            'service_type' => SdmType::Cfs->value,
            'staff_name' => 'Updated staff',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertSame('Updated staff', $memo->fresh()->staff_name);
        $this->get($response->headers->get('Location'))->assertOk()
            ->assertSee('data-sdm-action-feedback', false)
            ->assertSee('SDM action successful')
            ->assertSee('Draft saved.');
    }

    public function test_failed_submission_displays_validation_messages_in_a_popup_and_keeps_input(): void
    {
        $user = $this->stationUser();

        $response = $this->actingAs($user)->from(route('sdm-memos.create'))
            ->post(route('sdm-memos.store-and-submit'), ['service_type' => SdmType::Cfs->value, 'staff_name' => 'Keep this name']);

        $response->assertRedirect(route('sdm-memos.create'))->assertSessionHasErrors('contractor_id');
        $this->assertDatabaseCount('service_deficiency_memos', 0);
        $this->withCookie(config('session.cookie'), session()->getId())
            ->get(route('sdm-memos.create'))->assertOk()
            ->assertSee('data-sdm-action-feedback', false)
            ->assertSee('SDM action could not be completed')
            ->assertSee('The contractor id field is required.')
            ->assertSee('Keep this name');
    }

    public function test_create_form_validation_errors_appear_once_in_the_popup_and_are_absent_from_the_form(): void
    {
        $user = $this->stationUser();

        $this->actingAs($user)->from(route('sdm-memos.create'))->post(route('sdm-memos.store'), [
            'service_type' => SdmType::Ticketing->value,
            'deficiencies' => [DeficiencyType::CashCheck->value],
            'cash_check' => ['excess_amount' => '10', 'short_amount' => '20'],
        ])->assertRedirect(route('sdm-memos.create'))->assertSessionHasErrors('cash_check.excess_amount');

        $response = $this->withCookie(config('session.cookie'), session()->getId())
            ->get(route('sdm-memos.create'))->assertOk()
            ->assertDontSee('Please correct the following before continuing:');
        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new \DOMXPath($document);
        $popup = $xpath->query('//*[@data-sdm-action-feedback]')->item(0);
        $this->assertInstanceOf(\DOMElement::class, $popup);
        $this->assertSame(['Enter either an excess amount or a short amount, not both.'], json_decode($popup->getAttribute('data-messages'), true));
        $form = $xpath->query('//form[@enctype="multipart/form-data"]')->item(0);
        $this->assertInstanceOf(\DOMElement::class, $form);
        $this->assertStringNotContainsString('Enter either an excess amount or a short amount, not both.', $form->textContent);
    }

    public function test_station_controller_list_displays_submission_feedback_and_drafts_display_errors(): void
    {
        $user = $this->stationUser();

        $this->actingAs($user)->withSession(['status' => 'SDM memo submitted successfully.'])
            ->get(route('sdm-memos.index'))->assertOk()
            ->assertSee('data-sdm-action-feedback', false)
            ->assertSee('SDM action successful')
            ->assertSee('SDM memo submitted successfully.');

        $this->withSession(['error' => 'Unable to save the SDM.'])
            ->get(route('sdm-memos.drafts'))->assertOk()
            ->assertSee('SDM action could not be completed')
            ->assertSee('Unable to save the SDM.');
    }

    public function test_feedback_escapes_messages_and_errors_take_precedence_over_success(): void
    {
        $user = $this->stationUser();
        $unsafe = '<script>alert("feedback")</script>';

        $this->actingAs($user)->withSession(['status' => 'Stale success', 'error' => $unsafe])
            ->get(route('sdm-memos.create'))->assertOk()
            ->assertSee('SDM action could not be completed')
            ->assertSee($unsafe)
            ->assertDontSee($unsafe, false)
            ->assertDontSee('Stale success');
    }

    #[DataProvider('actionErrors')]
    public function test_sdm_action_errors_keep_the_http_status_and_display_a_safe_popup(int $status, string $message): void
    {
        Route::post('/sdm-feedback-failure', fn () => abort($status, 'Secret database details'))
            ->middleware('web')->name('sdm-memos.feedback-failure');
        config()->set('app.debug', false);

        $this->post('/sdm-feedback-failure')->assertStatus($status)
            ->assertSee('data-sdm-action-feedback', false)
            ->assertSee('SDM action could not be completed')
            ->assertSee($message)
            ->assertDontSee('Secret database details');
    }

    public static function actionErrors(): array
    {
        return [
            'missing memo' => [404, 'The requested SDM could not be found.'],
            'expired session' => [419, 'Your session has expired.'],
            'rate limit' => [429, 'Too many requests.'],
            'server error' => [500, 'Check the SDM status before trying again.'],
        ];
    }

    public function test_api_error_responses_are_not_replaced_with_html_popups(): void
    {
        Route::post('/api/sdm-feedback-failure', fn () => abort(403, 'Permission denied.'))
            ->name('sdm-memos.api-feedback-failure');

        $this->post('/api/sdm-feedback-failure')->assertForbidden()
            ->assertJsonPath('message', 'Permission denied.')
            ->assertDontSee('data-sdm-action-feedback', false);
    }

    public function test_sdm_authorization_failures_display_a_popup_without_changing_the_status(): void
    {
        $user = $this->stationUser();

        $this->actingAs($user)->post(route('sdm-memos.bulk-forward'), [])
            ->assertForbidden()
            ->assertSee('data-sdm-action-feedback', false)
            ->assertSee('You do not have permission to perform this SDM action.');
    }

    public function test_unrelated_error_pages_are_not_replaced(): void
    {
        Route::post('/unrelated-feedback-failure', fn () => abort(403))->middleware('web');

        $this->post('/unrelated-feedback-failure')->assertForbidden()
            ->assertDontSee('data-sdm-action-feedback', false);
    }

    private function stationUser(): User
    {
        config()->set('sdm.require_contract_workflow', false);
        $this->seed(RolesAndPermissionsSeeder::class);
        $line = Line::query()->create(['code' => 'FEEDBACK', 'name' => 'Feedback Line', 'is_active' => true]);
        $station = Station::query()->create([
            'code' => 'FB-01', 'name' => 'Feedback Station', 'line_id' => $line->id, 'is_active' => true,
        ]);
        $user = User::factory()->create(['station_id' => $station->id]);
        $user->assignRole(UserRole::Station->value);

        return $user;
    }
}
