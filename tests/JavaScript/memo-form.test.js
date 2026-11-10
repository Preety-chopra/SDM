import test from 'node:test';
import assert from 'node:assert/strict';
import { createMemoForm } from '../../resources/js/memo-form.js';

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
    form.serviceType = 'marshall';
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
