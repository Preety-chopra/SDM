@props([
    'memo',
])

@can('update', $memo)
    <a
        href="{{ route('sdm-memos.edit', $memo) }}"
        class="inline-flex items-center justify-center rounded-lg border border-emerald-700 px-5 py-2.5 text-sm font-semibold text-emerald-700 hover:bg-emerald-100 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 dark:text-emerald-300 dark:hover:bg-emerald-900/50 dark:focus:ring-offset-emerald-950"
    >Edit SDM</a>
@endcan

@can('reassignStationManager', $memo)
    <button
        type="button"
        x-on:click="$dispatch('open-modal', 'handover-station-manager-{{ $memo->id }}')"
        aria-haspopup="dialog"
        aria-controls="handover-station-manager-{{ $memo->id }}"
        class="rounded-lg bg-amber-700 px-5 py-2.5 text-sm font-semibold text-white hover:bg-amber-800 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 dark:focus:ring-offset-emerald-950"
    >Handover SDM</button>
@endcan
