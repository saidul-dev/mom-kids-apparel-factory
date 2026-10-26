@php($qty = fn ($v) => rtrim(rtrim(number_format((float) $v, 4), '0'), '.') ?: '0')
<x-app-layout>
    <x-slot name="title">{{ $order->order_no }}</x-slot>
    <x-slot name="header">
        <div>
            <h2 class="text-2xl font-bold text-brand-900">{{ $order->order_no }}</h2>
            <p class="text-sm text-slate-500 mt-0.5">{{ __('Sales Order detail') }}</p>
        </div>
    </x-slot>

    <div class="rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 p-6 mb-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="grid grid-cols-1 sm:grid-cols-4 gap-x-8 gap-y-3 text-sm">
                <div>
                    <span class="block text-[11px] font-semibold uppercase tracking-wide text-slate-400">{{ __('Customer') }}</span>
                    <span class="block font-semibold text-slate-800">{{ $order->party->name }}</span>
                    <span class="block text-xs text-slate-400">{{ $order->party->phone }}</span>
                </div>
                <div>
                    <span class="block text-[11px] font-semibold uppercase tracking-wide text-slate-400">{{ __('From Site') }}</span>
                    <span class="block text-slate-700">{{ $order->site->name }}</span>
                </div>
                <div>
                    <span class="block text-[11px] font-semibold uppercase tracking-wide text-slate-400">{{ __('Ordered') }}</span>
                    <span class="block text-slate-700">{{ $order->order_date->format('d M, Y') }} {{ __('by') }} {{ $order->createdBy->name ?? '—' }}</span>
                </div>
                <div>
                    <span class="block text-[11px] font-semibold uppercase tracking-wide text-slate-400">{{ __('Delivered') }}</span>
                    <span class="block text-slate-700">
                        @if ($order->delivered_at)
                            {{ $order->delivered_at->format('d M, Y') }} {{ __('by') }} {{ $order->deliveredBy->name ?? '—' }}
                        @else
                            —
                        @endif
                    </span>
                </div>
                @if ($order->ledgerTransactions->isNotEmpty())
                <div>
                    <span class="block text-[11px] font-semibold uppercase tracking-wide text-slate-400">{{ __('Vouchers') }}</span>
                    <span class="block text-slate-700">{{ $order->ledgerTransactions->pluck('voucher_no')->implode(', ') }}</span>
                </div>
                @endif
                @if ($order->note)
                <div class="sm:col-span-3">
                    <span class="block text-[11px] font-semibold uppercase tracking-wide text-slate-400">{{ __('Note') }}</span>
                    <span class="block text-slate-700">{{ $order->note }}</span>
                </div>
                @endif
            </div>

            @include('admin.sales-orders.partials.status', ['status' => $order->status])
        </div>

        @if ($order->status === 'pending')
        <div class="mt-5 flex flex-wrap gap-3 border-t border-slate-100 pt-5">
            @can('sales.approve')
            <form method="POST" action="{{ route('sales-orders.deliver', $order) }}"
                  onsubmit="return confirm('{{ __('Deliver :no from :site? Stock will be deducted and the customer billed.', ['no' => $order->order_no, 'site' => $order->site->name]) }}');">
                @csrf
                <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-brand-800 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 0 0-3.213-9.193 2.056 2.056 0 0 0-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 0 0-10.026 0 1.106 1.106 0 0 0-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12"/></svg>
                    {{ __('Deliver Order') }}
                </button>
            </form>
            @endcan
            @can('sales.edit')
            <form method="POST" action="{{ route('sales-orders.cancel', $order) }}"
                  onsubmit="return confirm('{{ __('Cancel :no?', ['no' => $order->order_no]) }}');">
                @csrf
                <button type="submit" class="inline-flex items-center gap-2 rounded-lg border border-rose-200 bg-rose-50 px-4 py-2 text-sm font-semibold text-rose-600 hover:bg-rose-100">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                    {{ __('Cancel Order') }}
                </button>
            </form>
            @endcan
        </div>
        @endif
    </div>

    <div class="rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
        <table class="w-full min-w-[760px] text-sm">
            <thead>
                <tr class="border-b border-slate-100 bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <th class="px-5 py-3 font-semibold">{{ __('Item') }}</th>
                    @if ($order->status === 'pending')
                    <th class="px-5 py-3 font-semibold text-right">{{ __('In Stock') }}</th>
                    @endif
                    <th class="px-5 py-3 font-semibold text-right">{{ __('Quantity') }}</th>
                    <th class="px-5 py-3 font-semibold text-right">{{ __('Unit Price') }}</th>
                    <th class="px-5 py-3 font-semibold text-right">{{ __('Line Total') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach ($order->items as $item)
                <tr class="hover:bg-slate-50">
                    <td class="px-5 py-3">
                        <span class="block font-semibold text-slate-800">
                            {{ $item->product->name }}{{ $item->productVariant ? ' — '.$item->productVariant->label : '' }}
                        </span>
                        <span class="block text-xs text-slate-400">{{ $item->productVariant->sku ?? $item->product->sku }}</span>
                    </td>
                    @if ($order->status === 'pending')
                    @php($short = $balances[$item->id] < (float) $item->quantity)
                    <td class="px-5 py-3 text-right {{ $short ? 'font-semibold text-rose-600' : 'text-slate-600' }}">
                        {{ $qty($balances[$item->id]) }}
                        @if ($short)
                        <span class="block text-xs font-normal">{{ __('short') }}</span>
                        @endif
                    </td>
                    @endif
                    <td class="px-5 py-3 text-right font-semibold text-slate-800">{{ $qty($item->quantity) }} {{ $item->product->stockUnit?->short_name }}</td>
                    <td class="px-5 py-3 text-right text-slate-600">{{ number_format($item->unit_price, 2) }}</td>
                    <td class="px-5 py-3 text-right text-slate-800">{{ number_format($item->line_total, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
            @php($span = $order->status === 'pending' ? 4 : 3)
            <tfoot class="border-t border-slate-200 text-sm">
                <tr>
                    <td colspan="{{ $span }}" class="px-5 py-2 text-right text-slate-500">{{ __('Subtotal') }}</td>
                    <td class="px-5 py-2 text-right text-slate-800">{{ number_format($order->subtotal, 2) }}</td>
                </tr>
                @if ((float) $order->discount > 0)
                <tr>
                    <td colspan="{{ $span }}" class="px-5 py-2 text-right text-slate-500">{{ __('Discount') }}</td>
                    <td class="px-5 py-2 text-right text-slate-800">− {{ number_format($order->discount, 2) }}</td>
                </tr>
                @endif
                <tr>
                    <td colspan="{{ $span }}" class="px-5 py-3 text-right font-semibold text-slate-700">{{ __('Total') }}</td>
                    <td class="px-5 py-3 text-right text-base font-bold text-brand-900">{{ number_format($order->total, 2) }}</td>
                </tr>
            </tfoot>
        </table>
        </div>
    </div>
</x-app-layout>
