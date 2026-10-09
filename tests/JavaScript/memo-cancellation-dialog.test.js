import test from 'node:test';
import assert from 'node:assert/strict';

import { createMemoCancellationDialog } from '../../resources/js/memo-cancellation-dialog.js';

test('valid remarks advance to confirmation without submitting', () => {
    let validityChecks = 0;
    const state = createMemoCancellationDialog();

    state.openCancellation();
    state.reviewCancellation({
        reportValidity: () => {
            validityChecks++;

            return true;
        },
    });

    assert.equal(validityChecks, 1);
    assert.equal(state.cancellationOpen, true);
    assert.equal(state.confirmationOpen, true);
});

test('invalid remarks cannot advance to confirmation', () => {
    const state = createMemoCancellationDialog();

    state.openCancellation();
    state.reviewCancellation({ reportValidity: () => false });

    assert.equal(state.confirmationOpen, false);
});

test('closing the dialog resets both cancellation steps', () => {
    const state = createMemoCancellationDialog();

    state.openCancellation();
    state.reviewCancellation({ reportValidity: () => true });
    state.closeCancellation();

    assert.equal(state.cancellationOpen, false);
    assert.equal(state.confirmationOpen, false);
});
