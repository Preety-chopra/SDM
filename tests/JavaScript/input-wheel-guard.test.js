import test from 'node:test';
import assert from 'node:assert/strict';
import { preventInputWheelChanges } from '../../resources/js/input-wheel-guard.js';

function page() {
    const root = new EventTarget();
    root.activeElement = null;
    preventInputWheelChanges(root);

    return {
        root,
        wheel(target, options = {}) {
            const event = new Event('wheel', { cancelable: true });
            Object.defineProperty(event, 'target', { value: target });
            Object.assign(event, { deltaY: 100, ctrlKey: false }, options);
            root.dispatchEvent(event);

            return event;
        },
    };
}

for (const type of ['number', 'date', 'time', 'datetime-local', 'month', 'week']) {
    test(`wheel cannot step a focused ${type} input in either direction`, () => {
        const { root, wheel } = page();
        const input = { tagName: 'INPUT', type };
        root.activeElement = input;

        assert.equal(wheel(input, { deltaY: 100 }).defaultPrevented, true);
        assert.equal(wheel(input, { deltaY: -100 }).defaultPrevented, true);
        assert.equal(root.activeElement, input);
    });
}

test('unfocused inputs and the rest of the page can still scroll', () => {
    const { wheel } = page();

    assert.equal(wheel({ tagName: 'INPUT', type: 'number' }).defaultPrevented, false);
    assert.equal(wheel({ tagName: 'MAIN' }).defaultPrevented, false);
});

test('text inputs and multiline fields retain their scrolling behavior', () => {
    const { root, wheel } = page();

    for (const input of [{ tagName: 'INPUT', type: 'text' }, { tagName: 'TEXTAREA' }]) {
        root.activeElement = input;

        assert.equal(wheel(input).defaultPrevented, false);
    }
});

test('inputs added after registration are protected', () => {
    const { root, wheel } = page();
    const input = { tagName: 'INPUT', type: 'number' };
    root.activeElement = input;

    assert.equal(wheel(input).defaultPrevented, true);
});

test('Ctrl plus wheel can still zoom while an input is focused', () => {
    const { root, wheel } = page();
    const input = { tagName: 'INPUT', type: 'number' };
    root.activeElement = input;

    assert.equal(wheel(input, { ctrlKey: true }).defaultPrevented, false);
});
