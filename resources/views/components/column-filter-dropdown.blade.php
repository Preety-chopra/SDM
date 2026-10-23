@props([
    'label',
    'active' => false,
    'width' => 'w-56',
])

<details
    class="group relative"
    data-column-filter="{{ $label }}"
    x-data="{
        openPicker(summary) {
            const disclosure = summary.parentElement;
            disclosure.open = ! disclosure.open;

            if (! disclosure.open) {
                return;
            }

            const picker = disclosure.querySelector('[data-filter-picker]');

            if (! picker) {
                return;
            }

            picker.focus({ preventScroll: true });

            if (typeof picker.showPicker === 'function') {
                picker.showPicker();
            }
        },
    }"
    @click.outside="$el.open = false"
    @keydown.escape.window="$el.open = false"
>
    <summary
        aria-label="Open {{ $label }} filter"
        aria-haspopup="dialog"
        @click.prevent.stop="openPicker($el)"
        @class([
            'inline-flex cursor-pointer list-none items-center gap-1.5 rounded-md py-1 text-left transition hover:text-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 dark:hover:text-emerald-300 dark:focus:ring-offset-gray-900',
            'text-emerald-700 dark:text-emerald-300' => $active,
        ])
    >
        <span>{{ $label }}</span>
        <svg class="h-3.5 w-3.5 shrink-0 transition group-open:rotate-180" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.17l3.71-3.94a.75.75 0 1 1 1.08 1.04l-4.25 4.5a.75.75 0 0 1-1.08 0l-4.25-4.5a.75.75 0 0 1 .02-1.06Z" clip-rule="evenodd" />
        </svg>
        @if($active)
            <span class="h-1.5 w-1.5 rounded-full bg-emerald-600 dark:bg-emerald-300" aria-label="Filter active"></span>
        @endif
    </summary>

    <div
        role="dialog"
        aria-label="{{ $label }} filter options"
        class="absolute left-0 z-50 mt-2 {{ $width }} rounded-lg border border-gray-200 bg-white p-3 normal-case tracking-normal shadow-xl dark:border-gray-700 dark:bg-gray-900"
    >
        {{ $slot }}
    </div>
</details>
