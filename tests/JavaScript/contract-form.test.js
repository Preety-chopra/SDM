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

function rangeForm(selected = ['21']) {
    const form = contractForm();
    form.stations = [
        { id: 14, line_id: 1, sequence_no: 40 },
        { id: 12, line_id: 1, sequence_no: 20 },
        { id: 21, line_id: 2, sequence_no: 20 },
        { id: 15, line_id: 1, sequence_no: null },
        { id: 11, line_id: 1, sequence_no: 10 },
        { id: 13, line_id: 1, sequence_no: 30 },
    ];
    form.$el = { dataset: {
        lineIds: JSON.stringify(['1', '2']),
        stationIds: JSON.stringify(selected),
        stations: JSON.stringify(form.stations),
    } };
    form.init();

    return form;
}

test('range dropdown stations follow sequence numbers and exclude stations without a sequence', () => {
    assert.deepEqual(rangeForm().rangeStationsForLine('1').map((station) => station.id), [11, 12, 13, 14]);
});

test('choosing endpoints selects the inclusive range on only that line in either direction', () => {
    const form = rangeForm(['11', '21']);
    form.stationRanges['1'] = { from: '14', till: '12' };

    form.selectStationRange('1');

    assert.deepEqual(form.stationIds, ['21', '12', '13', '14']);
    form.stationRanges['1'] = { from: '12', till: '12' };
    form.selectStationRange('1');
    assert.deepEqual(form.stationIds, ['21', '12']);
});

test('edit and validation recovery prefill contiguous ranges without changing saved stations', () => {
    const form = rangeForm(['13', '21', '12']);

    assert.deepEqual(form.stationRanges['1'], { from: '12', till: '13' });
    assert.deepEqual(form.stationRanges['2'], { from: '21', till: '21' });
    assert.deepEqual(form.stationIds, ['13', '21', '12']);
});

test('noncontiguous and unsequenced selections remain intact without a misleading range', () => {
    const form = rangeForm(['11', '13', '21']);

    assert.deepEqual(form.stationRanges['1'], { from: '', till: '' });
    assert.deepEqual(form.stationIds, ['11', '13', '21']);

    form.stationIds = ['11', '15'];
    form.syncStationRange('1');
    assert.deepEqual(form.stationRanges['1'], { from: '', till: '' });
});

test('incomplete or foreign endpoints cannot change station selections', () => {
    const form = rangeForm(['11', '21']);
    for (const range of [{ from: '11', till: '' }, { from: '11', till: '21' }, { from: '11', till: '15' }]) {
        form.stationRanges['1'] = range;
        form.selectStationRange('1');
        assert.deepEqual(form.stationIds, ['11', '21']);
    }
});

test('deselecting a line clears its range and stations while preserving other lines', () => {
    const form = rangeForm(['11', '12', '21']);
    form.lineIds = ['2'];

    form.pruneStations();

    assert.deepEqual(form.stationIds, ['21']);
    assert.deepEqual(form.stationRanges['1'], { from: '', till: '' });
    assert.deepEqual(form.stationRanges['2'], { from: '21', till: '21' });
});

test('select all and clear keep range dropdowns in sync', () => {
    const form = rangeForm();
    form.stations = form.stations.filter((station) => station.sequence_no !== null);

    form.selectLineStations('1');
    assert.deepEqual(form.stationRanges['1'], { from: '11', till: '14' });

    form.clearLineStations('1');
    assert.deepEqual(form.stationRanges['1'], { from: '', till: '' });
    assert.deepEqual(form.stationIds, ['21']);
});
