<div x-cloak x-show="mobileOpen" x-transition.opacity class="fixed inset-0 z-40 bg-gray-950/50 md:hidden" @click="mobileOpen = false" aria-hidden="true"></div>

<aside
    data-app-sidebar
    x-cloak
    :class="{
        'w-72': ! collapsed || mobileOpen,
        'w-20': collapsed && ! mobileOpen,
        'translate-x-0': mobileOpen,
        '-translate-x-full': ! mobileOpen,
    }"
    class="fixed inset-y-0 left-0 z-50 flex flex-col border-r border-gray-200 bg-white shadow-xl transition-all duration-300 md:translate-x-0 md:shadow-none dark:border-gray-700 dark:bg-gray-800"
>
    <div class="relative flex h-16 shrink-0 items-center border-b border-gray-200 px-3 pr-14 dark:border-gray-700">
        <a x-show="! collapsed || mobileOpen" x-transition.opacity href="{{ route('dashboard') }}" class="group flex min-w-0 items-center gap-3 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500">
            <x-application-logo class="h-10 w-12 shrink-0 transition-transform group-hover:scale-105" />
            <span x-show="! collapsed || mobileOpen" x-transition.opacity class="min-w-0">
                <span class="block truncate text-sm font-black tracking-tight text-gray-950 dark:text-white">SDM Workspace</span>
                {{-- <span class="block truncate text-xs text-gray-500 dark:text-gray-400">Deficiency management</span> --}}
            </span>
        </a>
        <button type="button" @click="mobileOpen = false" class="absolute right-3 top-3 rounded-lg p-2 text-gray-500 hover:bg-gray-100 md:hidden dark:text-gray-300 dark:hover:bg-gray-700">
            <span class="sr-only">Close sidebar</span>
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18 18 6M6 6l12 12" /></svg>
        </button>
        <button data-sidebar-toggle type="button" @click="toggleSidebar" :title="collapsed ? 'Expand sidebar' : 'Collapse sidebar'" :aria-label="collapsed ? 'Expand sidebar' : 'Collapse sidebar'" class="absolute right-3 top-3 hidden h-10 w-10 items-center justify-center rounded-lg text-gray-500 transition hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-emerald-500 md:inline-flex dark:text-gray-300 dark:hover:bg-gray-700">
            <svg class="h-5 w-5 transition-transform" :class="collapsed ? 'rotate-180' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="m15 18-6-6 6-6" /></svg>
        </button>
    </div>

    <nav class="flex flex-1 flex-col gap-1 overflow-y-auto p-3" aria-label="Main navigation">
        @php
            $baseClasses = 'group flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold transition focus:outline-none focus:ring-2 focus:ring-emerald-500';
            $activeClasses = 'bg-emerald-100 text-emerald-900 dark:bg-emerald-950 dark:text-emerald-200';
            $idleClasses = 'text-gray-600 hover:bg-gray-100 hover:text-gray-950 dark:text-gray-300 dark:hover:bg-gray-700 dark:hover:text-white';
        @endphp

        <a href="{{ route('dashboard') }}" title="Dashboard" class="{{ $baseClasses }} {{ request()->routeIs('dashboard') ? $activeClasses : $idleClasses }}">
            <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 11.5 12 4l9 7.5M5.5 10v10h13V10M9 20v-6h6v6" /></svg>
            <span x-show="! collapsed || mobileOpen" x-transition.opacity>Dashboard</span>
        </a>

        @can('viewAny', \App\Models\ServiceDeficiencyMemo::class)
            <a href="{{ route('sdm-memos.index') }}" title="Service Deficiency Memos" class="relative {{ $baseClasses }} {{ request()->routeIs('sdm-memos.*') ? $activeClasses : $idleClasses }}">
                <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M7 3.5h8l4 4V20a1 1 0 0 1-1 1H7a2 2 0 0 1-2-2V5.5a2 2 0 0 1 2-2ZM14 3v5h5M9 12h6M9 16h6" /></svg>
                <span x-show="! collapsed || mobileOpen" x-transition.opacity class="flex-1">All memos</span>
                @if ($pendingMemoCount > 0)
                    <span
                        data-memo-notification-count="{{ $pendingMemoCount }}"
                        :class="collapsed && ! mobileOpen ? 'absolute right-1 top-1' : 'ml-auto'"
                        class="inline-flex min-w-5 items-center justify-center rounded-full bg-red-600 px-1.5 py-0.5 text-xs font-bold leading-none text-white shadow-sm"
                        aria-label="{{ $pendingMemoCount }} {{ Str::plural('memo', $pendingMemoCount) }} awaiting your action"
                    >{{ $pendingMemoCount > 99 ? '99+' : $pendingMemoCount }}</span>
                @endif
            </a>
        @endcan
        @can('create', \App\Models\ServiceDeficiencyMemo::class)
            <a href="{{ route('sdm-memos.create') }}" title="Create SDM memo" class="{{ $baseClasses }} bg-emerald-700 text-white hover:bg-emerald-800">
                <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-width="2" d="M12 5v14M5 12h14" /></svg>
                <span x-show="! collapsed || mobileOpen" x-transition.opacity>New memo</span>
            </a>
        @endcan

        @can(\App\Enums\UserPermission::ViewUsers->value)
            <p x-show="! collapsed || mobileOpen" class="px-3 pb-1 pt-5 text-xs font-bold uppercase tracking-wider text-gray-400">User management</p>
            @can(\App\Enums\UserPermission::CreateUsers->value)
                <a href="{{ route('users.create_any') }}" title="Add user" class="{{ $baseClasses }} {{ request()->routeIs('users.create_any') ? $activeClasses : $idleClasses }}">
                    <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 19a6 6 0 0 0-12 0M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM19 8v6M16 11h6" /></svg>
                    <span x-show="! collapsed || mobileOpen">Add user</span>
                </a>
            @endcan
            @foreach ($navigationRoles as $role)
                <a href="{{ route('users.index', $role->name) }}" title="{{ \App\Support\RoleLabel::plural($role->name) }}" class="{{ $baseClasses }} {{ request()->routeIs('users.*') && request()->route('role') === $role->name ? $activeClasses : $idleClasses }}">
                    <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M16 20v-1.5a4.5 4.5 0 0 0-4.5-4.5h-4A4.5 4.5 0 0 0 3 18.5V20M9.5 10a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7ZM17 11a3 3 0 0 0 0-6M21 20v-1.5a4 4 0 0 0-3-3.87" /></svg>
                    <span x-show="! collapsed || mobileOpen" class="truncate">{{ \App\Support\RoleLabel::plural($role->name) }}</span>
                </a>
            @endforeach
        @endcan

        @canany([\App\Enums\UserPermission::ManageRoles->value, \App\Enums\UserPermission::ManageSdmHierarchy->value, \App\Enums\UserPermission::ManageLines->value, \App\Enums\UserPermission::ManageStations->value, \App\Enums\UserPermission::ManageContractors->value, \App\Enums\UserPermission::ManageContracts->value])
            <p x-show="! collapsed || mobileOpen" class="px-3 pb-1 pt-5 text-xs font-bold uppercase tracking-wider text-gray-400">Administration</p>
        @endcanany
        @foreach ([
            [\App\Enums\UserPermission::ManageRoles->value, 'roles.index', 'roles.*', 'Roles & permissions'],
            [\App\Enums\UserPermission::ManageSdmHierarchy->value, 'hierarchies.index', 'hierarchies.*', 'SDM hierarchy'],
            [\App\Enums\UserPermission::ManageLines->value, 'lines.index', 'lines.*', 'Lines'],
            [\App\Enums\UserPermission::ManageStations->value, 'stations.index', 'stations.*', 'Stations'],
            [\App\Enums\UserPermission::ManageContractors->value, 'contractors.index', 'contractors.*', 'Contractors'],
            [\App\Enums\UserPermission::ManageContracts->value, 'contracts.index', 'contracts.*', 'Service contracts'],
        ] as [$permission, $route, $pattern, $label])
            @can($permission)
                <a href="{{ route($route) }}" title="{{ $label }}" class="{{ $baseClasses }} {{ request()->routeIs($pattern) ? $activeClasses : $idleClasses }}">
                    <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 7h16M4 12h16M4 17h16" /></svg>
                    <span x-show="! collapsed || mobileOpen" class="truncate">{{ $label }}</span>
                </a>
            @endcan
        @endforeach
    </nav>

