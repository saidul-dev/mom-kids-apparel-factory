<x-app-layout>
    <x-slot name="title">{{ __('Production') }}</x-slot>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-2xl font-bold text-brand-900">{{ __('Production') }}</h2>
                <p class="text-sm text-slate-500 mt-0.5">{{ __('Production lots — Cutting → Sewing → Finishing → finished stock.') }}</p>
            </div>
            @can('inventory.create')
            <a href="{{ route('production-orders.create') }}"
               class="inline-flex items-center gap-2 rounded-lg bg-brand-800 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                {{ __('New Production Lot') }}
            </a>
            @endcan
        </div>
    </x-slot>

    <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200 mb-6">
        <form method="GET" action="{{ route('production-orders.index') }}" class="flex flex-nowrap items-end gap-4 overflow-x-auto">
            <div class="w-56 shrink-0">
                <x-input-label for="site_id" :value="__('Site')" />
                <div class="mt-1">
                    <x-searchable-select name="site_id" :options="$sites" :selected="$siteId"
                                          placeholder="{{ __('All sites') }}" :auto-submit="true" />
                </div>
            </div>
            <div class="w-48 shrink-0">
                <x-input-label for="status" :value="__('Status')" />
                <select name="status" onchange="this.form.submit()"
                        class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500">
                    <option value="">{{ __('All statuses') }}</option>
                    @foreach (['in_progress' => __('In Progress'), 'completed' => __('Completed')] as $key => $label)
                        <option value="{{ $key }}" @selected($status === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </form>
    </div>

    <div class="rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
        <table class="w-full min-w-[960px] text-sm">
            <thead>
                <tr class="border-b border-slate-100 bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <th class="px-5 py-3 font-semibold">{{ __('Lot') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('Item') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('Site') }}</th>
                    <th class="px-5 py-3 font-semibold text-right">{{ __('Cut') }}</th>
                    <th class="px-5 py-3 font-semibold text-right">{{ __('Sewn') }}</th>
                    <th class="px-5 py-3 font-semibold text-right">{{ __('Finished') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('Status') }}</th>
                    <th class="px-5 py-3 font-semibold"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($orders as $order)
                @php($t = $totals[$order->id] ?? collect())
                <tr class="hover:bg-slate-50">
                    <td class="px-5 py-3">
                        <span class="block font-semibold text-slate-800">{{ $order->production_no }}</span>
                        <span class="block text-xs text-slate-400">{{ $order->start_date->format('d M, Y') }}</span>
                    </td>
                    <td class="px-5 py-3 text-slate-700">
                        {{ $order->item_label }}
                        @if ($order->planned_quantity)
                        <span class="block text-xs text-slate-400">{{ __('Planned') }}: {{ rtrim(rtrim(number_format($order->planned_quantity, 4), '0'), '.') }} {{ $order->product->stockUnit?->short_name }}</span>
                        @endif
                    </td>
                    <td class="px-5 py-3 text-slate-600">{{ $order->site->name }}</td>
                    @foreach (['cutting', 'sewing', 'finishing'] as $stage)
                    <td class="px-5 py-3 text-right font-semibold text-slate-800">{{ rtrim(rtrim(number_format($t[$stage] ?? 0, 4), '0'), '.') ?: '0' }}</td>
                    @endforeach
                    <td class="px-5 py-3">
                        @if ($order->status === 'in_progress')
                            <span class="inline-flex rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-semibold text-amber-600 ring-1 ring-amber-200">{{ __('In Progress') }}</span>
                        @else
                            <span class="inline-flex rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-600 ring-1 ring-emerald-200">{{ __('Completed') }}</span>
                        @endif
                    </td>
                    <td class="px-5 py-3 text-right">
                        <a href="{{ route('production-orders.show', $order) }}" class="font-semibold text-accent-600 hover:text-accent-800">{{ __('View') }}</a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="px-5 py-10 text-center text-slate-400">{{ __('No production lots yet.') }}</td>
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
</x-app-layout>
