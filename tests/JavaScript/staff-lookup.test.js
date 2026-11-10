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
        assert.deepEqual(JSON.parse(options.body), { staff_identifier: '012345678901', contractor_id: '23' });
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
    form.contractorId = '24';
    t.mock.method(globalThis, 'fetch', async (url, options) => {
        assert.equal(JSON.parse(options.body).contractor_id, '24');
        return { ok: true, json: async () => ({ status: 'missing' }) };
    });

    await onChange();

    assert.equal(form.staffName, '');
    assert.equal(form.staffNameReadOnly, false);
});
