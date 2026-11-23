import test from 'node:test';
import assert from 'node:assert/strict';
import { createLineSectionsForm } from '../../resources/js/line-sections-form.js';

test('ranges include both ends in sequence order and exclude unsequenced stations', () => {
    const form = createLineSectionsForm();
    form.stations = [{ id: 10, sequence_no: 10 }, { id: 2, sequence_no: 2 }, { id: 1, sequence_no: 1 }, { id: 99, sequence_no: null }];
    const section = { from_station_id: '10', to_station_id: '2', station_ids: [] };

    form.selectRange(section);

    assert.deepEqual(section.station_ids, ['2', '10']);
});

test('assignment count includes each station once even when two sections overlap', () => {
    const form = createLineSectionsForm();
    form.stations = [{ id: 1, sequence_no: 1 }, { id: 2, sequence_no: 2 }];
    const section = { from_station_id: '1', to_station_id: '2', station_ids: [] };
    form.selectRange(section);
    section.station_ids = ['2'];

    assert.equal(form.assignedCount(), 0);
    form.sections = [section, { station_ids: ['2'] }];
    assert.equal(form.assignedCount(), 1);
});

test('incomplete or foreign range endpoints preserve the existing checkbox selection', () => {
    const form = createLineSectionsForm();
    form.stations = [{ id: 1, sequence_no: 1 }];
    const section = { from_station_id: '1', to_station_id: '99', station_ids: ['1'] };

    form.selectRange(section);

    assert.deepEqual(section.station_ids, ['1']);
});

test('removing sections and returning to a whole line clears the submitted selection', () => {
    const form = createLineSectionsForm();
    form.addSection();
    form.addSection();
    assert.equal(form.sections.length, 2);
    form.removeSection(0);
    assert.equal(form.sections.length, 1);
    form.sections = [];
    assert.equal(form.assignedCount(), 0);
});
