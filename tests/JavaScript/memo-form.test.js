import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';
import { createMemoForm } from '../../resources/js/memo-form.js';

for (const [start, end] of [['09:15', '18:15'], ['00:00', '09:00'], ['15:00', '00:00'], ['23:45', '08:45'], ['09:15:00', '18:15']]) {
    test(`entering booked shift from ${start} fills booked shift to ${end}`, () => {
        const form = createMemoForm();

        form.syncBookedShiftTo(start);

        assert.equal(form.bookedShiftTo, end);
    });
}

for (const eventName of ['input', 'change']) {
    test(`the start time field handles ${eventName} events and updates the bound end time`, () => {
        const template = readFileSync(new URL('../../resources/views/sdm-memos/form.blade.php', import.meta.url), 'utf8');
        const startInput = template.match(/<input\b[^>]*id="booked_shift_from"[^>]*>/)[0];
        const endInput = template.match(/<input\b[^>]*id="booked_shift_to"[^>]*>/)[0];
        const handler = startInput.match(new RegExp(`@${eventName}="([^"]+)"`))?.[1];
        assert.ok(handler, `The start time needs an ${eventName} handler`);
        const form = createMemoForm();

        runInNewContext(`with ($data) { ${handler} }`, { $data: form, $event: { target: { value: '23:45' } } });

        const endModel = endInput.match(/x-model="([^"]+)"/)[1];
        assert.equal(form[endModel], '08:45');
        assert.doesNotMatch(endInput, /\s(?:readonly|disabled)(?:\s|=|>)/);

        form[endModel] = '09:30';

        assert.equal(form.bookedShiftTo, '09:30');
    });
}

test('booked shift to remains editable and recalculates when the start time changes', () => {
    const form = createMemoForm();
    form.syncBookedShiftTo('09:00');

    form.bookedShiftTo = '17:30';

    assert.equal(form.bookedShiftTo, '17:30');

    form.syncBookedShiftTo('10:15');

    assert.equal(form.bookedShiftTo, '19:15');
});

test('clearing or entering an incomplete start time clears the calculated end time', () => {
    const form = createMemoForm();

    for (const start of ['', '09:', '24:00', '09:60']) {
        form.bookedShiftTo = '18:00';

        form.syncBookedShiftTo(start);

        assert.equal(form.bookedShiftTo, '');
    }
});

test('restoring saved or validation times preserves a custom booked shift end time', () => {
    const form = createMemoForm();
    form.$el = { dataset: { bookedShiftFrom: '09:00', bookedShiftTo: '17:30' } };

    form.init();

    assert.equal(form.bookedShiftFrom, '09:00');
    assert.equal(form.bookedShiftTo, '17:30');
});

test('removing one selected deficiency preserves the other selections and available options', () => {
    const form = createMemoForm();
    form.serviceType = 'ticketing';
    form.optionsByType = { ticketing: [
        { value: 'dress_code', label: 'Dress code' },
        { value: 'late_reporting', label: 'Late reporting' },
        { value: 'cash_check', label: 'Cash check' },
    ] };
    form.selected = ['dress_code', 'late_reporting', 'cash_check'];

    form.removeDeficiency('late_reporting');

    assert.deepEqual(form.selected, ['dress_code', 'cash_check']);
    assert.equal(form.has('late_reporting'), false);
    assert.equal(form.availableDeficiencies.length, 3);
});

test('removing the last selected deficiency clears the selection', () => {
    const form = createMemoForm();
    form.selected = ['cash_check'];

    form.removeDeficiency('cash_check');

    assert.deepEqual(form.selected, []);
    assert.equal(form.has('cash_check'), false);
});

test('the form restores more than five selections from a draft or validation error', () => {
    const form = createMemoForm();
    const selected = ['dress_code', 'mobile_phone_on_duty', 'improper_cleanliness', 'late_reporting', 'misbehavior', 'public_complaint'];
    form.$el = { dataset: { selectedDeficiencies: JSON.stringify(selected) } };

    form.init();

    assert.deepEqual(form.selected, selected);
});

const contractorForm = (contractorId = '') => {
    const form = createMemoForm();
    form.$el = { dataset: {
        serviceType: 'token_vending',
        contractorId,
        incidentDate: '2026-11-02',
        contractors: JSON.stringify([{ id: 1, name: 'Ticketing Partner' }, { id: 2, name: 'CFA Partner' }, { id: 3, name: 'Previous Partner' }]),
        contractorContracts: JSON.stringify([
            { contractor_id: 1, service_type: 'token_vending', effective_from: '2026-11-01', effective_to: '2027-10-31' },
            { contractor_id: 2, service_type: 'customer_facilitation', effective_from: '2026-01-01', effective_to: '2026-12-31' },
            { contractor_id: 3, service_type: 'token_vending', effective_from: '2026-01-01', effective_to: '2026-10-31' },
        ]),
    } };
    form.init();
    return form;
};

