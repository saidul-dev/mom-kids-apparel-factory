<x-app-layout>
    <x-slot name="title">{{ __('Products') }}</x-slot>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold text-brand-900">{{ __('Products') }}</h2>
                <p class="text-sm text-slate-500 mt-0.5">{{ __('Raw materials and finished goods') }}</p>
            </div>
            @can('inventory.create')
            <a href="{{ route('products.create', $type ? ['type' => $type] : []) }}" class="inline-flex items-center gap-2 rounded-lg bg-brand-800 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700 focus:ring-2 focus:ring-accent-500 focus:ring-offset-2">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                {{ __('Add Product') }}
            </a>
            @endcan
        </div>
    </x-slot>

    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
    <div class="inline-flex rounded-xl bg-white p-1 shadow-sm ring-1 ring-slate-200">
        @foreach (['' => __('All'), 'raw_material' => __('Raw Material'), 'finished_good' => __('Finished Goods')] as $key => $label)
        <a href="{{ route('products.index', array_filter(['type' => $key, 'q' => request('q')])) }}"
           class="rounded-lg px-4 py-1.5 text-sm font-semibold transition {{ ($type ?? '') === $key ? 'bg-brand-800 text-white' : 'text-slate-600 hover:bg-slate-50' }}">
            {{ $label }}
            <span class="ml-1 text-xs {{ ($type ?? '') === $key ? 'text-brand-200' : 'text-slate-400' }}">{{ $key === '' ? $typeCounts->sum() : ($typeCounts[$key] ?? 0) }}</span>
        </a>
        @endforeach
    </div>

    <form method="GET" action="{{ route('products.index') }}" class="w-full max-w-sm">
        @if ($type)<input type="hidden" name="type" value="{{ $type }}">@endif
        <div class="relative">
            <svg class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/></svg>
            <input type="search" name="q" value="{{ request('q') }}" placeholder="{{ __('Search by name, SKU or barcode…') }}"
                   class="w-full rounded-lg border-slate-200 bg-white pl-9 pr-4 py-2 text-sm focus:border-accent-500 focus:ring-accent-500 placeholder:text-slate-400">
        </div>
    </form>
    </div>

    <div class="rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
        <table class="w-full min-w-[900px] text-sm">
            <thead>
                <tr class="border-b border-slate-100 bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <th class="px-5 py-3 font-semibold">{{ __('Product') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('SKU') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('Category') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('Type') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('Unit') }}</th>
                    <th class="px-5 py-3 font-semibold text-right">{{ __('Cost') }}</th>
                    <th class="px-5 py-3 font-semibold text-right">{{ __('Price') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('Status') }}</th>
                    <th class="px-5 py-3 font-semibold text-right">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($products as $product)
                <tr class="hover:bg-slate-50">
                    <td class="px-5 py-3">
                        <div class="flex items-center gap-3">
                            <span class="grid h-9 w-9 shrink-0 place-items-center overflow-hidden rounded-lg bg-slate-100 ring-1 ring-slate-200">
                                @if ($product->image_url)
                                    <img src="{{ $product->image_url }}" alt="{{ $product->name }}" class="h-full w-full object-cover">
                                @else
                                    <svg class="h-4 w-4 text-slate-300" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m20.25 7.5-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z"/></svg>
                                @endif
                            </span>
                            <span class="font-semibold text-slate-800">{{ $product->name }}</span>
                        </div>
                    </td>
                    <td class="px-5 py-3 text-slate-600">{{ $product->sku }}</td>
                    <td class="px-5 py-3 text-slate-600">{{ $product->category->name ?? '—' }}</td>
                    <td class="px-5 py-3">
                        @if ($product->isRawMaterial())
                        <span class="inline-flex rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-semibold text-amber-700 ring-1 ring-amber-200">{{ __('Raw Material') }}</span>
                        @else
                        <span class="inline-flex rounded-full bg-sky-50 px-2.5 py-0.5 text-xs font-semibold text-sky-700 ring-1 ring-sky-200">{{ __('Finished Goods') }}</span>
                        @endif
                    </td>
                    <td class="px-5 py-3 text-slate-600">{{ $product->stockUnit->short_name ?? '—' }}</td>
                    <td class="px-5 py-3 text-right text-slate-600">{{ number_format($product->estimated_cost, 2) }}</td>
                    <td class="px-5 py-3 text-right font-semibold text-slate-800">{{ $product->isRawMaterial() ? '—' : number_format($product->selling_price, 2) }}</td>
                    <td class="px-5 py-3">
                        @can('inventory.edit')
                        <form method="POST" action="{{ route('products.toggle-status', $product) }}">
                            @csrf
                            @method('PATCH')
                            <button type="submit"
                                    class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1
                                    {{ $product->status ? 'bg-emerald-50 text-emerald-600 ring-emerald-200' : 'bg-slate-100 text-slate-500 ring-slate-200' }}">
                                {{ $product->status ? __('Active') : __('Inactive') }}
                            </button>
                        </form>
                        @else
                        <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1
                            {{ $product->status ? 'bg-emerald-50 text-emerald-600 ring-emerald-200' : 'bg-slate-100 text-slate-500 ring-slate-200' }}">
                            {{ $product->status ? 'Active' : 'Inactive' }}
                        </span>
                        @endcan
                    </td>
                    <td class="px-5 py-3">
                        <div class="flex items-center justify-end gap-1">
                            <a href="{{ route('products.history', $product) }}" title="{{ __('Stock History') }}"
                               class="rounded-lg p-2 text-slate-400 hover:bg-brand-50 hover:text-brand-700">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                            </a>
                            @can('inventory.edit')
                            <a href="{{ route('products.edit', $product) }}" title="{{ __('Edit') }}"
                               class="rounded-lg p-2 text-slate-400 hover:bg-brand-50 hover:text-brand-700">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10"/></svg>
                            </a>
                            @endcan
                            @can('inventory.delete')
                            <form method="POST" action="{{ route('products.destroy', $product) }}"
                                  onsubmit="return confirm('{{ __('Delete product :name? This cannot be undone.', ['name' => $product->name]) }}');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" title="{{ __('Delete') }}"
                                        class="rounded-lg p-2 text-slate-400 hover:bg-rose-50 hover:text-rose-600">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/></svg>
                                </button>
                            </form>
                            @endcan
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="9" class="px-5 py-10 text-center text-slate-400">
                        @if (request('q'))
                            {{ __('No products match ":query".', ['query' => request('q')]) }}
                        @else
                            {{ __('No products yet — add your first product to the catalogue.') }}
                        @endif
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
        </div>

        @if ($products->hasPages())
        <div class="border-t border-slate-100 px-5 py-4">
            {{ $products->links() }}
        </div>
        @endif
    </div>
</x-app-layout>
