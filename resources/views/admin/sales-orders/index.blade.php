<x-app-layout>
    <x-slot name="title">{{ __('Sales Orders') }}</x-slot>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-2xl font-bold text-brand-900">{{ __('Sales Orders') }}</h2>
                <p class="text-sm text-slate-500 mt-0.5">{{ __('Customer orders from the factory and the wholesale outlet.') }}</p>
            </div>
            @can('sales.create')
            <a href="{{ route('sales-orders.create') }}"
               class="inline-flex items-center gap-2 rounded-lg bg-brand-800 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                {{ __('New Order') }}
            </a>
            @endcan
        </div>
    </x-slot>

    <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200 mb-6">
        <form method="GET" action="{{ route('sales-orders.index') }}" class="flex flex-nowrap items-end gap-4 overflow-x-auto">
            <div class="w-56 shrink-0">
                <x-input-label for="site_id" :value="__('Site')" />
                <div class="mt-1">
                    <x-searchable-select name="site_id" :options="$sites" :selected="$siteId"
                                          placeholder="{{ __('All sites') }}" :auto-submit="true" />
                </div>
            </div>
            <div class="w-56 shrink-0">
                <x-input-label for="party_id" :value="__('Customer')" />
                <div class="mt-1">
                    <x-searchable-select name="party_id" :options="$customers" :selected="$partyId"
                                          placeholder="{{ __('All customers') }}" :auto-submit="true" />
                </div>
            </div>
            <div class="w-48 shrink-0">
                <x-input-label for="status" :value="__('Status')" />
                <select name="status" onchange="this.form.submit()"
                        class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500">
                    <option value="">{{ __('All statuses') }}</option>
                    @foreach (['pending' => __('Pending'), 'delivered' => __('Delivered'), 'cancelled' => __('Cancelled')] as $key => $label)
                        <option value="{{ $key }}" @selected($status === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </form>
    </div>

    <div class="rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
        <table class="w-full min-w-[860px] text-sm">
            <thead>
                <tr class="border-b border-slate-100 bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <th class="px-5 py-3 font-semibold">{{ __('Order') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('Date') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('Customer') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('From Site') }}</th>
                    <th class="px-5 py-3 font-semibold text-right">{{ __('Total') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('Status') }}</th>
                    <th class="px-5 py-3 font-semibold"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($orders as $order)
                <tr class="hover:bg-slate-50">
                    <td class="px-5 py-3 font-semibold text-slate-800">{{ $order->order_no }}</td>
                    <td class="px-5 py-3 text-slate-600">{{ $order->order_date->format('d M, Y') }}</td>
                    <td class="px-5 py-3 text-slate-600">{{ $order->party->name }}</td>
                    <td class="px-5 py-3 text-slate-600">{{ $order->site->name }}</td>
                    <td class="px-5 py-3 text-right font-semibold text-slate-800">{{ number_format($order->total, 2) }}</td>
                    <td class="px-5 py-3">@include('admin.sales-orders.partials.status', ['status' => $order->status])</td>
                    <td class="px-5 py-3 text-right">
                        <a href="{{ route('sales-orders.show', $order) }}" class="font-semibold text-accent-600 hover:text-accent-800">{{ __('View') }}</a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="px-5 py-10 text-center text-slate-400">{{ __('No sales orders yet.') }}</td>
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
