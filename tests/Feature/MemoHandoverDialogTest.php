<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class MemoHandoverDialogTest extends TestCase
{
    public function test_handover_action_displays_as_a_button_that_opens_the_employee_form_in_a_modal(): void
    {
        $candidate = new User([
            'name' => 'Replacement Manager',
            'employee_number' => 'EMP-42',
        ]);
        view()->share('errors', new ViewErrorBag);

        $html = Blade::render(
            <<<'BLADE'
                <x-memo-handover-dialog
                    modal-name="handover-line-manager-42"
                    title="Handover pending SDM"
                    description="Select another active manager."
                    action="/sdm-memos/42/line-manager"
                    field-name="line_manager_employee_number"
                    field-label="Handover to employee number"
                    :candidates="$candidates"
                />
            BLADE,
            [
                'candidates' => collect([$candidate]),
            ],
        );

        $this->assertStringContainsString('>Handover SDM</button>', $html);
        $this->assertStringContainsString("x-on:click=\"\$dispatch('open-modal', 'handover-line-manager-42')\"", $html);
        $this->assertStringContainsString('aria-controls="handover-line-manager-42"', $html);
        $this->assertStringContainsString('x-show="show"', $html);
        $this->assertStringContainsString('id="handover-line-manager-42"', $html);
        $this->assertStringContainsString('name="line_manager_employee_number"', $html);
    }

    public function test_handover_modal_reopens_when_the_employee_selection_has_a_validation_error(): void
    {
        $errors = (new ViewErrorBag)->put('default', new MessageBag([
            'station_manager_employee_number' => ['Select another Station Manager.'],
        ]));
        view()->share('errors', $errors);

        $html = Blade::render(
            <<<'BLADE'
                <x-memo-handover-dialog
                    modal-name="handover-station-manager-7"
                    title="Handover pending SDM"
                    description="Select another active Station Manager."
                    action="/sdm-memos/7/station-manager"
                    field-name="station_manager_employee_number"
                    field-label="Handover to SM employee number"
                    :candidates="collect()"
                />
            BLADE,
        );

        $this->assertStringContainsString('show: true', $html);
        $this->assertStringContainsString('Select another Station Manager.', $html);
    }
}
