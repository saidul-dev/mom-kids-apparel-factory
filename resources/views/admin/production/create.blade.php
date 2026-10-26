<x-app-layout>
    <x-slot name="title">{{ __('New Production') }}</x-slot>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <a href="{{ route('production-orders.index') }}" class="text-slate-400 hover:text-slate-600" title="{{ __('Back') }}">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/></svg>
            </a>
            <div>
                <h2 class="text-2xl font-bold text-brand-900">{{ __('New Production') }}</h2>
                <p class="text-sm text-slate-500 mt-0.5">{{ __('Fabric in, pieces out — one item, one size per production.') }}</p>
            </div>
        </div>
    </x-slot>

    @if (! $site)
        {{-- Step 0: pick the factory --}}
        <div class="mx-auto max-w-3xl">
            <h3 class="mb-1 text-lg font-semibold text-slate-800">{{ __('Where is it being made?') }}</h3>
            <p class="mb-5 text-sm text-slate-500">{{ __('Fabric is taken from this site\'s stock.') }}</p>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                @foreach ($sites->sortByDesc(fn ($s) => $s->type === 'Factory') as $s)
                <a href="{{ route('production-orders.create', ['site_id' => $s->id]) }}"
                   class="group flex items-center gap-4 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200 transition hover:-translate-y-0.5 hover:shadow-md hover:ring-brand-300">
                    <span class="grid h-12 w-12 shrink-0 place-items-center rounded-xl {{ $s->type === 'Factory' ? 'bg-brand-800 text-white' : 'bg-slate-100 text-slate-500' }}">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Z"/></svg>
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block font-semibold text-slate-800">{{ $s->name }}</span>
                        <span class="block text-xs text-slate-500">{{ $s->type }}</span>
                    </span>
                    <svg class="h-5 w-5 text-slate-300 group-hover:text-brand-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
                </a>
                @endforeach
            </div>
        </div>
    @else
        <div x-data="{ cut: @js(old('cut_quantity', '')), itemName: '' }"
             @option-selected-item="itemName = $event.detail ? $event.detail.name : ''">
        <div x-data="transferCart(@js(['itemOptions' => $fabricOptions]))">
            <form method="POST" action="{{ route('production-orders.store') }}" class="grid grid-cols-1 gap-5 lg:grid-cols-3">
                @csrf
                <input type="hidden" name="site_id" value="{{ $site->id }}">

                <div class="space-y-5 lg:col-span-2">
                    <!-- 1. What -->
                    <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
                        <div class="mb-5 flex items-center gap-3">
                            <span class="grid h-8 w-8 place-items-center rounded-full bg-brand-800 text-sm font-bold text-white">1</span>
                            <div>
                                <h3 class="font-semibold text-slate-800">{{ __('What are you making?') }}</h3>
                                <p class="text-xs text-slate-500">{{ __('Pick the item and its size. Another size = another production.') }}</p>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <div class="sm:col-span-2">
                                <x-input-label for="item" :value="__('Item — Size')" />
                                <div class="mt-1">
                                    <x-searchable-select name="item" :options="$items" :selected="old('item')"
                                                          placeholder="{{ __('Search finished goods…') }}" />
                                </div>
                                <x-input-error class="mt-2" :messages="$errors->get('item')" />
                                @if ($items->isEmpty())
                                <p class="mt-1 text-xs text-amber-600">{{ __('No finished goods yet —') }} <a href="{{ route('products.create', ['type' => 'finished_good']) }}" class="underline">{{ __('add one') }}</a>.</p>
                                @endif
                            </div>
                            <div>
                                <x-input-label for="start_date" :value="__('Cutting Date')" />
                                <x-text-input id="start_date" name="start_date" type="date" class="mt-1 block w-full"
                                              :value="old('start_date', now()->toDateString())" required />
                                <x-input-error class="mt-2" :messages="$errors->get('start_date')" />
                            </div>
                        </div>
                    </section>

                    <!-- 2. Fabric -->
                    <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
                        <div class="mb-5 flex items-center gap-3">
                            <span class="grid h-8 w-8 place-items-center rounded-full bg-brand-800 text-sm font-bold text-white">2</span>
                            <div>
                                <h3 class="font-semibold text-slate-800">{{ __('Fabric used') }}</h3>
                                <p class="text-xs text-slate-500">{{ __('Enter how much was used, in the fabric\'s own unit.') }}</p>
                            </div>
                        </div>

                        <div class="relative" @click.outside="open = false">
                            <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/></svg>
                            <input type="text" x-model="query" @focus="open = true"
                                   placeholder="{{ __('Search fabric in stock at :site…', ['site' => $site->name]) }}"
                                   class="block w-full rounded-xl border-slate-300 py-2.5 pl-9 text-sm focus:border-accent-500 focus:ring-accent-500">
                            <div x-show="open" x-cloak x-transition
                                 class="absolute z-20 mt-1 w-full max-h-64 overflow-y-auto rounded-xl bg-white py-1.5 shadow-lg ring-1 ring-slate-200">
                                <template x-for="opt in filtered" :key="opt.id">
                                    <div @click="addItem(opt)"
                                         class="flex cursor-pointer items-center justify-between gap-3 px-4 py-2.5 text-sm hover:bg-brand-50">
                                        <span x-text="opt.name" class="text-slate-700"></span>
                                        <span class="shrink-0 rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-500" x-text="opt.balance + ' ' + (opt.unit || '')"></span>
                                    </div>
                                </template>
                                <div x-show="filtered.length === 0" class="px-4 py-2 text-sm text-slate-400">{{ __('No matching fabric in stock.') }}</div>
                            </div>
                        </div>
                        @if ($fabricOptions->isEmpty())
                        <p class="mt-2 text-xs text-amber-600">{{ __('No raw material in stock at :site — purchase fabric first.', ['site' => $site->name]) }}</p>
                        @endif
                        <x-input-error class="mt-2" :messages="$errors->get('fabrics')" />
                        @foreach ($errors->getMessages() as $key => $messages)
                            @if (str_starts_with($key, 'fabrics.'))
                            <x-input-error class="mt-2" :messages="$messages" />
                            @endif
                        @endforeach

                        <div class="mt-4 space-y-3">
                            <template x-for="(item, i) in items" :key="item.id">
                                <div class="flex flex-wrap items-center gap-4 rounded-xl bg-slate-50 p-4 ring-1 ring-slate-200">
                                    <input type="hidden" :name="'fabrics[' + i + '][item]'" :value="item.id">
                                    <div class="min-w-0 flex-1">
                                        <span class="block truncate font-semibold text-slate-800" x-text="item.name"></span>
                                        <span class="mt-0.5 block text-xs"
                                              :class="(Number(item.quantity) || 0) > item.balance ? 'text-rose-600 font-semibold' : 'text-slate-500'"
                                              x-text="'{{ __('In stock') }}: ' + item.balance + ' ' + (item.unit || '') + ' · {{ __('left after') }}: ' + +(item.balance - (Number(item.quantity) || 0)).toFixed(2) + ' ' + (item.unit || '')"></span>
                                    </div>
                                    <div class="flex items-center overflow-hidden rounded-lg bg-white ring-1 ring-slate-300 focus-within:ring-2 focus-within:ring-accent-500">
                                        <input type="number" step="0.01" min="0.01" :max="item.balance"
                                               :name="'fabrics[' + i + '][quantity]'" x-model.number="item.quantity"
                                               class="w-28 border-0 text-right text-base font-semibold focus:ring-0" placeholder="0" required>
                                        <span class="bg-slate-100 px-3 py-2 text-sm font-medium text-slate-600" x-text="item.unit || ''"></span>
                                    </div>
                                    <button type="button" @click="removeItem(item.id)" class="rounded-lg p-1.5 text-slate-400 hover:bg-rose-50 hover:text-rose-600" title="{{ __('Remove') }}">
                                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                                    </button>
                                </div>
                            </template>
                            <div x-show="items.length === 0" class="rounded-xl border-2 border-dashed border-slate-200 p-6 text-center text-sm text-slate-400">
                                {{ __('Search above and pick the fabric used for this cutting.') }}
                            </div>
                        </div>
                    </section>

                    <!-- 3. Cutting -->
                    <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
                        <div class="mb-5 flex items-center gap-3">
                            <span class="grid h-8 w-8 place-items-center rounded-full bg-brand-800 text-sm font-bold text-white">3</span>
                            <div>
                                <h3 class="font-semibold text-slate-800">{{ __('How many pieces were cut?') }}</h3>
                                <p class="text-xs text-slate-500">{{ __('Sewing, finishing and blister are entered later on the production page.') }}</p>
                            </div>
                        </div>
                        <div class="flex flex-wrap items-end gap-6">
                            <div>
                                <div class="flex items-center overflow-hidden rounded-xl ring-1 ring-slate-300 focus-within:ring-2 focus-within:ring-accent-500">
                                    <input id="cut_quantity" name="cut_quantity" type="number" step="1" min="1" x-model.number="cut" required
                                           class="w-40 border-0 py-3 text-right text-2xl font-bold text-slate-800 focus:ring-0" placeholder="0">
                                    <span class="bg-slate-100 px-4 py-4 text-sm font-semibold text-slate-600">{{ __('pcs') }}</span>
                                </div>
                                <x-input-error class="mt-2" :messages="$errors->get('cut_quantity')" />
                            </div>
                            <div class="flex-1 min-w-[12rem]">
                                <x-input-label for="note" :value="__('Note (optional)')" />
                                <x-text-input id="note" name="note" type="text" class="mt-1 block w-full" :value="old('note')"
                                              placeholder="{{ __('e.g. cutting master, lot remarks') }}" />
                                <x-input-error class="mt-2" :messages="$errors->get('note')" />
                            </div>
                        </div>
                    </section>
                </div>

                <!-- Summary -->
                <aside class="lg:sticky lg:top-6 h-fit">
                    <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
                        <div class="bg-gradient-to-r from-brand-900 to-brand-700 px-5 py-4 text-white">
                            <span class="block text-[11px] font-semibold uppercase tracking-widest text-brand-200">{{ __('Production Summary') }}</span>
                            <span class="mt-1 block text-sm text-brand-100">{{ __('Tracking no. is generated on save') }}</span>
                        </div>
                        <dl class="divide-y divide-slate-100 text-sm">
                            <div class="flex items-center justify-between gap-3 px-5 py-3">
                                <dt class="text-slate-500">{{ __('Factory') }}</dt>
                                <dd class="text-right font-semibold text-slate-800">
                                    {{ $site->name }}
                                    <a href="{{ route('production-orders.create') }}" class="ml-1 text-xs font-medium text-accent-600 hover:text-accent-800">{{ __('change') }}</a>
                                </dd>
                            </div>
                            <div class="flex items-center justify-between gap-3 px-5 py-3">
                                <dt class="text-slate-500">{{ __('Item') }}</dt>
                                <dd class="truncate text-right font-semibold text-slate-800" x-text="itemName || '—'"></dd>
                            </div>
                            <div class="px-5 py-3">
                                <dt class="text-slate-500">{{ __('Fabric') }}</dt>
                                <dd class="mt-1 space-y-1">
                                    <template x-for="item in items" :key="item.id">
                                        <div class="flex justify-between gap-2">
                                            <span class="truncate text-slate-700" x-text="item.name.replace(/\s\([^)]*\)$/, '')"></span>
                                            <span class="shrink-0 font-semibold text-slate-800" x-text="(Number(item.quantity) || 0) + ' ' + (item.unit || '')"></span>
                                        </div>
                                    </template>
                                    <span x-show="items.length === 0" class="text-slate-400">—</span>
                                </dd>
                            </div>
                            <div class="flex items-center justify-between gap-3 px-5 py-3">
                                <dt class="text-slate-500">{{ __('Pieces cut') }}</dt>
                                <dd class="text-xl font-bold text-brand-900" x-text="(Number(cut) || 0) + ' pcs'"></dd>
                            </div>
                            <template x-if="cut > 0 && items.length === 1 && Number(items[0].quantity) > 0">
                                <div class="flex items-center justify-between gap-3 px-5 py-3">
                                    <dt class="text-slate-500">{{ __('Fabric per piece') }}</dt>
                                    <dd class="font-semibold text-slate-800" x-text="(Number(items[0].quantity) / cut).toFixed(3) + ' ' + (items[0].unit || '')"></dd>
                                </div>
                            </template>
                            <template x-if="cut > 0 && items.length">
                                <div class="flex items-center justify-between gap-3 px-5 py-3">
                                    <dt class="text-slate-500">{{ __('Est. material cost / pc') }}</dt>
                                    <dd class="font-semibold text-slate-800"
                                        x-text="(items.reduce((s, i) => s + (Number(i.quantity) || 0) * (itemOptions.find((o) => o.id === i.id)?.cost || 0), 0) / cut).toFixed(2)"></dd>
                                </div>
                            </template>
                        </dl>
                        <div class="border-t border-slate-100 p-5">
                            <button type="submit" :disabled="items.length === 0 || ! (cut > 0)"
                                    class="flex w-full items-center justify-center gap-2 rounded-xl bg-brand-800 px-5 py-3 text-sm font-semibold text-white shadow-sm hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-accent-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-40">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                                {{ __('Create Production') }}
                            </button>
                            <p class="mt-2 text-center text-xs text-slate-400" x-show="items.length === 0 || ! (cut > 0)">{{ __('Add fabric and pieces cut to continue') }}</p>
                        </div>
                    </div>
                </aside>
            </form>
        </div>
        </div>
    @endif
</x-app-layout>
