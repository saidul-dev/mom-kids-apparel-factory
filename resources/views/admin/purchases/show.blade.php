<x-app-layout>
    <x-slot name="title">{{ $purchase->purchase_no }}</x-slot>
    <x-slot name="header">
        <div>
            <h2 class="text-2xl font-bold text-brand-900">{{ $purchase->purchase_no }}</h2>
            <p class="text-sm text-slate-500 mt-0.5">{{ __('Purchase detail') }}</p>
        </div>
    </x-slot>

    <div class="rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 p-6 mb-4">
        <div class="grid grid-cols-1 sm:grid-cols-4 gap-4 text-sm">
            <div>
                <span class="block text-[11px] font-semibold uppercase tracking-wide text-slate-400">{{ __('Supplier') }}</span>
                <span class="block font-semibold text-slate-800">{{ $purchase->party->name }}</span>
            </div>
            <div>
                <span class="block text-[11px] font-semibold uppercase tracking-wide text-slate-400">{{ __('Received at') }}</span>
                <span class="block text-slate-700">{{ $purchase->site->name }}</span>
            </div>
            <div>
                <span class="block text-[11px] font-semibold uppercase tracking-wide text-slate-400">{{ __('Date') }}</span>
                <span class="block text-slate-700">{{ $purchase->purchase_date->format('d M, Y') }} {{ __('by') }} {{ $purchase->createdBy->name ?? '—' }}</span>
            </div>
            <div>
                <span class="block text-[11px] font-semibold uppercase tracking-wide text-slate-400">{{ __('Voucher') }}</span>
                <span class="block text-slate-700">{{ $purchase->ledgerTransaction->voucher_no ?? '—' }}</span>
            </div>
            @if ($purchase->supplier_invoice_no)
            <div>
                <span class="block text-[11px] font-semibold uppercase tracking-wide text-slate-400">{{ __('Supplier Invoice') }}</span>
                <span class="block text-slate-700">{{ $purchase->supplier_invoice_no }}</span>
            </div>
            @endif
            @if ($purchase->note)
            <div class="sm:col-span-3">
                <span class="block text-[11px] font-semibold uppercase tracking-wide text-slate-400">{{ __('Note') }}</span>
                <span class="block text-slate-700">{{ $purchase->note }}</span>
            </div>
            @endif
        </div>
    </div>

    <div class="rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
        <table class="w-full min-w-[700px] text-sm">
            <thead>
                <tr class="border-b border-slate-100 bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <th class="px-5 py-3 font-semibold">{{ __('Item') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('Unit') }}</th>
                    <th class="px-5 py-3 font-semibold text-right">{{ __('Quantity') }}</th>
                    <th class="px-5 py-3 font-semibold text-right">{{ __('Unit Cost') }}</th>
                    <th class="px-5 py-3 font-semibold text-right">{{ __('Line Total') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach ($purchase->items as $item)
                <tr class="hover:bg-slate-50">
                    <td class="px-5 py-3">
                        <span class="block font-semibold text-slate-800">
                            {{ $item->product->name }}{{ $item->productVariant ? ' — '.$item->productVariant->label : '' }}
                        </span>
                        <span class="block text-xs text-slate-400">{{ $item->productVariant->sku ?? $item->product->sku }}</span>
                    </td>
                    <td class="px-5 py-3 text-slate-600">{{ $item->product->stockUnit?->short_name ?? '—' }}</td>
                    <td class="px-5 py-3 text-right font-semibold text-slate-800">{{ rtrim(rtrim(number_format($item->quantity, 4), '0'), '.') ?: '0' }}</td>
                    <td class="px-5 py-3 text-right text-slate-600">{{ number_format($item->unit_cost, 2) }}</td>
                    <td class="px-5 py-3 text-right text-slate-800">{{ number_format($item->line_total, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
            <tfoot class="border-t border-slate-200 text-sm">
                <tr>
                    <td colspan="4" class="px-5 py-2 text-right text-slate-500">{{ __('Subtotal') }}</td>
                    <td class="px-5 py-2 text-right text-slate-800">{{ number_format($purchase->subtotal, 2) }}</td>
                </tr>
                @if ((float) $purchase->discount > 0)
                <tr>
                    <td colspan="4" class="px-5 py-2 text-right text-slate-500">{{ __('Discount') }}</td>
                    <td class="px-5 py-2 text-right text-slate-800">− {{ number_format($purchase->discount, 2) }}</td>
                </tr>
                @endif
                <tr>
                    <td colspan="4" class="px-5 py-3 text-right font-semibold text-slate-700">{{ __('Total') }}</td>
                    <td class="px-5 py-3 text-right text-base font-bold text-brand-900">{{ number_format($purchase->total, 2) }}</td>
                </tr>
            </tfoot>
        </table>
        </div>
    </div>
</x-app-layout>
