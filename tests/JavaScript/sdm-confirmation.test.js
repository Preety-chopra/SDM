import test from 'node:test';
import assert from 'node:assert/strict';

import { createSdmConfirmation } from '../../resources/js/sdm-confirmation.js';

const confirmation = (overrides = {}) => Object.assign(createSdmConfirmation(), {
    $dispatch: () => {},
    ...overrides,
});

test('cancel closes the preview without submitting the form', () => {
    let submissions = 0;
    const form = {
        reportValidity: () => true,
        requestSubmit: () => submissions++,
        dataset: { confirmationPreview: '[]', confirmationFields: '[]' },
    };
    const submitter = { dataset: { sdmConfirm: '', confirmationAction: 'Submit SDM' } };
    const state = confirmation();

    state.requestSdmConfirmation({
        currentTarget: form,
        preventDefault: () => {},
        submitter,
    });
    state.cancelSdmConfirmation();

    assert.equal(submissions, 0);
    assert.equal(state.pendingForm, null);
    assert.equal(state.pendingSubmitter, null);
    assert.equal(state.isSubmitting, false);
});

test('confirm resumes the original action exactly once', () => {
    let submittedWith = null;
    let submissions = 0;
    const submitter = { dataset: { sdmConfirm: '', confirmationAction: 'Forward SDM' } };
    const form = {
        reportValidity: () => true,
        requestSubmit: (button) => {
            submissions++;
            submittedWith = button;
        },
        dataset: { confirmationPreview: '[]', confirmationFields: '[]' },
    };
    const state = confirmation();

    state.requestSdmConfirmation({
        currentTarget: form,
        preventDefault: () => {},
        submitter,
    });
    state.confirmSdmAction();
    state.confirmSdmAction();

    assert.equal(submissions, 1);
    assert.equal(submittedWith, submitter);
    assert.equal(state.isSubmitting, true);
});

test('invalid forms show validation instead of the preview', () => {
    let dispatched = false;
    const state = confirmation({ $dispatch: () => dispatched = true });

    state.requestSdmConfirmation({
        currentTarget: {
            reportValidity: () => false,
            dataset: { confirmationPreview: '[]', confirmationFields: '[]' },
        },
        preventDefault: () => {},
        submitter: { dataset: { sdmConfirm: '', confirmationAction: 'Submit SDM' } },
    });

    assert.equal(dispatched, false);
    assert.equal(state.pendingForm, null);
});

test('preview combines safe fixed values with current form values', () => {
    const fields = new Map([
        ['serial_number', { value: 'SDM-42', type: 'text' }],
        ['service_type', {
            value: 'cfs',
            type: 'select-one',
            selectedOptions: [{ textContent: 'CFA · Customer Facilitation' }],
        }],
    ]);
    const state = confirmation();
    const form = {
        dataset: {
            confirmationPreview: JSON.stringify([{ label: 'From', value: 'Station Controller' }]),
            confirmationFields: JSON.stringify([
                { label: 'SDM number', name: 'serial_number' },
                { label: 'Type', name: 'service_type' },
            ]),
        },
        elements: { namedItem: (name) => fields.get(name) ?? null },
    };

    assert.deepEqual(state.buildSdmPreview(form, { dataset: { confirmationAction: 'Submit SDM' } }), [
        { label: 'Action', value: 'Submit SDM' },
        { label: 'From', value: 'Station Controller' },
        { label: 'SDM number', value: 'SDM-42' },
        { label: 'Type', value: 'CFA · Customer Facilitation' },
    ]);
});
