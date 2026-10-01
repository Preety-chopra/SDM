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
        ['incident_date', { value: '2026-09-23', type: 'date' }],
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
                { label: 'Incident date', name: 'incident_date' },
                { label: 'Type', name: 'service_type' },
            ]),
        },
        elements: { namedItem: (name) => fields.get(name) ?? null },
    };

    assert.deepEqual(state.buildSdmPreview(form, { dataset: { confirmationAction: 'Submit SDM' } }), [
        { label: 'Action', value: 'Submit SDM' },
        { label: 'From', value: 'Station Controller' },
        { label: 'Incident date', value: '2026-09-23' },
        { label: 'Type', value: 'CFA · Customer Facilitation' },
    ]);
});

test('preview can hide the action row for memo detail confirmation', () => {
    const fields = new Map([
        ['service_type', {
            value: 'cfs',
            type: 'select-one',
            selectedOptions: [{ textContent: 'CFA · Customer Facilitation' }],
        }],
        ['station_manager_employee_number', {
            value: '12345',
            type: 'text',
            dataset: { previewValue: 'Harish(12345)' },
        }],
        ['deficiencies[]', [
            {
                value: 'late_reporting',
                type: 'checkbox',
                checked: true,
                dataset: { previewValue: 'Late reporting' },
            },
            {
                value: 'public_complaint',
                type: 'checkbox',
                checked: false,
                dataset: { previewValue: 'Public complaint' },
            },
        ]],
    ]);
    const state = confirmation();
    const form = {
        dataset: {
            confirmationShowAction: 'false',
            confirmationPreview: '[]',
            confirmationFields: JSON.stringify([
                { label: 'SDM type', name: 'service_type' },
                { label: 'Line', value: 'RED · Red Line' },
                { label: 'Station', value: 'RED-01 · Central Secretariat' },
                { label: 'Deficiencies', name: 'deficiencies[]' },
                { label: 'Forward to', name: 'station_manager_employee_number' },
            ]),
        },
        elements: { namedItem: (name) => fields.get(name) ?? null },
    };

    assert.deepEqual(state.buildSdmPreview(form, { dataset: { confirmationAction: 'Submit SDM' } }), [
        { label: 'SDM type', value: 'CFA · Customer Facilitation' },
        { label: 'Line', value: 'RED · Red Line' },
        { label: 'Station', value: 'RED-01 · Central Secretariat' },
        { label: 'Deficiencies', value: 'Late reporting' },
        { label: 'Forward to', value: 'Harish(12345)' },
    ]);
});

test('preview reads urgent priority from the current checkbox state', () => {
    const urgentCheckbox = {
        value: '1',
        type: 'checkbox',
        checked: true,
        dataset: { previewValue: 'Urgent' },
    };
    const fields = new Map([
        ['is_urgent', [
            {
                value: '0',
                type: 'hidden',
                dataset: { previewIgnore: '' },
            },
            urgentCheckbox,
        ]],
    ]);
    const state = confirmation();
    const form = {
        dataset: {
            confirmationShowAction: 'false',
            confirmationPreview: '[]',
            confirmationFields: JSON.stringify([
                { label: 'Priority', name: 'is_urgent', emptyValue: 'Normal' },
            ]),
        },
        elements: { namedItem: (name) => fields.get(name) ?? null },
    };

    assert.deepEqual(state.buildSdmPreview(form, { dataset: {} }), [
        { label: 'Priority', value: 'Urgent' },
    ]);

    urgentCheckbox.checked = false;

    assert.deepEqual(state.buildSdmPreview(form, { dataset: {} }), [
        { label: 'Priority', value: 'Normal' },
    ]);
});
