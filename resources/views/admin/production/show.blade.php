@php
    $qty = fn ($v) => rtrim(rtrim(number_format((float) $v, 2), '0'), '.') ?: '0';
    $unit = $order->product->stockUnit?->short_name ?? 'Pcs';
    $cut = $totals['cutting'];
    $pct = fn ($v) => $cut > 0 ? min(100, round($v / $cut * 100)) : 0;
    $fabricQty = $fabrics->sum(fn ($m) => (float) $m->quantity);
    $fabricUnits = $fabrics->map(fn ($m) => $m->product->stockUnit?->short_name)->filter()->unique();
    $fabricUnit = $fabricUnits->count() === 1 ? $fabricUnits->first() : '';
    $stageStyle = [
        'cutting' => ['dot' => 'bg-amber-500', 'chip' => 'bg-amber-50 text-amber-700 ring-amber-200'],
        'sewing' => ['dot' => 'bg-sky-500', 'chip' => 'bg-sky-50 text-sky-700 ring-sky-200'],
        'finishing' => ['dot' => 'bg-emerald-500', 'chip' => 'bg-emerald-50 text-emerald-700 ring-emerald-200'],
    ];
@endphp
<x-app-layout>
    <x-slot name="title">{{ $order->production_no }}</x-slot>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <a href="{{ route('production-orders.index') }}" class="text-slate-400 hover:text-slate-600" title="{{ __('Back') }}">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/></svg>
            </a>
            <div>
                <h2 class="text-2xl font-bold text-brand-900">{{ __('Production Tracking') }}</h2>
                <p class="text-sm text-slate-500 mt-0.5">{{ __('Cutting → Sewing → Finishing → Finished stock') }}</p>
            </div>
        </div>
    </x-slot>

    <!-- Header -->
    <div class="rounded-2xl bg-gradient-to-r from-brand-900 to-brand-700 p-6 text-white shadow-sm mb-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <span class="block text-[11px] font-semibold uppercase tracking-widest text-brand-200">{{ __('Tracking No.') }}</span>
                <span class="block font-mono text-3xl font-bold tracking-wide">{{ $order->production_no }}</span>
                <div class="mt-3 flex flex-wrap items-center gap-2">
                    <span class="text-lg font-semibold">{{ $order->product->name }}</span>
                    @if ($order->productVariant)
                    <span class="inline-flex rounded-full bg-white/15 px-3 py-0.5 text-sm font-semibold ring-1 ring-white/30">{{ __('Size') }}: {{ $order->productVariant->label }}</span>
                    @endif
                </div>
                <div class="mt-2 flex flex-wrap gap-x-5 gap-y-1 text-sm text-brand-100">
                    <span>{{ $order->site->name }}</span>
                    <span>{{ __('Cut on') }} {{ $order->start_date->format('d M, Y') }}</span>
                    <span>{{ __('by') }} {{ $order->createdBy->name ?? '—' }}</span>
                </div>
            </div>

            <div class="flex flex-col items-end gap-3">
                @if ($order->isOpen())
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-400/20 px-3 py-1 text-xs font-semibold text-amber-200 ring-1 ring-amber-300/40">
                        <span class="h-1.5 w-1.5 rounded-full bg-amber-300"></span>{{ __('In Progress') }}
                    </span>
                    @can('inventory.approve')
                    <form method="POST" action="{{ route('production-orders.complete', $order) }}"
                          onsubmit="return confirm('{{ $inProcess > 0
                              ? __(':n pcs are still unfinished — completing writes their cost off as wastage. Continue?', ['n' => $qty($inProcess)])
                              : __('Mark this production as completed?') }}');">
                        @csrf
                        <button type="submit" class="rounded-lg bg-white/10 px-3 py-1.5 text-xs font-semibold text-white ring-1 ring-white/30 hover:bg-white/20">
                            {{ __('Complete Production') }}
                        </button>
                    </form>
                    @endcan
                @else
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-400/20 px-3 py-1 text-xs font-semibold text-emerald-200 ring-1 ring-emerald-300/40">
                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-300"></span>{{ __('Completed') }}
                    </span>
                    <span class="text-xs text-brand-100">{{ $order->completed_at->format('d M, Y') }} · {{ $order->completedBy->name ?? '—' }}</span>
                @endif
            </div>
        </div>
    </div>

    <!-- Stage tracker -->
    <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200 mb-4">
        <div class="grid grid-cols-1 gap-6 md:grid-cols-4">
            @foreach ([
                ['key' => 'cutting', 'label' => __('Cutting'), 'value' => $totals['cutting'], 'waiting' => null, 'bar' => 'bg-amber-500', 'ring' => 'bg-amber-100 text-amber-700',
                 'icon' => 'M7.848 8.25l1.536.887M7.848 8.25a3 3 0 1 1-5.196-3 3 3 0 0 1 5.196 3Zm1.536.887a2.165 2.165 0 0 1 1.083 1.839c.005.351.054.695.14 1.024M9.384 9.137l2.077 1.199M7.848 15.75l1.536-.887m-1.536.887a3 3 0 1 1-5.196 3 3 3 0 0 1 5.196-3Zm1.536-.887a2.165 2.165 0 0 0 1.083-1.838c.005-.352.054-.695.14-1.025m-1.223 2.863 2.077-1.199m0-3.328a4.323 4.323 0 0 1 2.068-1.379l5.325-1.628a4.5 4.5 0 0 1 2.48-.044l.803.215-7.794 4.5m-2.882-1.664A4.33 4.33 0 0 0 10.607 12m3.736 0 7.794 4.5-.802.215a4.5 4.5 0 0 1-2.48-.043l-5.326-1.629a4.324 4.324 0 0 1-2.068-1.379M14.343 12l-2.882 1.664'],
                ['key' => 'sewing', 'label' => __('Sewing'), 'value' => $totals['sewing'], 'waiting' => $available['sewing'], 'bar' => 'bg-sky-500', 'ring' => 'bg-sky-100 text-sky-700',
                 'icon' => 'M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28ZM15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z'],
                ['key' => 'finishing', 'label' => __('Finishing'), 'sub' => __('Iron & Poly'), 'value' => $totals['finishing'], 'waiting' => $available['finishing'], 'bar' => 'bg-emerald-500', 'ring' => 'bg-emerald-100 text-emerald-700',
                 'icon' => 'M9.53 16.122a3 3 0 0 0-5.78 1.128 2.25 2.25 0 0 1-2.4 2.245 4.5 4.5 0 0 0 8.4-2.245c0-.399-.078-.78-.22-1.128Zm0 0a15.998 15.998 0 0 0 3.388-1.62m-5.043-.025a15.994 15.994 0 0 1 1.622-3.395m3.42 3.42a15.995 15.995 0 0 0 4.764-4.648l3.876-5.814a1.151 1.151 0 0 0-1.597-1.597L14.146 6.32a15.996 15.996 0 0 0-4.649 4.763m3.42 3.42a6.776 6.776 0 0 0-3.42-3.42'],
                ['key' => 'stock', 'label' => __('Finished Stock'), 'value' => $totals['finishing'], 'waiting' => null, 'bar' => 'bg-brand-600', 'ring' => 'bg-brand-100 text-brand-700',
                 'icon' => 'm20.25 7.5-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5m8.25 3v6.75m0 0-3-3m3 3 3-3M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z'],
            ] as $i => $step)
            <div class="relative">
                @if ($i < 3)
                <div class="absolute left-[calc(50%+2rem)] right-[calc(-50%+0.5rem)] top-6 hidden h-0.5 md:block {{ $step['value'] > 0 ? 'bg-slate-300' : 'bg-slate-100' }}"></div>
                @endif
                <div class="flex flex-col items-center text-center">
                    <span class="relative z-10 flex h-12 w-12 items-center justify-center rounded-full ring-4 ring-white {{ $step['value'] > 0 ? $step['ring'] : 'bg-slate-100 text-slate-400' }}">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $step['icon'] }}"/></svg>
                    </span>
                    <span class="mt-3 text-xs font-semibold uppercase tracking-wide text-slate-500">
                        {{ $step['label'] }}
                        @isset($step['sub'])<span class="block normal-case font-normal text-slate-400">{{ $step['sub'] }}</span>@endisset
                    </span>
                    <span class="mt-1 text-3xl font-bold text-slate-800">{{ $qty($step['value']) }}<span class="ml-1 text-sm font-medium text-slate-400">{{ $unit }}</span></span>

                    <div class="mt-2 h-1.5 w-32 overflow-hidden rounded-full bg-slate-100">
                        <div class="h-full rounded-full {{ $step['bar'] }}" style="width: {{ $pct($step['value']) }}%"></div>
                    </div>
                    <span class="mt-1 text-xs text-slate-400">{{ $pct($step['value']) }}% {{ __('of cut') }}</span>

                    @if ($step['waiting'] !== null)
                        @if ($step['waiting'] > 0)
                        <span class="mt-2 inline-flex rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-semibold text-amber-700 ring-1 ring-amber-200">
                            {{ $qty($step['waiting']) }} {{ __('waiting') }}
                        </span>
                        @else
                        <span class="mt-2 inline-flex rounded-full bg-slate-50 px-2.5 py-0.5 text-xs font-medium text-slate-400 ring-1 ring-slate-200">{{ __('nothing waiting') }}</span>
                        @endif
                    @elseif ($step['key'] === 'stock')
                        <span class="mt-2 text-xs text-slate-500">{{ __('at :site', ['site' => $order->site->name]) }}</span>
                    @endif
                </div>
            </div>
            @endforeach
        </div>

        <!-- Where the pieces are now -->
        @if ($cut > 0)
        @php
            $atCutting = max($cut - $totals['sewing'], 0);
            $atSewing = max($totals['sewing'] - $totals['finishing'], 0);
        @endphp
        <div class="mt-8 border-t border-slate-100 pt-5">
            <div class="mb-2 flex items-center justify-between text-xs font-semibold uppercase tracking-wide text-slate-400">
                <span>{{ __('Where the :n pcs are now', ['n' => $qty($cut)]) }}</span>
                <span>{{ $pct($totals['finishing']) }}% {{ __('complete') }}</span>
            </div>
            <div class="flex h-3 w-full overflow-hidden rounded-full bg-slate-100">
                <div class="bg-emerald-500" style="width: {{ $pct($totals['finishing']) }}%" title="{{ __('Finished') }}"></div>
                <div class="bg-sky-500" style="width: {{ $pct($atSewing) }}%" title="{{ __('Sewn, waiting for finishing') }}"></div>
                <div class="bg-amber-400" style="width: {{ $pct($atCutting) }}%" title="{{ __('Cut, waiting for sewing') }}"></div>
            </div>
            <div class="mt-3 flex flex-wrap gap-x-6 gap-y-1 text-xs text-slate-600">
                <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm bg-emerald-500"></span>{{ __('Finished') }}: <b>{{ $qty($totals['finishing']) }}</b></span>
                <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm bg-sky-500"></span>{{ __('Waiting for finishing') }}: <b>{{ $qty($atSewing) }}</b></span>
                <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm bg-amber-400"></span>{{ __('Waiting for sewing') }}: <b>{{ $qty($atCutting) }}</b></span>
            </div>
        </div>
        @endif
    </div>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3 mb-4">
        <!-- Fabric used -->
        <div class="rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 overflow-hidden lg:col-span-2">
            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-3">
                <h3 class="text-sm font-semibold text-slate-800">{{ __('Fabric Used') }}</h3>
                @if ($fabricUnit)
                <span class="text-xs font-semibold text-slate-500">{{ __('Total') }}: {{ $qty($fabricQty) }} {{ $fabricUnit }}</span>
                @endif
            </div>
            <div class="overflow-x-auto">
            <table class="w-full min-w-[520px] text-sm">
                <thead>
                    <tr class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                        <th class="px-5 py-2.5 font-semibold">{{ __('Fabric') }}</th>
                        <th class="px-5 py-2.5 font-semibold text-right">{{ __('Quantity') }}</th>
                        <th class="px-5 py-2.5 font-semibold text-right">{{ __('Cost') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($fabrics as $m)
                    <tr>
                        <td class="px-5 py-2.5 font-semibold text-slate-800">{{ $m->product->name }}{{ $m->productVariant ? ' — '.$m->productVariant->label : '' }}</td>
                        <td class="px-5 py-2.5 text-right text-slate-800">{{ $qty($m->quantity) }} {{ $m->product->stockUnit?->short_name }}</td>
                        <td class="px-5 py-2.5 text-right text-slate-600">{{ number_format((float) $m->quantity * (float) $m->unit_cost, 2) }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="3" class="px-5 py-6 text-center text-slate-400">{{ __('No fabric recorded.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
            </div>
            @if ($fabrics->isNotEmpty() && $cut > 0)
            <div class="grid grid-cols-2 gap-px border-t border-slate-100 bg-slate-100 sm:grid-cols-3">
                {{-- Per-piece fabric figures only make sense in a single unit (Roll or Gaj, not both). --}}
                @if ($fabricUnit)
                <div class="bg-white px-5 py-3">
                    <span class="block text-[11px] font-semibold uppercase tracking-wide text-slate-400">{{ __('Fabric per piece') }}</span>
                    <span class="block text-lg font-bold text-slate-800">{{ number_format($fabricQty / $cut, 3) }} {{ $fabricUnit }}</span>
                </div>
                <div class="bg-white px-5 py-3">
                    <span class="block text-[11px] font-semibold uppercase tracking-wide text-slate-400">{{ __('Pieces per :unit', ['unit' => $fabricUnit]) }}</span>
                    <span class="block text-lg font-bold text-slate-800">{{ $fabricQty > 0 ? number_format($cut / $fabricQty, 1) : '—' }}</span>
                </div>
                @endif
                <div class="bg-white px-5 py-3">
                    <span class="block text-[11px] font-semibold uppercase tracking-wide text-slate-400">{{ __('Material cost / pc') }}</span>
                    <span class="block text-lg font-bold text-slate-800">{{ number_format($materialCost / $cut, 2) }}</span>
                </div>
            </div>
            @endif
        </div>

        <!-- Cost -->
        <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <h3 class="text-sm font-semibold text-slate-800 mb-4">{{ __('Cost Summary') }}</h3>
            <dl class="space-y-3 text-sm">
                <div class="flex justify-between"><dt class="text-slate-500">{{ __('Total material cost') }}</dt><dd class="font-semibold text-slate-800">{{ number_format($materialCost, 2) }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">{{ __('Still in process (WIP)') }}</dt><dd class="font-semibold text-slate-800">{{ number_format($wipValue, 2) }}</dd></div>
                @if ((float) $order->wastage_value > 0)
                <div class="flex justify-between"><dt class="text-rose-600">{{ __('Wastage written off') }}</dt><dd class="font-semibold text-rose-600">{{ number_format($order->wastage_value, 2) }}</dd></div>
                @endif
            </dl>
            @if ($accessories->isNotEmpty())
            <h4 class="mt-5 mb-2 text-[11px] font-semibold uppercase tracking-wide text-slate-400">{{ __('Other material') }}</h4>
            <ul class="space-y-1 text-sm text-slate-600">
                @foreach ($accessories as $m)
                <li class="flex justify-between gap-2">
                    <span>{{ $m->product->name }}</span>
                    <span class="text-slate-800">{{ $qty($m->quantity) }} {{ $m->product->stockUnit?->short_name }}</span>
                </li>
                @endforeach
            </ul>
            @endif
            @if ($order->note)
            <h4 class="mt-5 mb-1 text-[11px] font-semibold uppercase tracking-wide text-slate-400">{{ __('Note') }}</h4>
            <p class="text-sm text-slate-600">{{ $order->note }}</p>
            @endif
        </div>
    </div>

    @if ($order->isOpen())
    @can('inventory.create')
    <!-- Progress entry -->
    <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200 mb-4"
         x-data="{ stage: @js(old('stage', $nextStage)), available: @js($available), more: {{ old('materials') ? 'true' : 'false' }} }">
        <h3 class="text-base font-semibold text-slate-800">{{ __('Update Progress') }}</h3>
        <p class="text-sm text-slate-500 mb-4">{{ __('Enter how many pieces finished a stage today.') }}</p>

        <div x-data="transferCart(@js(['itemOptions' => $materialOptions]))">
        <form method="POST" action="{{ route('production-orders.stages.store', $order) }}">
            @csrf
            <input type="hidden" name="stage" :value="stage">

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3 max-w-3xl">
                @foreach (['sewing' => __('Sewing'), 'finishing' => __('Finishing (Iron & Poly)'), 'cutting' => __('Extra Cutting')] as $key => $label)
                <button type="button" @click="stage = '{{ $key }}'"
                        :class="stage === '{{ $key }}' ? 'ring-2 ring-brand-600 bg-brand-50' : 'ring-1 ring-slate-200 hover:bg-slate-50'"
                        class="rounded-xl px-4 py-3 text-left transition">
                    <span class="flex items-center gap-2 text-sm font-semibold text-slate-800">
                        <span class="h-2 w-2 rounded-full {{ $stageStyle[$key]['dot'] }}"></span>{{ $label }}
                    </span>
                    <span class="mt-0.5 block text-xs text-slate-500">
                        @if ($available[$key] === null)
                            {{ __('more fabric issued') }}
                        @else
                            {{ $qty($available[$key]) }} {{ __('pcs waiting') }}
                        @endif
                    </span>
                </button>
                @endforeach
            </div>
            <x-input-error class="mt-2" :messages="$errors->get('stage')" />

            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3 max-w-3xl">
                <div>
                    <x-input-label for="quantity" :value="__('Pieces')" />
                    <input id="quantity" name="quantity" type="number" step="1" min="1" :max="available[stage] ?? null"
                           value="{{ old('quantity') }}" required
                           class="mt-1 block w-full rounded-lg border-slate-300 text-lg font-semibold focus:border-accent-500 focus:ring-accent-500">
                    <x-input-error class="mt-2" :messages="$errors->get('quantity')" />
                </div>
                <div>
                    <x-input-label for="entry_date" :value="__('Date')" />
                    <x-text-input id="entry_date" name="entry_date" type="date" class="mt-1 block w-full"
                                  :value="old('entry_date', now()->toDateString())" required />
                    <x-input-error class="mt-2" :messages="$errors->get('entry_date')" />
                </div>
                <div>
                    <x-input-label for="note" :value="__('Note (optional)')" />
                    <x-text-input id="note" name="note" type="text" class="mt-1 block w-full" :value="old('note')" />
                </div>
            </div>

            <!-- Optional material -->
            <div class="mt-4">
                <button type="button" @click="more = ! more" class="inline-flex items-center gap-1 text-sm font-semibold text-accent-600 hover:text-accent-800">
                    <svg class="h-4 w-4 transition" :class="more && 'rotate-90'" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
                    <span x-text="stage === 'cutting' ? '{{ __('Fabric used for extra cutting') }}' : '{{ __('Material used (thread, poly bag…)') }}'"></span>
                </button>

                <div x-show="more" x-cloak class="mt-3 max-w-3xl">
                    <div class="relative max-w-md" @click.outside="open = false">
                        <input type="text" x-model="query" @focus="open = true"
                               placeholder="{{ __('Search material in stock…') }}"
                               class="block w-full rounded-lg border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500">
                        <div x-show="open" x-cloak x-transition
                             class="absolute z-20 mt-1 w-full max-h-64 overflow-y-auto rounded-xl bg-white py-1.5 shadow-lg ring-1 ring-slate-200">
                            <template x-for="opt in filtered" :key="opt.id">
                                <div @click="addItem(opt)"
                                     class="flex cursor-pointer items-center justify-between gap-3 px-4 py-2 text-sm hover:bg-slate-50">
                                    <span x-text="opt.name" class="text-slate-700"></span>
                                    <span class="shrink-0 text-xs text-slate-400" x-text="opt.balance + ' ' + (opt.unit || '')"></span>
                                </div>
                            </template>
                            <div x-show="filtered.length === 0" class="px-4 py-2 text-sm text-slate-400">{{ __('No matching material in stock.') }}</div>
                        </div>
                    </div>

                    <table class="mt-3 w-full text-sm" x-show="items.length" x-cloak>
                        <thead>
                            <tr class="border-b border-slate-100 text-left text-xs uppercase tracking-wide text-slate-500">
                                <th class="py-2 pr-4 font-semibold">{{ __('Material') }}</th>
                                <th class="py-2 pr-4 font-semibold w-28">{{ __('In Stock') }}</th>
                                <th class="py-2 pr-4 font-semibold w-36">{{ __('Quantity Used') }}</th>
                                <th class="py-2 w-10"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <template x-for="(item, i) in items" :key="item.id">
                                <tr>
                                    <td class="py-2 pr-4">
                                        <input type="hidden" :name="'materials[' + i + '][item]'" :value="item.id">
                                        <span class="font-semibold text-slate-800" x-text="item.name"></span>
                                    </td>
                                    <td class="py-2 pr-4 text-slate-600" x-text="item.balance + ' ' + (item.unit || '')"></td>
                                    <td class="py-2 pr-4">
                                        <input type="number" step="0.01" min="0.01" :max="item.balance"
                                               :name="'materials[' + i + '][quantity]'" x-model.number="item.quantity"
                                               class="w-28 rounded-lg border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500" placeholder="0" required>
                                    </td>
                                    <td class="py-2 text-right">
                                        <button type="button" @click="removeItem(item.id)" class="text-slate-400 hover:text-rose-600" title="{{ __('Remove') }}">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                                        </button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                    @foreach ($errors->getMessages() as $key => $messages)
                        @if (str_starts_with($key, 'materials'))
                        <x-input-error class="mt-2" :messages="$messages" />
                        @endif
                    @endforeach
                </div>
            </div>

            <button type="submit"
                    class="mt-5 inline-flex items-center gap-2 rounded-lg bg-brand-800 px-5 py-2.5 text-sm font-semibold text-white hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-accent-500 focus:ring-offset-2">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                {{ __('Save Progress') }}
            </button>
            <p class="mt-2 text-xs text-emerald-700" x-show="stage === 'finishing'" x-cloak>
                {{ __('Finished pieces go straight into stock at :site.', ['site' => $order->site->name]) }}
            </p>
        </form>
        </div>
    </div>
    @endcan
    @endif

    <!-- Timeline -->
    <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <h3 class="text-sm font-semibold text-slate-800 mb-5">{{ __('Production History') }}</h3>
        <ol class="relative ml-2 border-l-2 border-slate-100">
            @foreach ($order->stageEntries as $entry)
            <li class="mb-6 ml-6 last:mb-0">
                <span class="absolute -left-[9px] mt-1 h-4 w-4 rounded-full ring-4 ring-white {{ $stageStyle[$entry->stage]['dot'] }}"></span>
                <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                    <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 {{ $stageStyle[$entry->stage]['chip'] }}">{{ __($entry->stage_label) }}</span>
                    <span class="text-base font-bold text-slate-800">{{ $qty($entry->quantity) }} {{ $unit }}</span>
                    <span class="text-xs text-slate-400">{{ $entry->entry_date->format('d M, Y') }} · {{ $entry->createdBy->name ?? '—' }}</span>
                </div>
                @if ($entry->materials->isNotEmpty())
                <div class="mt-1.5 flex flex-wrap gap-1.5">
                    @foreach ($entry->materials as $m)
                    <span class="inline-flex rounded-md bg-slate-50 px-2 py-0.5 text-xs text-slate-600 ring-1 ring-slate-200">
                        {{ $m->product->name }}: {{ $qty($m->quantity) }} {{ $m->product->stockUnit?->short_name }}
                    </span>
                    @endforeach
                </div>
                @endif
                @if ($entry->stage === 'finishing' && $entry->unit_cost !== null)
                <p class="mt-1 text-xs text-slate-500">{{ __('Stocked at :cost / pc', ['cost' => number_format($entry->unit_cost, 2)]) }}</p>
                @endif
                @if ($entry->note)
                <p class="mt-1 text-sm text-slate-500">{{ $entry->note }}</p>
                @endif
            </li>
            @endforeach
        </ol>
    </div>
</x-app-layout>
