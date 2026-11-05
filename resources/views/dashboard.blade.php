<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold text-brand-900">{{ __('Dashboard') }}</h2>
                <p class="text-sm text-slate-500 mt-0.5">
                    {{ __('Welcome back,') }} {{ Auth::user()->name }} —
                    <span class="font-medium text-accent-600">{{ Auth::user()->getRoleNames()->first() ?? __('No role') }}</span>
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                @can('production.create')
                <a href="{{ route('production-orders.index') }}" class="inline-flex items-center gap-2 rounded-lg bg-white px-3 sm:px-4 py-2 text-sm font-semibold text-brand-800 ring-1 ring-slate-200 hover:bg-slate-50 whitespace-nowrap">
                    {{ __('Production') }}
                </a>
                <a href="{{ route('production-orders.create') }}" class="inline-flex items-center gap-2 rounded-lg bg-brand-800 px-3 sm:px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700 whitespace-nowrap">
                    <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                    {{ __('New Production') }}
                </a>
                @endcan
                @can('accounts.create')
                <a href="{{ route('collections.create') }}" class="inline-flex items-center gap-2 rounded-lg bg-brand-800 px-3 sm:px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700 focus:ring-2 focus:ring-accent-500 focus:ring-offset-2 whitespace-nowrap">
                    <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                    {{ __('New Collection') }}
                </a>
                <a href="{{ route('expenses.create') }}" class="inline-flex items-center gap-2 rounded-lg bg-white px-3 sm:px-4 py-2 text-sm font-semibold text-brand-800 ring-1 ring-slate-200 hover:bg-slate-50 whitespace-nowrap">
                    {{ __('New Expense') }}
                </a>
                @endcan
            </div>
        </div>
    </x-slot>

    @php
        // Collections, expenses and customer dues are for those who handle
        // money or sales — floor roles (supervisor, HR, employee) see stock only.
        $seesMoney = Auth::user()->canany(['accounts.view', 'sales.view']);
        // Stock levels matter to whoever buys or uses material; a worker doesn't need them.
        $seesStock = Auth::user()->canany(['inventory.view', 'production.view']);
        $stats = array_values(array_filter([
            $seesMoney ? ['label' => __("Today's Collection"), 'value' => number_format($todayCollection, 2), 'icon' => 'collection'] : null,
            $seesMoney ? ['label' => __("Today's Expense"), 'value' => number_format($todayExpense, 2), 'icon' => 'expense'] : null,
            $seesMoney ? ['label' => __('Total Receivable (Due)'), 'value' => number_format($totalReceivable, 2), 'icon' => 'due'] : null,
            $seesStock ? [
                'label' => __('Low Stock Items'),
                'value' => (string) $lowStockCount,
                'sub' => $criticalCount > 0 ? __(':count critical', ['count' => $criticalCount]) : __('None out of stock'),
                'alert' => $criticalCount > 0,
                'icon' => 'stock',
            ] : null,
        ]));
        $pcs = fn ($v) => rtrim(rtrim(number_format((float) $v, 2), '0'), '.') ?: '0';
        $appMode = Auth::user()->usesAppShell();
    @endphp

    @if ($appMode)
    @php
        $me = Auth::user();
        $firstName = strtok($me->employee->name ?? $me->name, ' ');
        $stageLook = [
            'cutting' => ['label' => __('Cutting'), 'bar' => 'bg-amber-400', 'dot' => 'bg-amber-400'],
            'sewing' => ['label' => __('Sewing'), 'bar' => 'bg-sky-500', 'dot' => 'bg-sky-500'],
            'finishing' => ['label' => __('Finishing'), 'bar' => 'bg-violet-500', 'dot' => 'bg-violet-500'],
            'packing' => ['label' => __('Blister / Poly'), 'bar' => 'bg-emerald-500', 'dot' => 'bg-emerald-500'],
        ];
        $onFloor = $production ? array_sum(array_intersect_key($production['floor'], array_flip(['cutting', 'sewing', 'finishing']))) : 0;
        $tiles = array_filter([
            Auth::user()->can('production.create') ? ['href' => route('production-orders.create'), 'label' => __('New production'), 'tint' => 'bg-accent-500 text-white',
                'icon' => 'M12 4.5v15m7.5-7.5h-15'] : null,
            ['href' => route('production-orders.index'), 'label' => __('Productions'), 'tint' => 'bg-sky-50 text-sky-700',
                'icon' => 'M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0ZM3.75 12h.007v.008H3.75V12Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm-.375 5.25h.007v.008H3.75v-.008Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z'],
            $me->employee ? ['href' => route('attendance.my'), 'label' => __('Attendance'), 'tint' => 'bg-amber-50 text-amber-700',
                'icon' => 'M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z'] : null,
            $me->employee ? ['href' => route('my-profile'), 'label' => __('My profile'), 'tint' => 'bg-violet-50 text-violet-700',
                'icon' => 'M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z'] : null,
        ]);
    @endphp
    <!-- Phone home screen (the regular dashboard below is for large screens) -->
    <div class="lg:hidden">
        <div class="-mx-4 -mt-6 rounded-b-[2rem] bg-brand-900 px-5 pb-24 text-white" style="padding-top: calc(env(safe-area-inset-top) + 1.25rem)">
            <div class="flex items-center gap-3">
                <span class="grid h-11 w-11 shrink-0 place-items-center rounded-full bg-white/15 text-lg font-bold ring-2 ring-white/25">{{ mb_strtoupper(mb_substr($firstName, 0, 1)) }}</span>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-lg font-semibold leading-tight">{{ __('Hello, :name', ['name' => $firstName]) }}</p>
                    <p class="truncate text-sm text-brand-200">{{ $me->currentSite->name ?? __('All Sites') }}</p>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" aria-label="{{ __('Log out') }}"
                        class="grid h-10 w-10 place-items-center rounded-full bg-white/10 hover:bg-white/20 focus-visible:outline focus-visible:outline-2 focus-visible:outline-accent-400">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9"/></svg>
                    </button>
                </form>
            </div>
        </div>

        @if ($production)
        <!-- Finished today, with where the rest of the pieces are -->
        <div class="relative -mt-16 rounded-3xl bg-white p-5 shadow-xl shadow-brand-900/10 ring-1 ring-slate-200">
            <div class="flex items-end justify-between gap-4">
                <div>
                    <p class="text-sm font-medium text-slate-500">{{ __('Finished today') }}</p>
                    <p class="mt-1 text-5xl font-extrabold leading-none tracking-tight text-brand-900 tabular-nums">{{ $pcs($production['packedToday']) }}<span class="ml-1.5 text-lg font-semibold text-slate-400">{{ __('pcs') }}</span></p>
                </div>
                <div class="text-right">
                    <p class="text-lg font-bold text-slate-800 tabular-nums">{{ $pcs($production['packedMonth']) }}</p>
                    <p class="text-xs text-slate-500">{{ __('this month') }}</p>
                </div>
            </div>

            <div class="mt-5">
                <div class="flex items-baseline justify-between text-xs text-slate-500">
                    <span>{{ __('On the floor now') }}</span>
                    <span class="font-semibold text-slate-700 tabular-nums">{{ $pcs($onFloor) }} {{ __('pcs') }}</span>
                </div>
                <div class="mt-2 flex h-2.5 w-full overflow-hidden rounded-full bg-slate-100" role="img"
                    aria-label="{{ __('Cutting :c, sewing :s, finishing :f pieces', ['c' => $pcs($production['floor']['cutting']), 's' => $pcs($production['floor']['sewing']), 'f' => $pcs($production['floor']['finishing'])]) }}">
                    @if ($onFloor > 0)
                    @foreach (['cutting', 'sewing', 'finishing'] as $stage)
                    <div class="{{ $stageLook[$stage]['bar'] }}" style="width: {{ $production['floor'][$stage] / $onFloor * 100 }}%"></div>
                    @endforeach
                    @endif
                </div>
                <div class="mt-3 grid grid-cols-3 gap-2">
                    @foreach (['cutting', 'sewing', 'finishing'] as $stage)
                    <div>
                        <span class="flex items-center gap-1.5 text-xs text-slate-500"><span class="h-2 w-2 rounded-full {{ $stageLook[$stage]['dot'] }}"></span>{{ $stageLook[$stage]['label'] }}</span>
                        <span class="block text-base font-bold text-slate-800 tabular-nums">{{ $pcs($production['floor'][$stage]) }}</span>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
        @endif

        <!-- Services -->
        <div class="mt-5 grid grid-cols-4 gap-2 rounded-3xl bg-white p-4 ring-1 ring-slate-200">
            @foreach ($tiles as $tile)
            <a href="{{ $tile['href'] }}" class="flex flex-col items-center gap-2 rounded-2xl p-1 text-center focus-visible:outline focus-visible:outline-2 focus-visible:outline-accent-500">
                <span class="grid h-14 w-14 place-items-center rounded-2xl {{ $tile['tint'] }}">
                    <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $tile['icon'] }}"/></svg>
                </span>
                <span class="text-xs font-medium leading-tight text-slate-700">{{ $tile['label'] }}</span>
            </a>
            @endforeach
        </div>

        @if ($production)
        <!-- Latest on the floor -->
        <div class="mt-5">
            <div class="mb-2 flex items-baseline justify-between px-1">
                <h2 class="text-base font-semibold text-slate-800">{{ __('Latest on the floor') }}</h2>
                <span class="text-xs text-slate-500">{{ trans_choice(':count running|:count running', $production['running'], ['count' => $production['running']]) }}</span>
            </div>
            <div class="divide-y divide-slate-100 overflow-hidden rounded-3xl bg-white ring-1 ring-slate-200">
                @forelse ($floorActivity as $entry)
                <a href="{{ route('production-orders.show', $entry->productionOrder) }}" class="flex items-center gap-3 px-4 py-3 active:bg-slate-50">
                    <span class="h-9 w-1.5 shrink-0 rounded-full {{ $stageLook[$entry->stage]['bar'] }}"></span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-semibold text-slate-800">{{ $entry->productionOrder->item_label }}</p>
                        <p class="truncate text-xs text-slate-500">{{ $entry->productionOrder->production_no }}, {{ $entry->created_at->diffForHumans() }}</p>
                    </div>
                    <div class="shrink-0 text-right">
                        <p class="text-sm font-bold text-slate-800 tabular-nums">{{ $pcs($entry->quantity) }} {{ __('pcs') }}</p>
                        <p class="text-xs text-slate-500">{{ $stageLook[$entry->stage]['label'] }}</p>
                    </div>
                </a>
                @empty
                <div class="px-4 py-8 text-center">
                    <p class="text-sm text-slate-500">{{ __('Nothing recorded yet.') }}</p>
                    @can('production.create')
                    <a href="{{ route('production-orders.create') }}" class="mt-2 inline-block text-sm font-semibold text-accent-700">{{ __('Start a production') }}</a>
                    @endcan
                </div>
                @endforelse
            </div>
        </div>
        @endif

        @if ($lowStockCount > 0)
        <div class="mt-5 flex items-center gap-3 rounded-2xl bg-rose-50 px-4 py-3 text-sm text-rose-700 ring-1 ring-rose-200">
            <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/></svg>
            <span>{{ trans_choice(':count material is running low|:count materials are running low', $lowStockCount, ['count' => $lowStockCount]) }}</span>
        </div>
        @endif
    </div>
    @endif

    <div class="space-y-6 {{ $appMode ? 'max-lg:hidden' : '' }}">
        @if ($myWork)
        <!-- My production work (piece-rate worker) -->
        <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <div class="mb-4 flex flex-wrap items-baseline justify-between gap-2">
                <h3 class="font-bold text-brand-900">{{ __('My Production Work') }}</h3>
                <a href="{{ route('my-profile') }}" class="text-xs font-semibold text-accent-600 hover:text-accent-500">{{ __('My Profile') }} &rarr;</a>
            </div>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                @foreach ([__('Today') => $myWork['today'], __('This month') => $myWork['month']] as $label => $sum)
                <div class="rounded-xl bg-gradient-to-br from-brand-800 to-brand-700 p-4 text-white">
                    <span class="block text-[11px] font-semibold uppercase tracking-widest text-brand-200">{{ $label }}</span>
                    <span class="mt-1 block text-3xl font-bold">{{ $pcs($sum->pcs) }} <span class="text-base font-medium text-brand-200">{{ __('pcs') }}</span></span>
                    <span class="block text-sm text-brand-100">{{ __('Earned') }}: {{ number_format((float) $sum->amount, 2) }}</span>
                </div>
                @endforeach
            </div>
            @if ($myWork['recent']->isNotEmpty())
            <div class="mt-4 overflow-x-auto">
            <table class="w-full min-w-[520px] text-sm">
                <thead>
                    <tr class="border-y border-slate-100 bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                        <th class="px-4 py-2 font-semibold">{{ __('Date') }}</th>
                        <th class="px-4 py-2 font-semibold">{{ __('Item') }}</th>
                        <th class="px-4 py-2 font-semibold">{{ __('Work') }}</th>
                        <th class="px-4 py-2 font-semibold text-right">{{ __('Pieces') }}</th>
                        <th class="px-4 py-2 font-semibold text-right">{{ __('Earned') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($myWork['recent'] as $work)
                    <tr>
                        <td class="px-4 py-2 text-slate-500 whitespace-nowrap">{{ $work->entry_date->format('d M') }}</td>
                        <td class="px-4 py-2 text-slate-800">{{ $work->productionOrder->item_label }}</td>
                        <td class="px-4 py-2 text-slate-600">{{ __($work->stage_label) }}</td>
                        <td class="px-4 py-2 text-right font-semibold text-slate-800">{{ $pcs($work->quantity) }}</td>
                        <td class="px-4 py-2 text-right text-slate-700">{{ number_format($work->amount, 2) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            </div>
            @else
            <p class="mt-4 text-center text-sm text-slate-400">{{ __('No piece work recorded for you yet.') }}</p>
            @endif
        </div>
        @endif

        @if ($production)
        <!-- Production floor: pieces only -->
        <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <div class="mb-4 flex flex-wrap items-baseline justify-between gap-2">
                <div>
                    <h3 class="font-bold text-brand-900">{{ __('Production Floor') }}</h3>
                    <p class="text-xs text-slate-400">{{ trans_choice(':count production running|:count productions running', $production['running'], ['count' => $production['running']]) }}</p>
                </div>
                @can('production.view')
                <a href="{{ route('production-orders.index') }}" class="text-xs font-semibold text-accent-600 hover:text-accent-500">{{ __('Open Production') }} &rarr;</a>
                @endcan
            </div>
            <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                @foreach (['cutting' => [__('At Cutting'), 'bg-amber-400'], 'sewing' => [__('At Sewing'), 'bg-sky-500'], 'finishing' => [__('At Finishing'), 'bg-violet-500']] as $stage => [$label, $dot])
                <div class="rounded-xl bg-slate-50 p-4 ring-1 ring-slate-200">
                    <span class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-slate-500"><span class="h-2 w-2 rounded-full {{ $dot }}"></span>{{ $label }}</span>
                    <span class="mt-1 block text-2xl font-bold text-slate-800">{{ $pcs($production['floor'][$stage]) }} <span class="text-sm font-medium text-slate-400">{{ __('pcs') }}</span></span>
                </div>
                @endforeach
                <div class="rounded-xl bg-emerald-50 p-4 ring-1 ring-emerald-200">
                    <span class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-emerald-700"><span class="h-2 w-2 rounded-full bg-emerald-500"></span>{{ __('Finished Today') }}</span>
                    <span class="mt-1 block text-2xl font-bold text-emerald-800">{{ $pcs($production['packedToday']) }} <span class="text-sm font-medium text-emerald-600">{{ __('pcs') }}</span></span>
                    <span class="block text-xs text-emerald-700">{{ $pcs($production['packedMonth']) }} {{ __('pcs this month') }}</span>
                </div>
            </div>
        </div>
        @endif

        @if ($stats)
        <!-- Stat cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
            @foreach ($stats as $stat)
            <div class="relative overflow-hidden rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                <div class="absolute right-0 top-0 h-20 w-20 translate-x-6 -translate-y-6 rounded-full bg-gradient-to-br from-brand-100 to-accent-300/40"></div>
                <div class="relative flex items-start justify-between">
                    <div>
                        <p class="text-sm font-medium text-slate-500">{{ $stat['label'] }}</p>
                        <p class="mt-2 text-2xl font-bold text-brand-900">{{ $stat['value'] }}</p>
                        @if (isset($stat['sub']))
                        <p class="mt-1 text-xs font-semibold {{ $stat['alert'] ? 'text-rose-500' : 'text-emerald-600' }}">{{ $stat['sub'] }}</p>
                        @endif
                    </div>
                    <span class="grid h-11 w-11 place-items-center rounded-xl bg-gradient-to-br from-brand-700 to-brand-900 text-accent-400">
                        @if ($stat['icon'] === 'collection')
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                        @elseif ($stat['icon'] === 'expense')
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18 9 11.25l4.306 4.306a11.95 11.95 0 0 1 5.814-5.518l2.74-1.22m0 0-5.94-2.281m5.94 2.28-2.28 5.941"/></svg>
                        @elseif ($stat['icon'] === 'due')
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z"/></svg>
                        @else
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m21 7.5-9-5.25L3 7.5m18 0-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9"/></svg>
                        @endif
                    </span>
                </div>
            </div>
            @endforeach
        </div>
        @endif

        @if ($seesMoney)
        <!-- Charts row -->
        <div class="grid grid-cols-1 xl:grid-cols-3 gap-4">
            <div class="xl:col-span-2 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="font-bold text-brand-900">{{ __('Collection vs Expense') }}</h3>
                        <p class="text-xs text-slate-400">{{ __('Last 7 days') }}</p>
                    </div>
                    <span class="rounded-full bg-accent-500/10 px-3 py-1 text-xs font-semibold text-accent-600">{{ __('Weekly') }}</span>
                </div>
                <div class="h-72"><canvas id="collectionChart"></canvas></div>
            </div>

            <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                <div class="mb-4 flex items-center justify-between">
                    <div>
                        <h3 class="font-bold text-brand-900">{{ __('Top Overdue Customers') }}</h3>
                        <p class="text-xs text-slate-400">{{ __('By outstanding balance') }}</p>
                    </div>
                </div>
                @forelse ($topOverdueCustomers as $row)
                <div class="flex items-center justify-between gap-3 border-b border-slate-100 py-2.5 last:border-0">
                    <a href="{{ route('parties.ledger', $row['id']) }}" class="truncate text-sm font-semibold text-slate-700 hover:text-brand-800">{{ $row['name'] }}</a>
                    <span class="shrink-0 text-sm font-bold text-amber-600">{{ number_format($row['due'], 2) }}</span>
                </div>
                @empty
                <p class="py-10 text-center text-sm text-slate-400">{{ __('No outstanding customer dues.') }}</p>
                @endforelse
            </div>
        </div>

        @endif

        @if ($seesMoney || $seesStock)
        <!-- Tables row -->
        <div class="grid grid-cols-1 xl:grid-cols-3 gap-4">
            @if ($seesMoney)
            <!-- Recent collections -->
            <div class="xl:col-span-2 rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 overflow-hidden">
                <div class="flex items-center justify-between px-5 pt-5 pb-3">
                    <h3 class="font-bold text-brand-900">{{ __('Recent Collections') }}</h3>
                    <a href="{{ route('collections.index') }}" class="text-xs font-semibold text-accent-600 hover:text-accent-500">{{ __('View all') }} &rarr;</a>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[480px] text-sm">
                        <thead>
                            <tr class="border-y border-slate-100 bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                                <th class="px-5 py-3 font-semibold">{{ __('Voucher') }}</th>
                                <th class="px-5 py-3 font-semibold">{{ __('Party') }}</th>
                                <th class="px-5 py-3 font-semibold">{{ __('Amount') }}</th>
                                <th class="px-5 py-3 font-semibold">{{ __('Date') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($recentCollections as $collection)
                            <tr class="hover:bg-slate-50">
                                <td class="px-5 py-3 font-semibold text-brand-800 whitespace-nowrap">
                                    <a href="{{ route('collections.show', $collection) }}" class="hover:underline">{{ $collection->collection_no }}</a>
                                </td>
                                <td class="px-5 py-3 text-slate-600 whitespace-nowrap">{{ $collection->party->name }}</td>
                                <td class="px-5 py-3 font-medium text-slate-800 whitespace-nowrap">{{ number_format($collection->amount, 2) }}</td>
                                <td class="px-5 py-3 whitespace-nowrap text-slate-500">{{ $collection->collection_date->format('d M, Y') }}</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="4" class="px-5 py-10 text-center text-slate-400">{{ __('No collections yet.') }}</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @endif

            @if ($seesStock)
            <!-- Low stock alerts -->
            <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-bold text-brand-900">{{ __('Low Stock Alerts') }}</h3>
                    @if ($lowStockCount > 0)
                    <span class="rounded-full bg-rose-50 px-2.5 py-0.5 text-xs font-semibold text-rose-600 ring-1 ring-rose-200">{{ __(':count items', ['count' => $lowStockCount]) }}</span>
                    @endif
                </div>
                @if ($lowStockItems->isEmpty())
                <p class="py-10 text-center text-sm text-slate-400">{{ __('Nothing below reorder level.') }}</p>
                @else
                <ul class="space-y-3">
                    @foreach ($lowStockItems as $item)
                    <li class="flex items-start justify-between gap-3 rounded-xl bg-slate-50 px-4 py-3">
                        <div>
                            <p class="text-sm font-semibold text-slate-800">{{ $item->name }}</p>
                            <p class="text-xs text-slate-400">{{ __('reorder at :level', ['level' => rtrim(rtrim(number_format($item->reorder_level, 4), '0'), '.')]) }}</p>
                        </div>
                        <span class="shrink-0 text-xs font-bold {{ $item->balance <= 0 ? 'text-rose-600' : 'text-amber-600' }}">
                            {{ __(':qty :unit left', ['qty' => rtrim(rtrim(number_format($item->balance, 4), '0'), '.'), 'unit' => $item->unit]) }}
                        </span>
                    </li>
                    @endforeach
                </ul>
                @endif
                @can('inventory.view')
                <a href="{{ route('stock.report') }}" class="mt-4 block rounded-lg bg-brand-800/5 px-4 py-2 text-center text-xs font-semibold text-brand-800 hover:bg-brand-800/10">
                    {{ __('Open Inventory') }} &rarr;
                </a>
                @endcan
            </div>
            @endif
        </div>
        @endif
    </div>

    @if ($seesMoney)
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const brand = { deep: '#0b2a6b', mid: '#1857c4', cyan: '#06b6d4', cyanSoft: 'rgba(6, 182, 212, .15)', blueSoft: 'rgba(11, 42, 107, .08)' };

            new Chart(document.getElementById('collectionChart'), {
                type: 'bar',
                data: {
                    labels: @json($chartLabels),
                    datasets: [
                        {
                            label: '{{ __('Collection') }}',
                            data: @json($collectionSeries),
                            backgroundColor: brand.cyan,
                            borderRadius: 6,
                            barPercentage: .55,
                        },
                        {
                            label: '{{ __('Expense') }}',
                            data: @json($expenseSeries),
                            backgroundColor: brand.deep,
                            borderRadius: 6,
                            barPercentage: .55,
                        },
                    ],
                },
                options: {
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8 } } },
                    scales: {
                        y: { grid: { color: 'rgba(100,116,139,.1)' }, ticks: { callback: v => (v / 1000) + 'k' } },
                        x: { grid: { display: false } },
                    },
                },
            });
        });
    </script>
    @endif
</x-app-layout>
