import test from 'node:test';
import assert from 'node:assert/strict';
import { createMemoForm } from '../../resources/js/memo-form.js';

const formForLookup = () => {
    const form = createMemoForm();
    form.staffLookupUrl = '/sdm-memos/staff-lookup';
    form.staffCsrfToken = 'test-csrf';
    form.contractorId = '23';
    form.staffIdentifier = '012345678901';
    return form;
};

test('a match fills and locks the name and replaces Aadhaar with the employee ID', async (t) => {
    const form = formForLookup();
    t.mock.method(globalThis, 'fetch', async (url, options) => {
        assert.equal(url, '/sdm-memos/staff-lookup');
        assert.equal(options.method, 'POST');
        assert.equal(options.headers['X-CSRF-TOKEN'], 'test-csrf');
        assert.deepEqual(JSON.parse(options.body), { staff_identifier: '012345678901', contractor_id: '23', suggest: true });
        return { ok: true, json: async () => ({ status: 'found', employee_number: '900001', staff_name: 'Registered staff' }) };
    });

    await form.lookupStaff();

    assert.equal(form.staffIdentifier, '900001');
    assert.equal(form.staffName, 'Registered staff');
    assert.equal(form.staffNameReadOnly, true);
});

test('an unknown employee leaves the name editable for manual entry', async (t) => {
    const form = formForLookup();
    t.mock.method(globalThis, 'fetch', async () => ({ ok: true, json: async () => ({ status: 'missing' }) }));

    await form.lookupStaff();

    assert.equal(form.staffNameReadOnly, false);
    assert.match(form.staffLookupMessage, /enter.*name manually/i);
    form.staffName = 'Manual name';
    assert.equal(form.staffName, 'Manual name');
});

test('changing the identifier immediately removes the previous name and invalidates a pending lookup', async (t) => {
    const form = formForLookup();
    let finish;
    t.mock.method(globalThis, 'fetch', () => new Promise((resolve) => { finish = resolve; }));
    const lookup = form.lookupStaff();
    form.staffIdentifier = 'NEW-ID';
    form.resetStaffLookup();
    form.staffName = 'New manual name';
    finish({ ok: true, json: async () => ({ status: 'found', employee_number: 'OLD-ID', staff_name: 'Stale name' }) });

    await lookup;

    assert.equal(form.staffIdentifier, 'NEW-ID');
    assert.equal(form.staffName, 'New manual name');
    assert.equal(form.staffNameReadOnly, false);
});

test('ambiguous Aadhaar matches require an employee ID and do not invent a staff name', async (t) => {
    const form = formForLookup();
    t.mock.method(globalThis, 'fetch', async () => ({ ok: true, json: async () => ({ status: 'ambiguous' }) }));

    await form.lookupStaff();

    assert.equal(form.staffName, '');
    assert.equal(form.staffNameReadOnly, true);
    assert.match(form.staffLookupMessage, /EMP ID/);
});

test('a lookup error permits manual entry and explains that lookup could not be completed', async (t) => {
    const form = formForLookup();
    t.mock.method(globalThis, 'fetch', async () => { throw new Error('offline'); });

    await form.lookupStaff();

    assert.equal(form.staffNameReadOnly, false);
    assert.match(form.staffLookupMessage, /unable to look up/i);
});

test('changing the contractor discards a matched name and looks up the identifier for the new agency', async (t) => {
    const form = createMemoForm();
    let onChange;
    form.$el = { dataset: { contractorId: '23', staffIdentifier: '900001', staffName: 'Original name' } };
    form.$watch = (field, callback) => { assert.equal(field, 'contractorId'); onChange = callback; };
    form.init();
    form.staffLookupUrl = '/sdm-memos/staff-lookup';
    form.staffSuggestions = [{ employee_number: 'OLD-ID', staff_name: 'Old contractor staff' }];
    form.staffSuggestionsOpen = true;
    form.contractorId = '24';
    t.mock.method(globalThis, 'fetch', async (url, options) => {
        assert.equal(JSON.parse(options.body).contractor_id, '24');
        return { ok: true, json: async () => ({ status: 'missing', suggestions: [{ employee_number: 'NEW-ID', staff_name: 'New contractor staff' }] }) };
    });

    await onChange();

    assert.equal(form.staffName, '');
    assert.equal(form.staffNameReadOnly, false);
    assert.deepEqual(form.staffSuggestions, [{ employee_number: 'NEW-ID', staff_name: 'New contractor staff' }]);
    assert.equal(form.staffSuggestionsOpen, false);
});

