@props(['proposal' => null, 'chart' => false])

@if($proposal || $chart)
    <span class="cmc-proposal-label mt-1.5 block w-fit whitespace-nowrap rounded px-2 py-1 text-xs font-extrabold uppercase leading-none tracking-wide"
        @if($chart) x-show="memo.highlighted_proposal" @endif>
        <span class="sr-only">Proposed action </span><span @if($chart) x-text="memo.highlighted_proposal" @endif>{{ $proposal }}</span>
    </span>
@endif