test('selecting an SDM type automatically selects its matching contractor', () => {
    const form = contractorForm();
    assert.equal(form.contractorId, '1');
    assert.deepEqual(form.availableContractors.map((contractor) => contractor.id), [1]);

    form.serviceType = 'customer_facilitation';
    form.syncContractor();

    assert.equal(form.contractorId, '2');
});

test('changing the incident date selects the contractor whose contract covers that date inclusively', () => {
    const form = contractorForm();
    form.incidentDate = '2026-10-31';
    form.syncContractor();
    assert.equal(form.contractorId, '3');

    form.incidentDate = '2026-11-01';
    form.syncContractor();
    assert.equal(form.contractorId, '1');
});

test('missing matches clear the previous contractor selection', () => {
    const form = contractorForm();
    form.serviceType = 'marshal';
    form.syncContractor();
    assert.equal(form.contractorId, '');
    assert.deepEqual(form.availableContractors, []);

    form.serviceType = '';
    form.syncContractor();
    assert.equal(form.contractorId, '');
});

test('multiple matching contractors require a choice and preserve a valid choice', () => {
    const form = contractorForm();
    form.contractorContracts.push({ contractor_id: 2, service_type: 'token_vending', effective_from: '2026-01-01', effective_to: '2026-12-31' });
    form.contractorId = '';
    form.syncContractor();
    assert.equal(form.contractorId, '');
    assert.deepEqual(form.availableContractors.map((contractor) => contractor.id), [1, 2]);

    form.contractorId = '2';
    form.syncContractor();
    assert.equal(form.contractorId, '2');
});

test('the create form lists every contractor for the type and defaults to the station and date match', () => {
    const form = contractorForm();
    form.$el.dataset.contractorIdsByServiceType = JSON.stringify({
        token_vending: [1, 2, 3],
        customer_facilitation: [2, 3],
        watch_and_ward: [3],
    });
    form.init();

    assert.equal(form.contractorId, '1');
    assert.deepEqual(form.availableContractors.map((contractor) => contractor.id), [1, 2, 3]);

    form.incidentDate = '2026-10-31';
    form.syncContractor();
    assert.equal(form.contractorId, '3');
    assert.deepEqual(form.availableContractors.map((contractor) => contractor.id), [1, 2, 3]);

    form.contractorManuallySelected = true;
    form.serviceType = 'customer_facilitation';
    form.syncContractor({ serviceTypeChanged: true });

    assert.equal(form.contractorId, '2');
    assert.deepEqual(form.availableContractors.map((contractor) => contractor.id), [2, 3]);

    form.contractorId = '3';
    form.contractorManuallySelected = true;
    form.incidentDate = '2026-11-03';
    form.syncContractor();

    assert.equal(form.contractorId, '3');

    form.serviceType = 'watch_and_ward';
    form.syncContractor({ serviceTypeChanged: true });
    assert.equal(form.contractorId, '');
    assert.deepEqual(form.availableContractors.map((contractor) => contractor.id), [3]);
});

test('duplicate contracts for the same contractor still auto-select that contractor', () => {
    const form = contractorForm();
    form.contractorContracts.push({ ...form.contractorContracts[0] });
    form.contractorId = '';
    form.syncContractor();
    assert.equal(form.contractorId, '1');
});

test('draft and validation selections are restored until the type or date changes', () => {
    const form = contractorForm('3');
    assert.equal(form.contractorId, '3');
    assert.deepEqual(form.availableContractors.map((contractor) => contractor.id), [1, 3]);

    form.syncContractor();
    assert.equal(form.contractorId, '1');
    assert.deepEqual(form.availableContractors.map((contractor) => contractor.id), [1]);
});

test('AFT contractor choices follow the inspected station and clear an invalid prior choice', () => {
    const form = contractorForm();
    form.contractorContracts[0].station_ids = [10, 20];
    form.contractorContracts.push({ ...form.contractorContracts[0], contractor_id: 2, station_ids: [30] });
    form.stationId = '20';
    form.syncContractor();
    assert.equal(form.contractorId, '1');

    form.stationId = '30';
    form.syncContractor();
    assert.equal(form.contractorId, '2');
    assert.deepEqual(form.availableContractors.map((contractor) => contractor.id), [2]);

    form.stationId = '';
    form.syncContractor();
    assert.equal(form.contractorId, '');
    assert.deepEqual(form.availableContractors, []);
});

test('AFT legacy contracts without a station restriction remain available at every selected station', () => {
    const form = contractorForm();
    form.contractorContracts[0].station_ids = [];
    form.stationId = '30';
    form.syncContractor();
    assert.equal(form.contractorId, '1');
});
