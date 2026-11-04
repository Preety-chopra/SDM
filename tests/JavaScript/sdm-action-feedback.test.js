import test from 'node:test';
import assert from 'node:assert/strict';
import { createSdmActionFeedback } from '../../resources/js/sdm-action-feedback.js';

const feedback = (messages = []) => {
    const dialog = { open: false, showModal() { this.open = true; }, close() { this.open = false; } };
    const state = createSdmActionFeedback();
    state.$el = { dataset: { messages: JSON.stringify(messages), feedbackType: 'success' } };
    state.$refs = { dialog };
    state.$nextTick = (callback) => callback();
    state.init();
    return state;
};

test('feedback opens automatically when an action has a result and stays closed otherwise', () => {
    const state = feedback(['Draft saved.']);
    assert.equal(state.$refs.dialog.open, true);
    state.dismiss();
    assert.equal(state.$refs.dialog.open, false);
    assert.equal(feedback().$refs.dialog.open, false);
});

test('invalid SDM fields open an error popup and focus the field after dismissal', () => {
    const state = feedback();
    let focused = false;
    let prevented = false;
    const field = {
        form: { method: 'post' },
        labels: [{ textContent: 'Staff name *' }],
        validationMessage: 'Please fill out this field.',
        focus() { focused = true; },
    };
    state.showValidationError({ target: field, preventDefault() { prevented = true; } });
    assert.equal(prevented, true);
    assert.equal(state.type, 'error');
    assert.deepEqual(state.messages, ['Staff name *: Please fill out this field.']);
    assert.equal(state.$refs.dialog.open, true);
    state.dismiss();
    assert.equal(focused, true);
});

test('multiple invalid fields are summarized in one popup and GET filters are ignored', () => {
    const state = feedback();
    const invalid = (name, method = 'post') => ({
        target: { form: { method }, name, validationMessage: 'Required', focus() {} },
        preventDefault() {},
    });
    state.showValidationError(invalid('filter', 'get'));
    assert.equal(state.$refs.dialog.open, false);
    state.showValidationError(invalid('staff_name'));
    state.showValidationError(invalid('employee_number'));
    assert.deepEqual(state.messages, ['staff_name: Required', 'employee_number: Required']);
});
