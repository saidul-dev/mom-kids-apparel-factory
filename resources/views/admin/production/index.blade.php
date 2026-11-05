@php
    $qty = fn ($v) => rtrim(rtrim(number_format((float) $v, 2), '0'), '.') ?: '0';
    $stageColor = [
        'cutting' => ['dot' => 'bg-amber-400', 'chip' => 'bg-amber-50 text-amber-700 ring-amber-200', 'text' => 'text-amber-700'],
        'sewing' => ['dot' => 'bg-sky-500', 'chip' => 'bg-sky-50 text-sky-700 ring-sky-200', 'text' => 'text-sky-700'],
        'finishing' => ['dot' => 'bg-violet-500', 'chip' => 'bg-violet-50 text-violet-700 ring-violet-200', 'text' => 'text-violet-700'],
        'packing' => ['dot' => 'bg-emerald-500', 'chip' => 'bg-emerald-50 text-emerald-700 ring-emerald-200', 'text' => 'text-emerald-700'],
        'stock' => ['dot' => 'bg-emerald-500', 'chip' => 'bg-emerald-50 text-emerald-700 ring-emerald-200', 'text' => 'text-emerald-700'],
    ];
    $floorTiles = [
        'cutting' => __('At Cutting'),
        'sewing' => __('At Sewing'),
        'finishing' => __('At Finishing'),
    ];
    $appMode = auth()->user()->usesAppShell();
    $statusTabs = ['in_progress' => __('Running'), 'completed' => __('Completed'), 'all' => __('All')];
