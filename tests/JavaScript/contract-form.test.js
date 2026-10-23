import test from 'node:test';
import assert from 'node:assert/strict';
import { createContractForm } from '../../resources/js/contract-form.js';

function contractForm() {
    const form = createContractForm();
    form.lineIds = ['1', '2'];
    form.stationIds = ['11'];
    form.stations = [
        { id: 11, line_id: 1 },
        { id: 12, line_id: 1 },
        { id: 21, line_id: 2 },
    ];

    return form;
}

test('contract location selector can select and clear every station on one line', () => {
    const form = contractForm();

    form.selectLineStations('1');

    assert.deepEqual(form.stationIds, ['11', '12']);
    assert.equal(form.stationCountForLine('1'), 2);
    assert.equal(form.allLineStationsSelected('1'), true);

    form.clearLineStations('1');

    assert.deepEqual(form.stationIds, []);
    assert.equal(form.allLineStationsSelected('1'), false);
});

test('contract location selector removes stations when their line is deselected', () => {
    const form = contractForm();
    form.stationIds = ['11', '21'];
    form.lineIds = ['2'];

    form.pruneStations();

    assert.deepEqual(form.stationIds, ['21']);
});

test('contract location selector searches station codes and names case-insensitively', () => {
    const form = contractForm();
    form.stations[0].code = 'RED-01';
    form.stations[0].name = 'Central Secretariat';

    form.stationSearch = 'secretariat';
    assert.equal(form.stationMatchesSearch('11'), true);

    form.stationSearch = 'red-01';
    assert.equal(form.stationMatchesSearch('11'), true);

    form.stationSearch = 'airport';
    assert.equal(form.stationMatchesSearch('11'), false);
});
