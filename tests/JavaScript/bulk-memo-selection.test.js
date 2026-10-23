import test from 'node:test';
import assert from 'node:assert/strict';
import { createBulkMemoSelection } from '../../resources/js/bulk-memo-selection.js';
import { createCmcMemoSelection } from '../../resources/js/cmc-memo-selection.js';

test('select page includes only memos for the chosen destination', () => {
    const selection = createBulkMemoSelection();
    selection.destinations = {
        11: 'am_cmc_to_scrutiny',
        12: 'am_cmc_to_dgm',
        13: 'am_cmc_to_scrutiny',
    };

    selection.togglePage(true);

    assert.deepEqual(selection.selected, ['11', '13']);
    assert.equal(selection.allPageSelected, true);

    selection.togglePage(false);

    assert.deepEqual(selection.selected, []);
});

test('availability changes with the selected destination', () => {
    const selection = createBulkMemoSelection();
    selection.destinations = {
        21: 'am_cmc_to_scrutiny',
        22: 'am_cmc_to_dgm',
    };
    selection.destination = 'am_cmc_to_dgm';

    assert.equal(selection.isAvailable('21'), false);
    assert.equal(selection.isAvailable('22'), true);
    assert.deepEqual(selection.availableIds(), ['22']);
});

test('CMC selection keeps only memos eligible for the chosen action', () => {
    const selection = createCmcMemoSelection();
    selection.actions = {
        31: ['assign'],
        32: ['forward', 'return'],
        33: [],
    };

    selection.togglePage(true);
    assert.deepEqual(selection.selected, ['31']);

    selection.action = 'return';
    selection.selected = selection.selected.filter((id) => selection.isAvailable(id));
    selection.togglePage(true);

    assert.deepEqual(selection.selected, ['32']);
    assert.equal(selection.isAvailable('31'), false);
});
