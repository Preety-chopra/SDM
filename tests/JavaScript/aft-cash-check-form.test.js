import test from 'node:test';
import assert from 'node:assert/strict';
import { createAftCashCheckForm } from '../../resources/js/aft-cash-check-form.js';

const form = (entries = []) => {
    const component = createAftCashCheckForm();
    component.$el = { dataset: { entries: JSON.stringify(entries), today: '2026-11-10', stations: JSON.stringify([{ id: 1, line_code: '7' }]) } };
    component.init();
    return component;
};

test('cash differences use cents and distinguish OK, excess, and short', () => {
    const component = form();
    assert.deepEqual(component.cashSummary({ cash_as_per_eos: '1486.10', actual_cash: '1490.20' }), { amount: '4.10', status: 'EXCESS' });
    assert.deepEqual(component.cashSummary({ cash_as_per_eos: '6585', actual_cash: '6581' }), { amount: '-4.00', status: 'SHORT' });
    assert.deepEqual(component.cashSummary({ cash_as_per_eos: '0.10', actual_cash: '0.1' }), { amount: '0.00', status: 'OK' });
    assert.equal(component.cashSummary({ cash_as_per_eos: '', actual_cash: '0' }).status, 'Awaiting cash amounts');
});

test('removing a cash-check row keeps the other values and gives new rows stable unique keys', () => {
    const component = form([{ operator_name: 'First' }, { operator_name: 'Second' }]);
    const secondKey = component.entries[1]._key;
    component.remove(0);
    component.add();
    assert.equal(component.entries[0].operator_name, 'Second');
    assert.equal(component.entries[0]._key, secondKey);
    assert.notEqual(component.entries[1]._key, secondKey);
    assert.equal(component.entries[1].check_type, 'SURP');
    assert.equal(component.entries[1].check_date, '2026-11-10');
    assert.equal(component.lineFor({ station_id: '1' }), '7');
    assert.equal(component.lineFor({ station_id: '' }), '—');
});

test('the editor always retains at least one row and restores draft values', () => {
    const component = form([{ operator_name: 'Saved', cash_as_per_eos: '0.00' }]);
    component.remove(0);
    assert.equal(component.entries.length, 1);
    assert.equal(component.entries[0].operator_name, 'Saved');
    assert.equal(component.entries[0].cash_as_per_eos, '0.00');
});