test('typing a partial employee ID shows suggestions and choosing one fills the staff and closes the list', async (t) => {
    const form = formForLookup();
    form.staffIdentifier = 'EMP-';
    const suggestions = [
        { employee_number: 'EMP-001', staff_name: 'First staff' },
        { employee_number: 'EMP-002', staff_name: 'Second staff' },
    ];
    t.mock.method(globalThis, 'fetch', async () => ({ ok: true, json: async () => ({ status: 'missing', suggestions }) }));

    await form.lookupStaff();

    assert.deepEqual(form.staffSuggestions, suggestions);
    assert.equal(form.staffSuggestionsOpen, true);
    assert.match(form.staffLookupMessage, /suggestions/i);

    form.selectStaffSuggestion(suggestions[1]);

    assert.equal(form.staffIdentifier, 'EMP-002');
    assert.equal(form.staffName, 'Second staff');
    assert.equal(form.staffNameReadOnly, true);
    assert.equal(form.staffSuggestionsOpen, false);
});

test('staff suggestions can be navigated by keyboard and disappear when the identifier changes', () => {
    const form = formForLookup();
    form.staffSuggestions = [{ employee_number: 'EMP-001', staff_name: 'First staff' }, { employee_number: 'EMP-002', staff_name: 'Second staff' }];

    form.moveStaffSuggestion(1);
    assert.equal(form.staffSuggestionsOpen, true);
    assert.equal(form.staffSuggestionIndex, 0);
    form.moveStaffSuggestion(1);
    assert.equal(form.staffSuggestionIndex, 1);
    form.moveStaffSuggestion(1);
    assert.equal(form.staffSuggestionIndex, 0);
    form.moveStaffSuggestion(-1);
    assert.equal(form.staffSuggestionIndex, 1);

    form.resetStaffLookup();

    assert.deepEqual(form.staffSuggestions, []);
    assert.equal(form.staffSuggestionsOpen, false);
    assert.equal(form.staffSuggestionIndex, -1);
});

test('dismissed suggestions stay closed if an outstanding lookup completes', async (t) => {
    const form = formForLookup();
    let finish;
    t.mock.method(globalThis, 'fetch', () => new Promise((resolve) => { finish = resolve; }));
    const lookup = form.lookupStaff();

    form.closeStaffSuggestions();
    finish({ ok: true, json: async () => ({ status: 'missing', suggestions: [{ employee_number: 'EMP-001', staff_name: 'First staff' }] }) });
    await lookup;

    assert.equal(form.staffSuggestionsOpen, false);
});

test('a stale lookup cannot replace a chosen staff suggestion', async (t) => {
    const form = formForLookup();
    let finish;
    t.mock.method(globalThis, 'fetch', () => new Promise((resolve) => { finish = resolve; }));
    const lookup = form.lookupStaff();
    form.selectStaffSuggestion({ employee_number: 'EMP-002', staff_name: 'Chosen staff' });
    finish({ ok: true, json: async () => ({ status: 'found', employee_number: 'EMP-001', staff_name: 'Stale staff', suggestions: [] }) });

    await lookup;

    assert.equal(form.staffIdentifier, 'EMP-002');
    assert.equal(form.staffName, 'Chosen staff');
    assert.equal(form.staffSuggestionsOpen, false);
});
