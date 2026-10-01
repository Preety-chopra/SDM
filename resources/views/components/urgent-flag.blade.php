@props(['memo'])

@if($memo->is_urgent)
    <span {{ $attributes->class(['inline-flex items-center rounded-full bg-red-600 px-2 py-0.5 text-[0.65rem] font-bold uppercase tracking-wide text-white shadow-sm animate-pulse dark:bg-red-500']) }}>
        Urgent
    </span>
@endif
