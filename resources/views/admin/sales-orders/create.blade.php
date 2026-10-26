<x-app-layout>
    <x-slot name="title">{{ __('New Sales Order') }}</x-slot>
    <x-slot name="header">
        <div>
            <h2 class="text-2xl font-bold text-brand-900">{{ __('New Sales Order') }}</h2>
            <p class="text-sm text-slate-500 mt-0.5">{{ __('Take a customer order. Stock leaves the site only when the order is delivered.') }}</p>
        </div>
    </x-slot>

    <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200 mb-6">
        <form method="GET" action="{{ route('sales-orders.create') }}" class="flex flex-nowrap items-end gap-4 overflow-x-auto">
            <div class="w-56 shrink-0">
                <x-input-label for="site_id" :value="__('Deliver from Site')" />
                <div class="mt-1">
                    <x-searchable-select name="site_id" :options="$sites" :selected="$site?->id"
                                          placeholder="{{ __('Select a site…') }}" :auto-submit="true" />
                </div>
            </div>
        </form>
    </div>

    @if (! $site)
        <div class="rounded-2xl bg-white p-10 text-center text-slate-400 shadow-sm ring-1 ring-slate-200">
            {{ __('Pick the Factory or Outlet this order ships from.') }}
        </div>
    @else
        <div x-data="saleCart(@js(['itemOptions' => $itemOptions, 'discount' => old('discount')]))">
            <form method="POST" action="{{ route('sales-orders.store') }}">
                @csrf
                <input type="hidden" name="site_id" value="{{ $site->id }}">

                <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200 mb-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 max-w-2xl">
                        <div>
                            <x-input-label for="party_id" :value="__('Customer')" />
                            <div class="mt-1">
                                <x-searchable-select name="party_id" :options="$customers" :selected="old('party_id')"
                                                      placeholder="{{ __('Select a customer…') }}" />
                            </div>
                            <x-input-error class="mt-2" :messages="$errors->get('party_id')" />
                        </div>
                        <div>
                            <x-input-label for="order_date" :value="__('Order Date')" />
                            <x-text-input id="order_date" name="order_date" type="date" class="mt-1 block w-full"
                                          :value="old('order_date', now()->toDateString())" required />
                            <x-input-error class="mt-2" :messages="$errors->get('order_date')" />
                        </div>
                    </div>
                </div>

                <!-- Item picker -->
                <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200 mb-4">
                    <x-input-label :value="__('Add Item')" />
                    <div class="relative mt-1 max-w-md" @click.outside="open = false">
                        <input type="text" x-model="query" @focus="open = true"
                               placeholder="{{ __('Search a product or variant…') }}"
                               class="block w-full rounded-lg border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500">
                        <div x-show="open" x-cloak x-transition
                             class="absolute z-20 mt-1 w-full max-h-64 overflow-y-auto rounded-xl bg-white py-1.5 shadow-lg ring-1 ring-slate-200">
                            <template x-for="opt in filtered" :key="opt.id">
                                <div @click="addItem(opt)"
                                     class="flex cursor-pointer items-center justify-between gap-3 px-4 py-2 text-sm hover:bg-slate-50">
                                    <span x-text="opt.name" class="text-slate-700"></span>
                                    <span class="shrink-0 text-xs" :class="opt.balance > 0 ? 'text-slate-400' : 'text-rose-400'"
                                          x-text="opt.balance + ' ' + (opt.unit || '') + ' {{ __('in stock') }}'"></span>
                                </div>
                            </template>
                            <div x-show="filtered.length === 0" class="px-4 py-2 text-sm text-slate-400">{{ __('No matching items.') }}</div>
                        </div>
                    </div>
                    <x-input-error class="mt-2" :messages="$errors->get('items')" />
                </div>

                <!-- Cart -->
                <div class="rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 overflow-hidden mb-4" x-show="items.length" x-cloak>
                    <div class="overflow-x-auto">
                    <table class="w-full min-w-[820px] text-sm">
                        <thead>
                            <tr class="border-b border-slate-100 bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                                <th class="px-5 py-3 font-semibold">{{ __('Item') }}</th>
                                <th class="px-5 py-3 font-semibold w-28">{{ __('In Stock') }}</th>
                                <th class="px-5 py-3 font-semibold w-36">{{ __('Quantity') }}</th>
                                <th class="px-5 py-3 font-semibold w-36">{{ __('Unit Price') }}</th>
                                <th class="px-5 py-3 font-semibold w-32 text-right">{{ __('Line Total') }}</th>
                                <th class="px-5 py-3 font-semibold w-16"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <template x-for="(item, i) in items" :key="item.id">
                                <tr class="hover:bg-slate-50">
                                    <td class="px-5 py-3">
                                        <input type="hidden" :name="'items[' + i + '][item]'" :value="item.id">
                                        <span class="font-semibold text-slate-800" x-text="item.name"></span>
                                    </td>
                                    <td class="px-5 py-3 text-slate-600"
                                        x-text="(itemOptions.find((o) => o.id === item.id)?.balance ?? 0) + ' ' + (item.unit || '')"></td>
                                    <td class="px-5 py-3">
                                        <input type="number" step="0.0001" min="0.0001"
                                               :name="'items[' + i + '][quantity]'" x-model.number="item.quantity"
                                               class="w-28 rounded-lg border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500" placeholder="0" required>
                                    </td>
                                    <td class="px-5 py-3">
                                        <input type="number" step="0.01" min="0"
                                               :name="'items[' + i + '][unit_price]'" x-model.number="item.unit_price"
                                               class="w-28 rounded-lg border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500" placeholder="0.00" required>
                                    </td>
                                    <td class="px-5 py-3 text-right font-semibold text-slate-800"
                                        x-text="((Number(item.quantity) || 0) * (Number(item.unit_price) || 0)).toFixed(2)"></td>
                                    <td class="px-5 py-3 text-right">
                                        <button type="button" @click="removeItem(item.id)" class="text-slate-400 hover:text-rose-600" title="{{ __('Remove') }}">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                                        </button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                    </div>
                </div>

                <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200" x-show="items.length" x-cloak>
                    <div class="mb-5 grid grid-cols-1 sm:grid-cols-3 gap-4 max-w-2xl text-sm">
                        <div>
                            <span class="block text-[11px] font-semibold uppercase tracking-wide text-slate-400">{{ __('Subtotal') }}</span>
                            <span class="mt-2 block text-lg font-semibold text-slate-800" x-text="subtotal.toFixed(2)"></span>
                        </div>
                        <div>
                            <x-input-label for="discount" :value="__('Discount')" />
                            <input id="discount" name="discount" type="number" step="0.01" min="0" x-model="discount"
                                   class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500" placeholder="0.00">
                            <x-input-error class="mt-2" :messages="$errors->get('discount')" />
                        </div>
                        <div>
                            <span class="block text-[11px] font-semibold uppercase tracking-wide text-slate-400">{{ __('Order Total') }}</span>
                            <span class="mt-2 block text-lg font-bold text-brand-900" x-text="total.toFixed(2)"></span>
                        </div>
                    </div>
                    <div class="mb-5 max-w-xl">
                        <x-input-label for="note" :value="__('Note / Delivery Address (optional)')" />
                        <textarea id="note" name="note" rows="2"
                                  class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500">{{ old('note') }}</textarea>
                        <x-input-error class="mt-2" :messages="$errors->get('note')" />
                    </div>

                    <button type="submit" :disabled="items.length === 0"
                            class="inline-flex items-center gap-2 rounded-lg bg-brand-800 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-accent-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                        {{ __('Save Order') }}
                    </button>
                </div>
            </form>
        </div>
    @endif
</x-app-layout>
