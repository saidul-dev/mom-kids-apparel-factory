@php($qty = fn ($v) => rtrim(rtrim(number_format((float) $v, 4), '0'), '.') ?: '0')
@php($unit = $order->product->stockUnit?->short_name ?? 'pcs')
<x-app-layout>
    <x-slot name="title">{{ $order->production_no }}</x-slot>
    <x-slot name="header">
        <div>
            <h2 class="text-2xl font-bold text-brand-900">{{ $order->production_no }}</h2>
            <p class="text-sm text-slate-500 mt-0.5">{{ $order->item_label }} · {{ $order->site->name }}</p>
        </div>
    </x-slot>

    <div class="rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 p-6 mb-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="grid grid-cols-1 sm:grid-cols-4 gap-x-8 gap-y-3 text-sm">
                <div>
                    <span class="block text-[11px] font-semibold uppercase tracking-wide text-slate-400">{{ __('Started') }}</span>
                    <span class="block text-slate-700">{{ $order->start_date->format('d M, Y') }} {{ __('by') }} {{ $order->createdBy->name ?? '—' }}</span>
                </div>
                <div>
                    <span class="block text-[11px] font-semibold uppercase tracking-wide text-slate-400">{{ __('Planned') }}</span>
                    <span class="block text-slate-700">{{ $order->planned_quantity ? $qty($order->planned_quantity).' '.$unit : '—' }}</span>
                </div>
                <div>
                    <span class="block text-[11px] font-semibold uppercase tracking-wide text-slate-400">{{ __('Cost in WIP') }}</span>
                    <span class="block text-slate-700">{{ number_format($wipValue, 2) }}</span>
                </div>
                @if ($order->completed_at)
                <div>
                    <span class="block text-[11px] font-semibold uppercase tracking-wide text-slate-400">{{ __('Completed') }}</span>
                    <span class="block text-slate-700">{{ $order->completed_at->format('d M, Y') }} {{ __('by') }} {{ $order->completedBy->name ?? '—' }}</span>
                    @if ((float) $order->wastage_value > 0)
                    <span class="block text-xs text-rose-600">{{ __('Wastage written off') }}: {{ number_format($order->wastage_value, 2) }}</span>
                    @endif
                </div>
                @endif
                @if ($order->note)
                <div class="sm:col-span-4">
                    <span class="block text-[11px] font-semibold uppercase tracking-wide text-slate-400">{{ __('Note') }}</span>
                    <span class="block text-slate-700">{{ $order->note }}</span>
                </div>
                @endif
            </div>

            <div class="flex items-center gap-3">
                @if ($order->isOpen())
                    <span class="inline-flex rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-600 ring-1 ring-amber-200">{{ __('In Progress') }}</span>
                    @can('inventory.approve')
                    <form method="POST" action="{{ route('production-orders.complete', $order) }}"
                          onsubmit="return confirm('{{ $inProcess > 0
                              ? __(':n pcs are still unfinished — closing the lot writes their cost off as wastage. Continue?', ['n' => $qty($inProcess)])
                              : __('Mark this production lot as completed?') }}');">
                        @csrf
                        <button type="submit" class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                            {{ __('Complete Lot') }}
                        </button>
                    </form>
                    @endcan
                @else
                    <span class="inline-flex rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-600 ring-1 ring-emerald-200">{{ __('Completed') }}</span>
                @endif
            </div>
        </div>
    </div>

    <!-- Stage pipeline -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
        <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <span class="block text-[11px] font-semibold uppercase tracking-wide text-slate-400">1 · {{ __('Cutting') }}</span>
            <span class="mt-1 block text-2xl font-bold text-slate-800">{{ $qty($totals['cutting']) }} <span class="text-sm font-medium text-slate-400">{{ $unit }}</span></span>
            <span class="block text-xs text-slate-500">{{ __('pieces cut') }}</span>
        </div>
        <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <span class="block text-[11px] font-semibold uppercase tracking-wide text-slate-400">2 · {{ __('Sewing') }}</span>
            <span class="mt-1 block text-2xl font-bold text-slate-800">{{ $qty($totals['sewing']) }} <span class="text-sm font-medium text-slate-400">{{ $unit }}</span></span>
            <span class="block text-xs {{ $available['sewing'] > 0 ? 'text-amber-600' : 'text-slate-500' }}">{{ $qty($available['sewing']) }} {{ __('waiting for sewing') }}</span>
        </div>
        <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <span class="block text-[11px] font-semibold uppercase tracking-wide text-slate-400">3 · {{ __('Finishing (Iron & Poly)') }}</span>
            <span class="mt-1 block text-2xl font-bold text-slate-800">{{ $qty($totals['finishing']) }} <span class="text-sm font-medium text-slate-400">{{ $unit }}</span></span>
            <span class="block text-xs {{ $available['finishing'] > 0 ? 'text-amber-600' : 'text-slate-500' }}">{{ $qty($available['finishing']) }} {{ __('waiting for finishing') }}</span>
        </div>
        <div class="rounded-2xl bg-emerald-50 p-5 shadow-sm ring-1 ring-emerald-200">
            <span class="block text-[11px] font-semibold uppercase tracking-wide text-emerald-700">{{ __('Finished Stock') }}</span>
            <span class="mt-1 block text-2xl font-bold text-emerald-800">{{ $qty($totals['finishing']) }} <span class="text-sm font-medium text-emerald-600">{{ $unit }}</span></span>
            <span class="block text-xs text-emerald-700">{{ __('added to stock at :site', ['site' => $order->site->name]) }}</span>
        </div>
    </div>

    @if ($order->isOpen())
    @can('inventory.create')
    <div x-data="transferCart(@js(['itemOptions' => $materialOptions]))" class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200 mb-4">
        <h3 class="text-base font-semibold text-slate-800 mb-4">{{ __('Record Stage Progress') }}</h3>

        <form method="POST" action="{{ route('production-orders.stages.store', $order) }}"
              x-data="{ stage: @js(old('stage', 'cutting')), available: @js($available) }">
            @csrf

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 max-w-3xl">
                <div>
                    <x-input-label for="stage" :value="__('Stage')" />
                    <select id="stage" name="stage" x-model="stage"
                            class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500">
                        @foreach (\App\Models\ProductionOrder::STAGES as $key => $label)
                            <option value="{{ $key }}">{{ __($label) }}</option>
                        @endforeach
                    </select>
                    <x-input-error class="mt-2" :messages="$errors->get('stage')" />
                </div>
                <div>
                    <x-input-label for="quantity" :value="__('Pieces')" />
                    <input id="quantity" name="quantity" type="number" step="1" min="1" :max="available[stage] ?? null"
                           value="{{ old('quantity') }}" required
                           class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500">
                    <p class="mt-1 text-xs text-slate-400" x-show="available[stage] !== null" x-cloak>
                        <span x-text="available[stage]"></span> {{ __('pcs waiting for this stage') }}
                    </p>
                    <x-input-error class="mt-2" :messages="$errors->get('quantity')" />
                </div>
                <div>
                    <x-input-label for="entry_date" :value="__('Date')" />
                    <x-text-input id="entry_date" name="entry_date" type="date" class="mt-1 block w-full"
                                  :value="old('entry_date', now()->toDateString())" required />
                    <x-input-error class="mt-2" :messages="$errors->get('entry_date')" />
                </div>
            </div>

            <!-- Material issue -->
            <div class="mt-5">
                <x-input-label :value="__('Material Used (optional)')" />
                <p class="text-xs text-slate-400">{{ __('e.g. fabric at cutting, thread at sewing, poly bags at finishing — deducted from stock at :site.', ['site' => $order->site->name]) }}</p>
                <div class="relative mt-2 max-w-md" @click.outside="open = false">
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

                <div class="mt-3 max-w-3xl overflow-x-auto" x-show="items.length" x-cloak>
                    <table class="w-full text-sm">
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
                                        <input type="number" step="0.0001" min="0.0001" :max="item.balance"
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
                </div>
                @foreach ($errors->getMessages() as $key => $messages)
                    @if (str_starts_with($key, 'materials'))
                    <x-input-error class="mt-2" :messages="$messages" />
                    @endif
                @endforeach
            </div>

            <div class="mt-5 max-w-3xl">
                <x-input-label for="note" :value="__('Note (optional)')" />
                <textarea id="note" name="note" rows="2"
                          class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500">{{ old('note') }}</textarea>
            </div>

            <button type="submit"
                    class="mt-5 inline-flex items-center gap-2 rounded-lg bg-brand-800 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-accent-500 focus:ring-offset-2">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                {{ __('Save Entry') }}
            </button>
            <p class="mt-2 text-xs text-slate-400" x-show="stage === 'finishing'" x-cloak>
                {{ __('Finished pieces are added to stock at :site.', ['site' => $order->site->name]) }}
            </p>
        </form>
    </div>
    @endcan
    @endif

    <!-- History -->
    <div class="rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 overflow-hidden">
        <div class="border-b border-slate-100 px-5 py-3">
            <h3 class="text-sm font-semibold text-slate-800">{{ __('Stage History') }}</h3>
        </div>
        <div class="overflow-x-auto">
        <table class="w-full min-w-[820px] text-sm">
            <thead>
                <tr class="border-b border-slate-100 bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <th class="px-5 py-3 font-semibold">{{ __('Date') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('Stage') }}</th>
                    <th class="px-5 py-3 font-semibold text-right">{{ __('Pieces') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('Material Used') }}</th>
                    <th class="px-5 py-3 font-semibold text-right">{{ __('Cost / pc') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('By') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($order->stageEntries as $entry)
                <tr class="hover:bg-slate-50 align-top">
                    <td class="px-5 py-3 text-slate-600">{{ $entry->entry_date->format('d M, Y') }}</td>
                    <td class="px-5 py-3">
                        <span class="font-semibold text-slate-800">{{ __($entry->stage_label) }}</span>
                        @if ($entry->note)
                        <span class="block text-xs text-slate-400">{{ $entry->note }}</span>
                        @endif
                    </td>
                    <td class="px-5 py-3 text-right font-semibold text-slate-800">{{ $qty($entry->quantity) }}</td>
                    <td class="px-5 py-3 text-slate-600">
                        @forelse ($entry->materials as $m)
                            <span class="block">
                                {{ $m->product->name }}{{ $m->productVariant ? ' — '.$m->productVariant->label : '' }}:
                                {{ $qty($m->quantity) }} {{ $m->product->stockUnit?->short_name }}
                            </span>
                        @empty
                            <span class="text-slate-300">—</span>
                        @endforelse
                    </td>
                    <td class="px-5 py-3 text-right text-slate-600">{{ $entry->unit_cost !== null ? number_format($entry->unit_cost, 2) : '—' }}</td>
                    <td class="px-5 py-3 text-slate-600">{{ $entry->createdBy->name ?? '—' }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="px-5 py-10 text-center text-slate-400">{{ __('Nothing recorded yet — start with Cutting.') }}</td>
                </tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </div>
</x-app-layout>
