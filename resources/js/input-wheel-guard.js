const steppedInputTypes = new Set(['number', 'date', 'time', 'datetime-local', 'month', 'week']);

export function preventInputWheelChanges(root = document) {
    root.addEventListener('wheel', (event) => {
        const input = event.target;

        if (!event.ctrlKey
            && input === root.activeElement
            && input?.tagName === 'INPUT'
            && steppedInputTypes.has(input.type)) {
            event.preventDefault();
        }
    }, { passive: false });
}