@endphp
<x-app-layout>
    <x-slot name="title">{{ __('Production Dashboard') }}</x-slot>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold text-brand-900">{{ __('Production Dashboard') }}</h2>
                <p class="text-sm text-slate-500 mt-0.5">{{ __('Cutting → Sewing → Finishing → Blister / Poly → Finished stock') }}</p>
            </div>
            @can('production.create')
            <a href="{{ route('production-orders.create') }}"
               class="inline-flex items-center gap-2 rounded-lg bg-brand-800 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                {{ __('New Production') }}
            </a>
            @endcan
        </div>
    </x-slot>

    @if ($appMode)
    @php
        $onFloor = $summary['floor']['cutting'] + $summary['floor']['sewing'] + $summary['floor']['finishing'];
    @endphp
    <!-- Phone app view (the table layout below is for large screens) -->
    <div class="space-y-4 lg:hidden">
        <!-- Floor right now -->
        <div class="rounded-3xl bg-white p-4 ring-1 ring-slate-200">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-sm text-slate-500">{{ __('On the floor now') }}</p>
                    <p class="text-3xl font-extrabold leading-tight text-brand-900 tabular-nums">{{ $qty($onFloor) }}<span class="ml-1 text-base font-semibold text-slate-400">{{ __('pcs') }}</span></p>
                    <p class="text-xs text-slate-500">{{ trans_choice(':count production running|:count productions running', $summary['running'], ['count' => $summary['running']]) }}</p>
                </div>
                <div class="rounded-2xl bg-emerald-50 px-3 py-2 text-right ring-1 ring-emerald-200">
                    <p class="text-xs text-emerald-700">{{ __('Finished today') }}</p>
                    <p class="text-xl font-bold text-emerald-800 tabular-nums">{{ $qty($summary['packedToday']) }}</p>
                </div>
            </div>
            <div class="mt-3 flex h-2 w-full overflow-hidden rounded-full bg-slate-100">
                @if ($onFloor > 0)
                @foreach (['cutting' => 'bg-amber-400', 'sewing' => 'bg-sky-500', 'finishing' => 'bg-violet-500'] as $stage => $bar)
                <div class="{{ $bar }}" style="width: {{ $summary['floor'][$stage] / $onFloor * 100 }}%"></div>
                @endforeach
                @endif
            </div>
            <div class="mt-2 grid grid-cols-3 gap-2 text-xs text-slate-500">
                @foreach (['cutting' => __('Cutting'), 'sewing' => __('Sewing'), 'finishing' => __('Finishing')] as $stage => $label)
                <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full {{ $stageColor[$stage]['dot'] }}"></span>{{ $label }} <b class="text-slate-800 tabular-nums">{{ $qty($summary['floor'][$stage]) }}</b></span>
                @endforeach
            </div>
        </div>

        <!-- Status tabs + search, kept in reach under the app bar while scrolling -->
        <div class="sticky z-10 -mx-4 space-y-2 bg-slate-50/95 px-4 py-2 backdrop-blur" style="top: calc(3.5rem + env(safe-area-inset-top))">
        <div class="grid grid-cols-3 rounded-2xl bg-white p-1 ring-1 ring-slate-200">
            @foreach ($statusTabs as $key => $label)
            <a href="{{ route('production-orders.index', array_filter(['status' => $key, 'q' => $search ?: null, 'site_id' => $siteId])) }}"
               @if ($status === $key) aria-current="page" @endif
               class="tap rounded-xl py-2 text-center text-sm font-semibold {{ $status === $key ? 'bg-brand-800 text-white' : 'text-slate-600' }}">{{ $label }}</a>
            @endforeach
        </div>
        <form method="GET" action="{{ route('production-orders.index') }}" class="relative">
            <input type="hidden" name="status" value="{{ $status }}">
            <input type="hidden" name="site_id" value="{{ $siteId }}">
            <svg class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/></svg>
            <input name="q" type="search" value="{{ $search }}" placeholder="{{ __('Search tracking no. or item') }}" enterkeyhint="search"
                   class="w-full rounded-2xl border-slate-200 bg-white py-3 pl-10 text-sm focus:border-accent-500 focus:ring-accent-500">
        </form>
        </div>

        <!-- Productions -->
        <div class="space-y-3">
            @forelse ($orders as $order)
            @php
                $cut = $positions[$order->id]['totals']['cutting'];
                $now = $positions[$order->id]['current'];
                $p = fn ($v) => $cut > 0 ? min(100, round($v / $cut * 100)) : 0;
            @endphp
            <a href="{{ route('production-orders.show', $order) }}"
               class="tap block rounded-3xl bg-white p-4 ring-1 ring-slate-200 active:bg-slate-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-accent-500">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="truncate text-base font-semibold text-slate-800">
                            {{ $order->product->name }}
                            @if ($order->productVariant)
                            <span class="ml-1 inline-flex rounded-md bg-brand-50 px-1.5 py-0.5 align-middle text-xs font-semibold text-brand-700 ring-1 ring-brand-100">{{ $order->productVariant->label }}</span>
                            @endif
                        </p>
                        <p class="mt-0.5 text-xs text-slate-500">{{ $order->production_no }}, {{ __('cut :date', ['date' => $order->start_date->format('d M')]) }}</p>
                    </div>
                    <div class="shrink-0 text-right">
                        <p class="text-lg font-bold text-brand-900 tabular-nums">{{ $p($now['stock']) }}%</p>
                        <p class="text-[11px] text-slate-500">{{ $order->isOpen() ? __('finished') : __('Completed') }}</p>
                    </div>
                </div>

                {{-- Same left-to-right flow as the stage counts below it. --}}
                <div class="mt-3 flex h-2 w-full overflow-hidden rounded-full bg-slate-100">
                    <div class="bg-amber-400" style="width: {{ $p($now['cutting']) }}%"></div>
                    <div class="bg-sky-500" style="width: {{ $p($now['sewing']) }}%"></div>
                    <div class="bg-violet-500" style="width: {{ $p($now['finishing']) }}%"></div>
                    <div class="bg-emerald-500" style="width: {{ $p($now['stock']) }}%"></div>
                </div>

                <div class="mt-3 grid grid-cols-4 gap-1 text-center">
                    @foreach (['cutting' => __('Cutting'), 'sewing' => __('Sewing'), 'finishing' => __('Finishing'), 'stock' => __('Done')] as $stage => $label)
                    <div class="rounded-xl py-1.5 {{ $now[$stage] > 0 ? 'bg-slate-50' : '' }}">
                        <p class="text-base font-bold tabular-nums {{ $now[$stage] > 0 ? $stageColor[$stage]['text'] : 'text-slate-300' }}">{{ $qty($now[$stage]) }}</p>
                        <p class="text-[11px] text-slate-500">{{ $label }}</p>
                    </div>
                    @endforeach
                </div>
                <p class="mt-2 text-xs text-slate-400">{{ __(':n pcs cut', ['n' => $qty($cut)]) }}</p>
            </a>
            @empty
            <div class="rounded-3xl bg-white px-4 py-10 text-center ring-1 ring-slate-200">
                <p class="text-sm text-slate-500">{{ $search !== '' ? __('No production matches ":q".', ['q' => $search]) : __('No productions here yet.') }}</p>
                @can('production.create')
                <a href="{{ route('production-orders.create') }}" class="mt-2 inline-block text-sm font-semibold text-accent-700">{{ __('Start a production') }}</a>
                @endcan
            </div>
            @endforelse
        </div>

        @if ($orders->hasPages())
        <div>{{ $orders->links() }}</div>
        @endif
    </div>
    @endif

    <div class="{{ $appMode ? 'max-lg:hidden' : '' }}">
    <!-- Floor right now -->
    <div class="mb-6 grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-5">
        <div class="rounded-2xl bg-gradient-to-br from-brand-900 to-brand-700 p-5 text-white shadow-sm">
            <span class="block text-[11px] font-semibold uppercase tracking-wide text-brand-200">{{ __('Running Productions') }}</span>
            <span class="mt-1 block text-3xl font-bold">{{ $summary['running'] }}</span>
            <span class="block text-xs text-brand-200">{{ $qty(array_sum($summary['floor']) - $summary['floor']['stock']) }} {{ __('pcs on the floor') }}</span>
        </div>
        @foreach ($floorTiles as $stage => $label)
        <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <span class="flex items-center gap-2 text-[11px] font-semibold uppercase tracking-wide text-slate-400">
                <span class="h-2 w-2 rounded-full {{ $stageColor[$stage]['dot'] }}"></span>{{ $label }}
            </span>
            <span class="mt-1 block text-3xl font-bold text-slate-800">{{ $qty($summary['floor'][$stage]) }}</span>
            <span class="block text-xs text-slate-400">{{ __('pcs right now') }}</span>
        </div>
        @endforeach
        <div class="rounded-2xl bg-emerald-50 p-5 shadow-sm ring-1 ring-emerald-200">
            <span class="flex items-center gap-2 text-[11px] font-semibold uppercase tracking-wide text-emerald-700">
                <span class="h-2 w-2 rounded-full bg-emerald-500"></span>{{ __('Finished Today') }}
            </span>
            <span class="mt-1 block text-3xl font-bold text-emerald-800">{{ $qty($summary['packedToday']) }}</span>
            <span class="block text-xs text-emerald-700">{{ $qty($summary['packedMonth']) }} {{ __('pcs this month') }}</span>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-4">
        <div class="xl:col-span-3">
            <!-- Filters -->
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <div class="inline-flex rounded-xl bg-white p-1 shadow-sm ring-1 ring-slate-200">
                    @foreach (['in_progress' => __('Running'), 'completed' => __('Completed'), 'all' => __('All')] as $key => $label)
                    <a href="{{ route('production-orders.index', array_filter(['status' => $key, 'q' => $search ?: null, 'site_id' => $siteId])) }}"
                       class="rounded-lg px-4 py-1.5 text-sm font-semibold transition {{ $status === $key ? 'bg-brand-800 text-white' : 'text-slate-600 hover:bg-slate-50' }}">{{ $label }}</a>
                    @endforeach
                </div>
                <form method="GET" action="{{ route('production-orders.index') }}" class="flex flex-wrap items-center gap-2">
                    <input type="hidden" name="status" value="{{ $status }}">
                    <div class="w-48">
                        <x-searchable-select name="site_id" :options="$sites" :selected="$siteId"
                                              placeholder="{{ __('All sites') }}" :auto-submit="true" />
                    </div>
                    <div class="relative">
                        <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/></svg>
                        <input name="q" type="search" value="{{ $search }}" placeholder="{{ __('Tracking no. or item…') }}"
                               class="w-56 rounded-lg border-slate-200 bg-white py-2 pl-9 text-sm focus:border-accent-500 focus:ring-accent-500">
                    </div>
                </form>
            </div>

            <!-- Productions -->
            <div class="rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 overflow-hidden">
                <div class="overflow-x-auto">
                <table class="w-full min-w-[920px] text-sm">
                    <thead>
                        <tr class="border-b border-slate-100 bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                            <th class="px-5 py-3 font-semibold">{{ __('Tracking No.') }}</th>
                            <th class="px-5 py-3 font-semibold">{{ __('Item / Size') }}</th>
                            <th class="px-4 py-3 font-semibold text-right">{{ __('Cut') }}</th>
                            <th class="px-4 py-3 font-semibold text-center" colspan="4">{{ __('Pieces right now at') }}</th>
                            <th class="px-5 py-3 font-semibold w-40">{{ __('Progress') }}</th>
                        </tr>
                        <tr class="border-b border-slate-100 bg-slate-50 text-[11px] uppercase tracking-wide text-slate-400">
                            <th colspan="3"></th>
                            <th class="px-3 pb-2 text-center font-semibold">{{ __('Cutting') }}</th>
                            <th class="px-3 pb-2 text-center font-semibold">{{ __('Sewing') }}</th>
                            <th class="px-3 pb-2 text-center font-semibold">{{ __('Finishing') }}</th>
                            <th class="px-3 pb-2 text-center font-semibold">{{ __('Finished') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($orders as $order)
                        @php
                            $cut = $positions[$order->id]['totals']['cutting'];
                            $now = $positions[$order->id]['current'];
                            $p = fn ($v) => $cut > 0 ? min(100, round($v / $cut * 100)) : 0;
                        @endphp
                        <tr class="cursor-pointer hover:bg-slate-50" onclick="window.location='{{ route('production-orders.show', $order) }}'">
                            <td class="px-5 py-3">
                                <a href="{{ route('production-orders.show', $order) }}" class="font-mono font-semibold text-brand-800 hover:text-brand-600">{{ $order->production_no }}</a>
                                <span class="block text-xs text-slate-400">{{ $order->start_date->format('d M, Y') }} · {{ $order->site->name }}</span>
                            </td>
                            <td class="px-5 py-3">
                                <span class="font-semibold text-slate-800">{{ $order->product->name }}</span>
                                @if ($order->productVariant)
                                <span class="ml-1 inline-flex rounded-md bg-brand-50 px-2 py-0.5 text-xs font-semibold text-brand-700 ring-1 ring-brand-100">{{ $order->productVariant->label }}</span>
                                @endif
                                @unless ($order->isOpen())
                                <span class="ml-1 inline-flex rounded-md bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-500">{{ __('Completed') }}</span>
                                @endunless
                            </td>
                            <td class="px-4 py-3 text-right font-bold text-slate-800">{{ $qty($cut) }}</td>
                            @foreach (['cutting', 'sewing', 'finishing', 'stock'] as $stage)
                            <td class="px-3 py-3 text-center">
                                @if ($now[$stage] > 0)
                                <span class="inline-flex min-w-[3rem] justify-center rounded-full px-2.5 py-0.5 text-sm font-semibold ring-1 {{ $stageColor[$stage]['chip'] }}">{{ $qty($now[$stage]) }}</span>
                                @else
                                <span class="text-slate-300">0</span>
                                @endif
                            </td>
                            @endforeach
                            <td class="px-5 py-3">
                                <div class="flex h-2 w-full overflow-hidden rounded-full bg-slate-100">
                                    <div class="bg-emerald-500" style="width: {{ $p($now['stock']) }}%"></div>
                                    <div class="bg-violet-500" style="width: {{ $p($now['finishing']) }}%"></div>
                                    <div class="bg-sky-500" style="width: {{ $p($now['sewing']) }}%"></div>
                                    <div class="bg-amber-400" style="width: {{ $p($now['cutting']) }}%"></div>
                                </div>
                                <span class="mt-1 block text-xs text-slate-400">{{ $p($now['stock']) }}% {{ __('finished') }}</span>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="px-5 py-12 text-center text-slate-400">
                                {{ $search !== '' ? __('No production matches ":q".', ['q' => $search]) : __('No productions here yet.') }}
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
                </div>

                @if ($orders->hasPages())
                <div class="border-t border-slate-100 px-5 py-4">
                    {{ $orders->links() }}
                </div>
                @endif
            </div>
        </div>

        <!-- Activity -->
        <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200 h-fit">
            <h3 class="mb-4 text-sm font-semibold text-slate-800">{{ __('Latest Activity') }}</h3>
            <ol class="space-y-4">
                @forelse ($activity as $entry)
                <li class="flex gap-3">
                    <span class="mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full {{ $stageColor[$entry->stage]['dot'] }}"></span>
                    <div class="min-w-0 text-sm">
                        <p class="text-slate-700">
                            <span class="font-semibold {{ $stageColor[$entry->stage]['text'] }}">{{ $qty($entry->quantity) }} pcs</span>
                            {{ __($entry->stage_label) }}
                        </p>
                        <a href="{{ route('production-orders.show', $entry->productionOrder) }}" class="block truncate text-xs text-slate-500 hover:text-brand-700">
                            <span class="font-mono">{{ $entry->productionOrder->production_no }}</span> · {{ $entry->productionOrder->item_label }}
                        </a>
                        <p class="text-[11px] text-slate-400">{{ $entry->entry_date->format('d M') }} · {{ $entry->createdBy->name ?? '—' }}</p>
                    </div>
                </li>
                @empty
                <li class="text-sm text-slate-400">{{ __('Nothing recorded yet.') }}</li>
                @endforelse
            </ol>
        </div>
    </div>
    </div>
</x-app-layout>