</aside>

<header class="sticky top-0 z-30 flex h-16 items-center justify-between gap-4 border-b border-gray-200 bg-white/95 px-4 shadow-sm backdrop-blur sm:px-6 dark:border-gray-700 dark:bg-gray-800/95">
    <button type="button" @click="mobileOpen = true" class="inline-flex h-10 w-10 items-center justify-center rounded-lg text-gray-600 hover:bg-gray-100 md:hidden dark:text-gray-300 dark:hover:bg-gray-700">
        <span class="sr-only">Open sidebar</span>
        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" /></svg>
    </button>

    <div class="ml-auto">
        <x-dropdown align="right" width="48">
            <x-slot name="trigger">
                <button type="button" aria-haspopup="menu" :aria-expanded="open" class="flex items-center gap-3 rounded-xl px-2 py-1.5 text-left transition hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-emerald-500 dark:hover:bg-gray-700">
                    <span class="flex h-9 w-9 items-center justify-center rounded-full bg-emerald-100 text-sm font-black uppercase text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">{{ str(Auth::user()->name)->substr(0, 1) }}</span>
                    <span class="hidden min-w-0 sm:block"><span class="block max-w-40 truncate text-sm font-bold text-gray-900 dark:text-white">{{ Auth::user()->name }}</span><span class="block max-w-40 truncate text-xs text-gray-500 dark:text-gray-400">Account</span></span>
                    <svg class="hidden h-4 w-4 text-gray-400 sm:block" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.17l3.71-3.94a.75.75 0 1 1 1.08 1.04l-4.25 4.5a.75.75 0 0 1-1.08 0l-4.25-4.5a.75.75 0 0 1 .02-1.06Z" clip-rule="evenodd" /></svg>
                </button>
            </x-slot>
            <x-slot name="content">
                <div class="border-b border-gray-100 px-4 py-3 dark:border-gray-600"><p class="truncate text-sm font-semibold text-gray-900 dark:text-white">{{ Auth::user()->name }}</p><p class="truncate text-xs text-gray-500 dark:text-gray-400">{{ Auth::user()->email }}</p></div>
                <x-dropdown-link :href="route('profile.edit')">Change password</x-dropdown-link>
                <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" class="block w-full px-4 py-2 text-left text-sm text-rose-700 transition hover:bg-rose-50 focus:outline-none dark:text-rose-300 dark:hover:bg-rose-950">Log out</button></form>
            </x-slot>
        </x-dropdown>
    </div>
</header>
