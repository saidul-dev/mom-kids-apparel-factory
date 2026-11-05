<x-app-layout>
    <x-slot name="title">{{ $transfer->transfer_no }}</x-slot>
    <x-slot name="header">
        <div>
            <h2 class="text-2xl font-bold text-brand-900">{{ $transfer->transfer_no }}</h2>
            <p class="text-sm text-slate-500 mt-0.5">{{ __('Stock Transfer detail') }}</p>
        </div>
    </x-slot>

    <div class="rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 p-6 mb-4">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center gap-3 text-sm">
                <span class="font-semibold text-slate-800">{{ $transfer->fromSite->name }}</span>
                <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"/></svg>
                <span class="font-semibold text-slate-800">{{ $transfer->toSite->name }}</span>
            </div>

            <div class="flex items-center gap-3">
            <a href="{{ route('stock.transfers.print', $transfer) }}" target="_blank"
               class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-50">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0 1 10.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0 .229 2.523a1.125 1.125 0 0 1-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0 0 21 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 0 0-1.913-.247M6.34 18H5.25A2.25 2.25 0 0 1 3 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 0 1 1.913-.247m10.5 0a48.536 48.536 0 0 0-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5Zm-3 0h.008v.008H15V10.5Z"/></svg>
                {{ __('Print Bill') }}
            </a>
            @if ($transfer->status === 'in_transit')
                <span class="inline-flex rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-600 ring-1 ring-amber-200">{{ __('In Transit') }}</span>
            @elseif ($transfer->status === 'received')
                <span class="inline-flex rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-600 ring-1 ring-emerald-200">{{ __('Received') }}</span>
            @else
                <span class="inline-flex rounded-full bg-rose-50 px-3 py-1 text-xs font-semibold text-rose-600 ring-1 ring-rose-200">{{ __('Cancelled') }}</span>
            @endif
            </div>
        </div>

        <div class="mt-5 grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm">
            <div>
                <span class="block text-[11px] font-semibold uppercase tracking-wide text-slate-400">{{ __('Dispatched') }}</span>
                <span class="block text-slate-700">{{ $transfer->dispatched_at->format('d M, Y') }} {{ __('by') }} {{ $transfer->dispatchedBy->name ?? '—' }}</span>
            </div>
            <div>
                <span class="block text-[11px] font-semibold uppercase tracking-wide text-slate-400">{{ __('Received') }}</span>
                <span class="block text-slate-700">
                    @if ($transfer->received_at)
                        {{ $transfer->received_at->format('d M, Y') }} {{ __('by') }} {{ $transfer->receivedBy->name ?? '—' }}
                    @else
                        —
                    @endif
                </span>
            </div>
            @if ($transfer->note)
            <div>
                <span class="block text-[11px] font-semibold uppercase tracking-wide text-slate-400">{{ __('Note') }}</span>
                <span class="block text-slate-700">{{ $transfer->note }}</span>
            </div>
            @endif
        </div>

        @if ($transfer->status === 'in_transit')
        <div class="mt-5 flex flex-wrap gap-3 border-t border-slate-100 pt-5">
            @can('inventory.approve')
            <form method="POST" action="{{ route('stock.transfers.receive', $transfer) }}"
                  onsubmit="return confirm('{{ __('Confirm receipt of :no at :site?', ['no' => $transfer->transfer_no, 'site' => $transfer->toSite->name]) }}');">
                @csrf
                <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-brand-800 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                    {{ __('Confirm Receipt') }}
                </button>
            </form>
            @endcan
            @can('inventory.edit')
            <form method="POST" action="{{ route('stock.transfers.cancel', $transfer) }}"
                  onsubmit="return confirm('{{ __('Cancel :no? Stock will be reversed back at :site.', ['no' => $transfer->transfer_no, 'site' => $transfer->fromSite->name]) }}');">
                @csrf
                <button type="submit" class="inline-flex items-center gap-2 rounded-lg border border-rose-200 bg-rose-50 px-4 py-2 text-sm font-semibold text-rose-600 hover:bg-rose-100">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                    {{ __('Cancel Transfer') }}
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
                    <th class="px-5 py-3 font-semibold">{{ __('Unit') }}</th>
                    <th class="px-5 py-3 font-semibold text-right">{{ __('Quantity') }}</th>
                    <th class="px-5 py-3 font-semibold text-right">{{ __('Unit Cost') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('Batch / Expiry / Serial') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach ($transfer->items as $item)
                <tr class="hover:bg-slate-50">
                    <td class="px-5 py-3">
                        <span class="block font-semibold text-slate-800">
                            {{ $item->product->name }}{{ $item->productVariant ? ' — '.$item->productVariant->label : '' }}
                        </span>
                        <span class="block text-xs text-slate-400">{{ $item->productVariant->sku ?? $item->product->sku }}</span>
                    </td>
                    <td class="px-5 py-3 text-slate-600">{{ $item->product->stockUnit?->short_name ?? '—' }}</td>
                    <td class="px-5 py-3 text-right font-semibold text-slate-800">{{ rtrim(rtrim(number_format($item->quantity, 4), '0'), '.') ?: '0' }}</td>
                    <td class="px-5 py-3 text-right text-slate-600">{{ $item->unit_cost !== null ? number_format($item->unit_cost, 2) : '—' }}</td>
                    <td class="px-5 py-3 text-slate-600">
                        {{ collect([$item->batch_no, $item->expiry_date?->format('d M, Y'), $item->serial_no])->filter()->implode(' · ') ?: '—' }}
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        </div>
    </div>
</x-app-layout>
