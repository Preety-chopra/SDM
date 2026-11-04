import test from 'node:test';
import assert from 'node:assert/strict';
import { createFeedbackForm } from '../../resources/js/feedback-form.js';

const form = (request) => {
    const state = createFeedbackForm({
        request,
        currentUrl: () => 'http://localhost:5252/sdm-memos?category=pending#details',
        csrfToken: () => 'csrf-token',
    });
    state.$el = { dataset: { submitUrl: '/feedback' } };
    state.$refs = { dialog: { open: false, showModal() { this.open = true; }, close() { this.open = false; } } };
    return state;
};

test('feedback captures the current URL and submits without navigation', async () => {
    let sent;
    const state = form(async (url, options) => {
        sent = { url, options };
        return { ok: true, json: async () => ({ message: 'Thank you. Your feedback has been submitted.' }) };
    });
    state.open();
    state.message = 'Please clarify this page.';
    await state.submit();
    assert.equal(sent.url, '/feedback');
    assert.equal(sent.options.headers['X-CSRF-TOKEN'], 'csrf-token');
    assert.deepEqual(JSON.parse(sent.options.body), {
        message: 'Please clarify this page.',
        page_url: 'http://localhost:5252/sdm-memos?category=pending#details',
    });
    assert.equal(state.success, 'Thank you. Your feedback has been submitted.');
    assert.equal(state.message, '');
    assert.equal(state.submitting, false);
    state.close();
    assert.equal(state.$refs.dialog.open, false);
});

test('validation errors preserve feedback so the user can correct and retry it', async () => {
    const state = form(async () => ({
        ok: false, status: 422,
        json: async () => ({ errors: { message: ['Please enter feedback.'], page_url: ['Invalid page URL.'] } }),
    }));
    state.message = 'Original feedback';
    await state.submit();
    assert.deepEqual(state.errors, ['Please enter feedback.', 'Invalid page URL.']);
    assert.equal(state.message, 'Original feedback');
    assert.equal(state.success, '');
    assert.equal(state.submitting, false);
});

test('network and expired session errors preserve feedback and allow a retry', async () => {
    for (const request of [
        async () => { throw new Error('Offline'); },
        async () => ({ ok: false, status: 419, json: async () => ({}) }),
        async () => ({ ok: false, status: 500, json: async () => { throw new Error('Not JSON'); } }),
    ]) {
        const state = form(request);
        state.message = 'Keep this feedback';
        await state.submit();
        assert.equal(state.message, 'Keep this feedback');
        assert.equal(state.errors.length, 1);
        assert.equal(state.submitting, false);
    }
});

test('duplicate submits and dismissal are prevented while a submission is pending', async () => {
    let complete;
    let requests = 0;
    const state = form(() => {
        requests++;
        return new Promise((resolve) => { complete = resolve; });
    });
    state.open();
    const pending = state.submit();
    await state.submit();
    state.close();
    assert.equal(requests, 1);
    assert.equal(state.$refs.dialog.open, true);
    complete({ ok: true, json: async () => ({ message: 'Submitted.' }) });
    await pending;
    state.close();
    state.open();
    assert.equal(state.success, '');
});
