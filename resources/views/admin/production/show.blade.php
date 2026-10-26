@php
    $qty = fn ($v) => rtrim(rtrim(number_format((float) $v, 2), '0'), '.') ?: '0';
    $unit = $order->product->stockUnit?->short_name ?? 'Pcs';
    $cut = $totals['cutting'];
    $pct = fn ($v) => $cut > 0 ? min(100, round($v / $cut * 100)) : 0;
    $color = [
        'cutting' => ['dot' => 'bg-amber-400', 'soft' => 'bg-amber-50 ring-amber-200', 'text' => 'text-amber-700', 'btn' => 'bg-amber-500 hover:bg-amber-600', 'chip' => 'bg-amber-50 text-amber-700 ring-amber-200'],
        'sewing' => ['dot' => 'bg-sky-500', 'soft' => 'bg-sky-50 ring-sky-200', 'text' => 'text-sky-700', 'btn' => 'bg-sky-600 hover:bg-sky-700', 'chip' => 'bg-sky-50 text-sky-700 ring-sky-200'],
        'finishing' => ['dot' => 'bg-violet-500', 'soft' => 'bg-violet-50 ring-violet-200', 'text' => 'text-violet-700', 'btn' => 'bg-violet-600 hover:bg-violet-700', 'chip' => 'bg-violet-50 text-violet-700 ring-violet-200'],
        'packing' => ['dot' => 'bg-emerald-500', 'soft' => 'bg-emerald-50 ring-emerald-200', 'text' => 'text-emerald-700', 'btn' => 'bg-emerald-600 hover:bg-emerald-700', 'chip' => 'bg-emerald-50 text-emerald-700 ring-emerald-200'],
    ];
    // What each stage card shows: pieces sitting there now, and how many have passed through it.
    $pipeline = [
        ['key' => 'cutting', 'label' => __('Cutting'), 'now' => $current['cutting'], 'nowLabel' => __('waiting for sewing'), 'total' => $totals['cutting'], 'totalLabel' => __('pcs cut')],
        ['key' => 'sewing', 'label' => __('Sewing'), 'now' => $current['sewing'], 'nowLabel' => __('waiting for finishing'), 'total' => $totals['sewing'], 'totalLabel' => __('pcs sewn')],
        ['key' => 'finishing', 'label' => __('Finishing (Iron)'), 'now' => $current['finishing'], 'nowLabel' => __('waiting for blister'), 'total' => $totals['finishing'], 'totalLabel' => __('pcs ironed')],
        ['key' => 'packing', 'label' => __('Finished (Blister / Poly)'), 'now' => $current['stock'], 'nowLabel' => __('in finished stock'), 'total' => null, 'totalLabel' => null],
    ];
    $quick = [
        'sewing' => [__('Sewing'), __('from cutting')],
        'finishing' => [__('Finishing (Iron)'), __('from sewing')],
        'packing' => [__('Blister / Poly'), __('from finishing → finished stock')],
    ];
    $materials = $fabrics->concat($accessories);
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
                <p class="text-sm text-slate-500 mt-0.5">{{ __('Everything about this production on one screen') }}</p>
            </div>
        </div>
    </x-slot>

    <!-- Header -->
    <div class="mb-4 rounded-2xl bg-gradient-to-r from-brand-900 to-brand-700 p-6 text-white shadow-sm">
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
                <div class="text-right">
                    <span class="block text-4xl font-bold">{{ $pct($current['stock']) }}%</span>
                    <span class="block text-xs text-brand-200">{{ __('finished') }}</span>
                </div>
                @if ($order->isOpen())
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
                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-300"></span>{{ __('Completed') }} · {{ $order->completed_at->format('d M, Y') }}
                    </span>
                @endif
            </div>
        </div>
    </div>

    <!-- Where the pieces are right now -->
    <div class="mb-4 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <div class="mb-4 flex flex-wrap items-baseline justify-between gap-2">
            <h3 class="text-base font-semibold text-slate-800">{{ __('Where the :n pcs are right now', ['n' => $qty($cut)]) }}</h3>
            @if ($inProcess > 0)
            <span class="text-sm text-slate-500">{{ $qty($inProcess) }} {{ __('pcs still in process') }}</span>
            @endif
        </div>

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($pipeline as $i => $step)
            <div class="relative rounded-2xl p-5 ring-1 {{ $step['now'] > 0 ? $color[$step['key']]['soft'] : 'bg-slate-50 ring-slate-200' }}">
                @if ($i < 3)
                <svg class="absolute -right-3 top-1/2 z-10 hidden h-6 w-6 -translate-y-1/2 rounded-full bg-white p-1 text-slate-400 shadow ring-1 ring-slate-200 lg:block" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
                @endif
                <span class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide {{ $color[$step['key']]['text'] }}">
                    <span class="h-2 w-2 rounded-full {{ $color[$step['key']]['dot'] }}"></span>{{ $step['label'] }}
                </span>
                <span class="mt-2 block text-4xl font-bold text-slate-800">{{ $qty($step['now']) }}<span class="ml-1 text-base font-medium text-slate-400">{{ $unit }}</span></span>
                <span class="block text-sm text-slate-500">{{ $step['nowLabel'] }}</span>
                @if ($step['total'] !== null)
                <span class="mt-3 block border-t border-black/5 pt-2 text-xs text-slate-500">{{ __('Total') }}: <b class="text-slate-700">{{ $qty($step['total']) }}</b> {{ $step['totalLabel'] }}</span>
                @else
                <span class="mt-3 block border-t border-black/5 pt-2 text-xs text-slate-500">{{ __('at :site', ['site' => $order->site->name]) }}</span>
                @endif
            </div>
            @endforeach
        </div>

        @if ($cut > 0)
        <div class="mt-5 flex h-3 w-full overflow-hidden rounded-full bg-slate-100">
            <div class="bg-emerald-500" style="width: {{ $pct($current['stock']) }}%" title="{{ __('Finished') }}"></div>
            <div class="bg-violet-500" style="width: {{ $pct($current['finishing']) }}%" title="{{ __('At finishing') }}"></div>
            <div class="bg-sky-500" style="width: {{ $pct($current['sewing']) }}%" title="{{ __('At sewing') }}"></div>
            <div class="bg-amber-400" style="width: {{ $pct($current['cutting']) }}%" title="{{ __('At cutting') }}"></div>
        </div>
        @endif
    </div>

    <div class="mb-4 grid grid-cols-1 gap-4 lg:grid-cols-3">
        <!-- Material used -->
        <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 lg:col-span-2">
            <div class="border-b border-slate-100 px-5 py-3">
                <h3 class="text-sm font-semibold text-slate-800">{{ __('Material Used') }}</h3>
            </div>
            <div class="overflow-x-auto">
            <table class="w-full min-w-[520px] text-sm">
                <thead>
                    <tr class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                        <th class="px-5 py-2.5 font-semibold">{{ __('Item') }}</th>
                        <th class="px-5 py-2.5 font-semibold">{{ __('Used at') }}</th>
                        <th class="px-5 py-2.5 font-semibold text-right">{{ __('Quantity') }}</th>
                        <th class="px-5 py-2.5 font-semibold text-right">{{ __('Cost') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($materials as $m)
                    <tr>
                        <td class="px-5 py-2.5 font-semibold text-slate-800">{{ $m->product->name }}{{ $m->productVariant ? ' — '.$m->productVariant->label : '' }}</td>
                        <td class="px-5 py-2.5">
                            <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold ring-1 {{ $color[$m->stage]['chip'] }}">{{ __(\App\Models\ProductionOrder::STAGES[$m->stage]) }}</span>
                        </td>
                        <td class="px-5 py-2.5 text-right text-slate-800">{{ $qty($m->quantity) }} {{ $m->product->stockUnit?->short_name }}</td>
                        <td class="px-5 py-2.5 text-right text-slate-600">{{ number_format((float) $m->quantity * (float) $m->unit_cost, 2) }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="4" class="px-5 py-6 text-center text-slate-400">{{ __('No material recorded.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
            </div>
        </div>

        <!-- Numbers -->
        <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <h3 class="mb-4 text-sm font-semibold text-slate-800">{{ __('Cost') }}</h3>
            <dl class="space-y-3 text-sm">
                <div class="flex justify-between"><dt class="text-slate-500">{{ __('Material cost') }}</dt><dd class="font-semibold text-slate-800">{{ number_format($materialCost, 2) }}</dd></div>
                @if ($cut > 0)
                <div class="flex justify-between"><dt class="text-slate-500">{{ __('Material cost / pc') }}</dt><dd class="font-semibold text-slate-800">{{ number_format($materialCost / $cut, 2) }}</dd></div>
                @endif
                @php
                    $fabricUnits = $fabrics->map(fn ($m) => $m->product->stockUnit?->short_name)->filter()->unique();
                @endphp
                @if ($cut > 0 && $fabricUnits->count() === 1)
                <div class="flex justify-between"><dt class="text-slate-500">{{ __('Fabric / pc') }}</dt><dd class="font-semibold text-slate-800">{{ number_format($fabrics->sum(fn ($m) => (float) $m->quantity) / $cut, 3) }} {{ $fabricUnits->first() }}</dd></div>
                @endif
                <div class="flex justify-between"><dt class="text-slate-500">{{ __('Still in process (WIP)') }}</dt><dd class="font-semibold text-slate-800">{{ number_format($wipValue, 2) }}</dd></div>
                @if ((float) $order->wastage_value > 0)
                <div class="flex justify-between"><dt class="text-rose-600">{{ __('Wastage written off') }}</dt><dd class="font-semibold text-rose-600">{{ number_format($order->wastage_value, 2) }}</dd></div>
                @endif
            </dl>
            @if ($order->note)
            <p class="mt-4 border-t border-slate-100 pt-3 text-sm text-slate-600">{{ $order->note }}</p>
            @endif
        </div>
    </div>

    @if ($order->isOpen())
    @can('inventory.create')
    <!-- Quick update -->
    <div class="mb-4">
        <h3 class="mb-3 text-base font-semibold text-slate-800">{{ __('Update Progress') }}</h3>
        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
            @foreach ($quick as $stage => [$label, $from])
            @php
                $waiting = (float) $available[$stage];
            @endphp
            <form method="POST" action="{{ route('production-orders.stages.store', $order) }}"
                  x-data="{ qty: @js(old('stage') === $stage ? old('quantity') : '') }"
                  class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200 {{ $waiting <= 0 ? 'opacity-60' : '' }}">
                @csrf
                <input type="hidden" name="stage" value="{{ $stage }}">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <span class="flex items-center gap-2 text-sm font-semibold text-slate-800">
                            <span class="h-2.5 w-2.5 rounded-full {{ $color[$stage]['dot'] }}"></span>{{ $label }}
                        </span>
                        <span class="block text-xs text-slate-400">{{ $from }}</span>
                    </div>
                    <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 {{ $waiting > 0 ? $color[$stage]['chip'] : 'bg-slate-50 text-slate-400 ring-slate-200' }}">
                        {{ $qty($waiting) }} {{ __('waiting') }}
                    </span>
                </div>

                <div class="mt-4 flex items-center gap-2">
                    <div class="flex flex-1 items-center overflow-hidden rounded-lg ring-1 ring-slate-300 focus-within:ring-2 focus-within:ring-accent-500">
                        <input type="number" name="quantity" step="1" min="1" max="{{ $waiting }}" x-model="qty" required @disabled($waiting <= 0)
                               class="w-full border-0 text-right text-lg font-bold focus:ring-0" placeholder="0">
                        <span class="bg-slate-100 px-3 py-2.5 text-xs font-semibold text-slate-500">{{ __('pcs') }}</span>
                    </div>
                    <button type="button" @click="qty = {{ $waiting }}" @disabled($waiting <= 0)
                            class="rounded-lg px-3 py-2.5 text-xs font-semibold text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50 disabled:cursor-not-allowed">{{ __('All') }}</button>
                </div>
                @if (old('stage') === $stage)
                <x-input-error class="mt-2" :messages="$errors->get('quantity')" />
                @endif

                <div class="mt-3 flex items-center gap-2">
                    <input type="date" name="entry_date" value="{{ now()->toDateString() }}" required
                           class="flex-1 rounded-lg border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500">
                    <button type="submit" @disabled($waiting <= 0)
                            class="rounded-lg px-4 py-2 text-sm font-semibold text-white shadow-sm disabled:cursor-not-allowed {{ $color[$stage]['btn'] }}">
                        {{ __('Save') }}
                    </button>
                </div>
            </form>
            @endforeach
        </div>
    </div>

    <!-- Extra: more cutting or material issue -->
    <details class="mb-4 rounded-2xl bg-white shadow-sm ring-1 ring-slate-200" @if (old('materials') || old('stage') === 'cutting') open @endif>
        <summary class="cursor-pointer select-none px-5 py-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">
            {{ __('More: extra cutting or issue material (thread, poly bag…)') }}
        </summary>
        <div class="border-t border-slate-100 p-5"
             x-data="{ stage: @js(old('stage', 'cutting')) }">
            <div x-data="transferCart(@js(['itemOptions' => $materialOptions]))">
            <form method="POST" action="{{ route('production-orders.stages.store', $order) }}">
                @csrf
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
                    <div>
                        <x-input-label for="more_stage" :value="__('Stage')" />
                        <select id="more_stage" name="stage" x-model="stage"
                                class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500">
                            @foreach (\App\Models\ProductionOrder::STAGES as $key => $label)
                                <option value="{{ $key }}">{{ $key === 'cutting' ? __('Extra Cutting') : __($label) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <x-input-label for="more_quantity" :value="__('Pieces')" />
                        <x-text-input id="more_quantity" name="quantity" type="number" step="1" min="1" class="mt-1 block w-full" required />
                    </div>
                    <div>
                        <x-input-label for="more_date" :value="__('Date')" />
                        <x-text-input id="more_date" name="entry_date" type="date" class="mt-1 block w-full" :value="now()->toDateString()" required />
                    </div>
                    <div>
                        <x-input-label for="more_note" :value="__('Note')" />
                        <x-text-input id="more_note" name="note" type="text" class="mt-1 block w-full" />
                    </div>
                </div>

                <div class="relative mt-4 max-w-md" @click.outside="open = false">
                    <input type="text" x-model="query" @focus="open = true"
                           placeholder="{{ __('Add material in stock…') }}"
                           class="block w-full rounded-lg border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500">
                    <div x-show="open" x-cloak x-transition
                         class="absolute z-20 mt-1 w-full max-h-64 overflow-y-auto rounded-xl bg-white py-1.5 shadow-lg ring-1 ring-slate-200">
                        <template x-for="opt in filtered" :key="opt.id">
                            <div @click="addItem(opt)" class="flex cursor-pointer items-center justify-between gap-3 px-4 py-2 text-sm hover:bg-slate-50">
                                <span x-text="opt.name" class="text-slate-700"></span>
                                <span class="shrink-0 text-xs text-slate-400" x-text="opt.balance + ' ' + (opt.unit || '')"></span>
                            </div>
                        </template>
                        <div x-show="filtered.length === 0" class="px-4 py-2 text-sm text-slate-400">{{ __('No matching material in stock.') }}</div>
                    </div>
                </div>

                <div class="mt-3 space-y-2">
                    <template x-for="(item, i) in items" :key="item.id">
                        <div class="flex flex-wrap items-center gap-3 rounded-lg bg-slate-50 px-3 py-2 ring-1 ring-slate-200">
                            <input type="hidden" :name="'materials[' + i + '][item]'" :value="item.id">
                            <span class="min-w-0 flex-1 truncate text-sm font-semibold text-slate-800" x-text="item.name"></span>
                            <span class="text-xs text-slate-500" x-text="'{{ __('stock') }}: ' + item.balance"></span>
                            <input type="number" step="0.01" min="0.01" :max="item.balance" :name="'materials[' + i + '][quantity]'" x-model.number="item.quantity" required
                                   class="w-24 rounded-lg border-slate-300 text-right text-sm focus:border-accent-500 focus:ring-accent-500" placeholder="0">
                            <span class="w-10 text-xs text-slate-500" x-text="item.unit || ''"></span>
                            <button type="button" @click="removeItem(item.id)" class="text-slate-400 hover:text-rose-600">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                            </button>
                        </div>
                    </template>
                </div>
                @foreach ($errors->getMessages() as $key => $messages)
                    @if (str_starts_with($key, 'materials') || (old('materials') && $key === 'quantity'))
                    <x-input-error class="mt-2" :messages="$messages" />
                    @endif
                @endforeach

                <button type="submit" class="mt-4 rounded-lg bg-brand-800 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Save') }}</button>
            </form>
            </div>
        </div>
    </details>
    @endcan
    @endif

    <!-- History -->
    <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <h3 class="mb-5 text-sm font-semibold text-slate-800">{{ __('History') }}</h3>
        <ol class="relative ml-2 border-l-2 border-slate-100">
            @foreach ($order->stageEntries as $entry)
            <li class="mb-5 ml-6 last:mb-0">
                <span class="absolute -left-[9px] mt-1 h-4 w-4 rounded-full ring-4 ring-white {{ $color[$entry->stage]['dot'] }}"></span>
                <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                    <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 {{ $color[$entry->stage]['chip'] }}">{{ __($entry->stage_label) }}</span>
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
                @if ($entry->stage === \App\Models\ProductionOrder::FINAL_STAGE && $entry->unit_cost !== null)
                <p class="mt-1 text-xs text-slate-500">{{ __('Added to stock at :cost / pc', ['cost' => number_format($entry->unit_cost, 2)]) }}</p>
                @endif
                @if ($entry->note)
                <p class="mt-1 text-sm text-slate-500">{{ $entry->note }}</p>
                @endif
            </li>
            @endforeach
        </ol>
    </div>
</x-app-layout>
