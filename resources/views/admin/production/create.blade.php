<x-app-layout>
    <x-slot name="title">{{ __('New Production') }}</x-slot>
    <x-slot name="header">
        <div>
            <h2 class="text-2xl font-bold text-brand-900">{{ __('New Production') }}</h2>
            <p class="text-sm text-slate-500 mt-0.5">{{ __('One production = one item in one size. Enter the fabric used and the pieces cut from it — a tracking number is generated on save.') }}</p>
        </div>
    </x-slot>

    <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200 mb-6">
        <form method="GET" action="{{ route('production-orders.create') }}" class="flex flex-nowrap items-end gap-4 overflow-x-auto">
            <div class="w-64 shrink-0">
                <x-input-label for="site_id" :value="__('Factory')" />
                <div class="mt-1">
                    <x-searchable-select name="site_id" :options="$sites" :selected="$site?->id"
                                          placeholder="{{ __('Select the factory…') }}" :auto-submit="true" />
                </div>
            </div>
        </form>
    </div>

    @if (! $site)
        <div class="rounded-2xl bg-white p-10 text-center text-slate-400 shadow-sm ring-1 ring-slate-200">
            {{ __('Pick the factory first — the fabric list shows what is in stock there.') }}
        </div>
    @else
        <div x-data="{ cut: @js(old('cut_quantity', '')) }">
        <div x-data="transferCart(@js(['itemOptions' => $fabricOptions]))">
            <form method="POST" action="{{ route('production-orders.store') }}" class="space-y-4">
                @csrf
                <input type="hidden" name="site_id" value="{{ $site->id }}">

                <!-- 1. Item & size -->
                <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                    <div class="mb-4 flex items-center gap-3">
                        <span class="flex h-7 w-7 items-center justify-center rounded-full bg-brand-800 text-xs font-bold text-white">1</span>
                        <h3 class="text-base font-semibold text-slate-800">{{ __('Item & Size') }}</h3>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div class="sm:col-span-2">
                            <x-input-label for="item" :value="__('Item — Size')" />
                            <div class="mt-1">
                                <x-searchable-select name="item" :options="$items" :selected="old('item')"
                                                      placeholder="{{ __('e.g. Kids T-Shirt — M') }}" />
                            </div>
                            <p class="mt-1 text-xs text-slate-400">{{ __('Each size needs its own production.') }}</p>
                            <x-input-error class="mt-2" :messages="$errors->get('item')" />
                        </div>
                        <div>
                            <x-input-label for="start_date" :value="__('Cutting Date')" />
                            <x-text-input id="start_date" name="start_date" type="date" class="mt-1 block w-full"
                                          :value="old('start_date', now()->toDateString())" required />
                            <x-input-error class="mt-2" :messages="$errors->get('start_date')" />
                        </div>
                    </div>
                </div>

                <!-- 2. Fabric used -->
                <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                    <div class="mb-4 flex items-center gap-3">
                        <span class="flex h-7 w-7 items-center justify-center rounded-full bg-brand-800 text-xs font-bold text-white">2</span>
                        <h3 class="text-base font-semibold text-slate-800">{{ __('Fabric Used') }}</h3>
                    </div>

                    <div class="relative max-w-md" @click.outside="open = false">
                        <input type="text" x-model="query" @focus="open = true"
                               placeholder="{{ __('Search fabric in stock at :site…', ['site' => $site->name]) }}"
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
                            <div x-show="filtered.length === 0" class="px-4 py-2 text-sm text-slate-400">{{ __('No matching fabric in stock.') }}</div>
                        </div>
                    </div>
                    @if ($fabricOptions->isEmpty())
                    <p class="mt-2 text-xs text-amber-600">{{ __('Nothing is in stock at :site — purchase fabric first.', ['site' => $site->name]) }}</p>
                    @endif
                    <x-input-error class="mt-2" :messages="$errors->get('fabrics')" />
                    @foreach ($errors->getMessages() as $key => $messages)
                        @if (str_starts_with($key, 'fabrics.'))
                        <x-input-error class="mt-2" :messages="$messages" />
                        @endif
                    @endforeach

                    <div class="mt-4 overflow-x-auto" x-show="items.length" x-cloak>
                        <table class="w-full min-w-[640px] text-sm">
                            <thead>
                                <tr class="border-b border-slate-100 bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                                    <th class="px-4 py-2.5 font-semibold">{{ __('Fabric') }}</th>
                                    <th class="px-4 py-2.5 font-semibold w-32">{{ __('In Stock') }}</th>
                                    <th class="px-4 py-2.5 font-semibold w-40">{{ __('Quantity Used') }}</th>
                                    <th class="px-4 py-2.5 w-10"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <template x-for="(item, i) in items" :key="item.id">
                                    <tr>
                                        <td class="px-4 py-2.5">
                                            <input type="hidden" :name="'fabrics[' + i + '][item]'" :value="item.id">
                                            <span class="font-semibold text-slate-800" x-text="item.name"></span>
                                        </td>
                                        <td class="px-4 py-2.5 text-slate-600" x-text="item.balance + ' ' + (item.unit || '')"></td>
                                        <td class="px-4 py-2.5">
                                            <div class="flex items-center gap-2">
                                                <input type="number" step="0.01" min="0.01" :max="item.balance"
                                                       :name="'fabrics[' + i + '][quantity]'" x-model.number="item.quantity"
                                                       class="w-28 rounded-lg border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500" placeholder="0" required>
                                                <span class="text-xs text-slate-400" x-text="item.unit || ''"></span>
                                            </div>
                                        </td>
                                        <td class="px-4 py-2.5 text-right">
                                            <button type="button" @click="removeItem(item.id)" class="text-slate-400 hover:text-rose-600" title="{{ __('Remove') }}">
                                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                                            </button>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                            <!-- Only summed when every fabric shares one unit — Roll and Gaj don't add up. -->
                            <tfoot class="border-t border-slate-200" x-show="items.length > 1 && new Set(items.map((i) => i.unit)).size === 1">
                                <tr class="text-sm">
                                    <td colspan="2" class="px-4 py-2.5 text-right font-semibold text-slate-500">{{ __('Total') }}</td>
                                    <td class="px-4 py-2.5 font-semibold text-slate-800" colspan="2"
                                        x-text="items.reduce((s, i) => s + (Number(i.quantity) || 0), 0).toLocaleString() + ' ' + (items[0]?.unit || '')"></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                <!-- 3. Cutting output -->
                <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                    <div class="mb-4 flex items-center gap-3">
                        <span class="flex h-7 w-7 items-center justify-center rounded-full bg-brand-800 text-xs font-bold text-white">3</span>
                        <h3 class="text-base font-semibold text-slate-800">{{ __('Cutting Output') }}</h3>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 items-start">
                        <div>
                            <x-input-label for="cut_quantity" :value="__('Pieces Cut')" />
                            <input id="cut_quantity" name="cut_quantity" type="number" step="1" min="1" x-model.number="cut" required
                                   class="mt-1 block w-full rounded-lg border-slate-300 text-lg font-semibold focus:border-accent-500 focus:ring-accent-500" placeholder="e.g. 500">
                            <x-input-error class="mt-2" :messages="$errors->get('cut_quantity')" />
                        </div>
                        <div class="sm:col-span-2 grid grid-cols-2 gap-4" x-show="cut > 0 && items.length === 1" x-cloak>
                            <div class="rounded-xl bg-slate-50 p-4 ring-1 ring-slate-100">
                                <span class="block text-[11px] font-semibold uppercase tracking-wide text-slate-400">{{ __('Fabric per piece') }}</span>
                                <span class="mt-1 block text-xl font-bold text-slate-800"
                                      x-text="((Number(items[0]?.quantity) || 0) / cut).toFixed(3) + ' ' + (items[0]?.unit || '')"></span>
                            </div>
                            <div class="rounded-xl bg-slate-50 p-4 ring-1 ring-slate-100">
                                <span class="block text-[11px] font-semibold uppercase tracking-wide text-slate-400" x-text="'{{ __('Pieces per') }} ' + (items[0]?.unit || '')"></span>
                                <span class="mt-1 block text-xl font-bold text-slate-800"
                                      x-text="(Number(items[0]?.quantity) > 0 ? cut / Number(items[0].quantity) : 0).toFixed(1)"></span>
                            </div>
                        </div>
                    </div>

                    <div class="mt-5 max-w-xl">
                        <x-input-label for="note" :value="__('Note (optional)')" />
                        <textarea id="note" name="note" rows="2"
                                  class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500">{{ old('note') }}</textarea>
                        <x-input-error class="mt-2" :messages="$errors->get('note')" />
                    </div>

                    <button type="submit" :disabled="items.length === 0"
                            class="mt-5 inline-flex items-center gap-2 rounded-lg bg-brand-800 px-5 py-2.5 text-sm font-semibold text-white hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-accent-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                        {{ __('Create Production') }}
                    </button>
                </div>
            </form>
        </div>
        </div>
    @endif
</x-app-layout>
