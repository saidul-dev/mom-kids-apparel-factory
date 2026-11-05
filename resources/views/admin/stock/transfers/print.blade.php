@php
    $qty = fn ($v) => rtrim(rtrim(number_format((float) $v, 4), '0'), '.') ?: '0';
    $lineTotal = fn ($item) => (float) $item->quantity * (float) ($item->unit_cost ?? 0);
    $total = round($transfer->items->sum($lineTotal), 2);
    $status = match ($transfer->status) {
        'received' => __('Received'),
        'cancelled' => __('Cancelled'),
        default => __('In Transit'),
    };
@endphp
<x-app-layout>
    <x-slot name="title">{{ __('Print') }} — {{ $transfer->transfer_no }}</x-slot>
    <x-slot name="header">
        <div>
            <h2 class="text-2xl font-bold text-brand-900">{{ __('Transfer Bill') }}</h2>
            <p class="text-sm text-slate-500 mt-0.5">{{ $transfer->transfer_no }}</p>
        </div>
    </x-slot>

    <div class="no-print mb-4 flex justify-end gap-3">
        <a href="{{ route('stock.transfers.show', $transfer) }}"
           class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50">
            {{ __('Back') }}
        </a>
        <button type="button" onclick="window.print()"
                class="inline-flex items-center gap-2 rounded-lg bg-brand-800 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">
            {{ __('Print') }}
        </button>
    </div>

    <div class="print-area mx-auto max-w-4xl rounded-2xl bg-white p-8 ring-1 ring-slate-200">
        <div class="h-1.5 -mx-8 -mt-8 mb-6 rounded-t-2xl bg-brand-800"></div>

        <!-- Company + bill no. -->
        <div class="flex items-start justify-between gap-6 border-b-2 border-brand-800 pb-6">
            <div class="flex items-center gap-3">
                @if ($company->logo_url)
                <img src="{{ $company->logo_url }}" alt="{{ $company->name }}" class="h-14 w-14 rounded-lg object-cover">
                @endif
                <div>
                    <h1 class="text-xl font-bold text-brand-800">{{ $company->name }}</h1>
                    @if ($company->address)<p class="text-xs text-slate-500">{{ $company->address }}</p>@endif
                    @if ($company->phone || $company->email)
                    <p class="text-xs text-slate-500">{{ collect([$company->phone, $company->email])->filter()->implode(' · ') }}</p>
                    @endif
                </div>
            </div>
            <div class="text-right">
                <h2 class="text-lg font-bold uppercase tracking-wide text-brand-800">{{ __('Transfer Bill / Challan') }}</h2>
                <p class="text-sm font-semibold text-slate-800">{{ $transfer->transfer_no }}</p>
                <p class="text-xs text-slate-500">{{ $transfer->dispatched_at->format('d M, Y') }}</p>
                <p class="mt-1 text-xs font-semibold {{ $transfer->status === 'cancelled' ? 'text-rose-600' : 'text-slate-500' }}">{{ $status }}</p>
            </div>
        </div>

        <!-- From / To -->
        <div class="mt-6 grid grid-cols-2 gap-6 text-sm">
            @foreach ([[__('From'), $transfer->fromSite], [__('To'), $transfer->toSite]] as $i => [$label, $site])
            <div class="{{ $i === 1 ? 'text-right' : '' }}">
                <p class="mb-1 text-[11px] font-semibold uppercase tracking-wide text-slate-400">{{ $label }}</p>
                <p class="font-semibold text-slate-800">{{ $site->name }}</p>
                @if ($site->address)<p class="text-slate-500">{{ $site->address }}</p>@endif
                @if ($site->phone)<p class="text-slate-600">{{ $site->phone }}</p>@endif
            </div>
            @endforeach
        </div>

        <!-- Items -->
        <div class="mt-6 overflow-x-auto">
        <table class="w-full min-w-[600px] text-sm">
            <thead>
                <tr class="border-y border-slate-200 bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <th class="px-3 py-2 font-semibold">#</th>
                    <th class="px-3 py-2 font-semibold">{{ __('Item') }}</th>
                    <th class="px-3 py-2 font-semibold">{{ __('Size') }}</th>
                    <th class="px-3 py-2 font-semibold text-right">{{ __('Quantity') }}</th>
                    <th class="px-3 py-2 font-semibold text-right">{{ __('Unit Cost') }}</th>
                    <th class="px-3 py-2 font-semibold text-right">{{ __('Total') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach ($transfer->items as $item)
                <tr>
                    <td class="px-3 py-2 text-slate-500">{{ $loop->iteration }}</td>
                    <td class="px-3 py-2">
                        <span class="block font-semibold text-slate-800">{{ $item->product->name }}</span>
                        <span class="block text-xs text-slate-400">{{ $item->productVariant->sku ?? $item->product->sku }}</span>
                    </td>
                    <td class="px-3 py-2 text-slate-700">{{ $item->productVariant?->label ?? '—' }}</td>
                    <td class="px-3 py-2 text-right font-semibold text-slate-800">{{ $qty($item->quantity) }} {{ $item->product->stockUnit?->short_name }}</td>
                    <td class="px-3 py-2 text-right text-slate-600">{{ $item->unit_cost !== null ? number_format($item->unit_cost, 2) : '—' }}</td>
                    <td class="px-3 py-2 text-right font-semibold text-slate-800">{{ number_format($lineTotal($item), 2) }}</td>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="border-t-2 border-slate-300">
                    <td colspan="3" class="px-3 py-2.5 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Total') }}</td>
                    <td class="px-3 py-2.5 text-right font-bold text-slate-800">{{ $qty($transfer->items->sum('quantity')) }}</td>
                    <td></td>
                    <td class="px-3 py-2.5 text-right text-base font-black text-brand-800">{{ number_format($total, 2) }}</td>
                </tr>
            </tfoot>
        </table>
        </div>

        <p class="mt-3 text-xs italic text-slate-600">
            {{ __('In Words') }}: {{ \App\Support\AmountInWords::convert($total, $company->currency_label) }}
        </p>

        @if ($transfer->note)
        <div class="mt-6 text-sm">
            <p class="mb-1 text-[11px] font-semibold uppercase tracking-wide text-slate-400">{{ __('Note') }}</p>
            <p class="text-slate-700">{{ $transfer->note }}</p>
        </div>
        @endif

        <div class="print-footer mt-20">
            <div class="grid grid-cols-3 gap-6 text-sm">
                <div class="border-t border-slate-400 pt-2 text-slate-600">
                    {{ __('Dispatched By') }} ({{ $transfer->dispatchedBy->name ?? '—' }})
                </div>
                <div class="border-t border-slate-400 pt-2 text-center text-slate-600">
                    {{ __('Carried By') }}
                </div>
                <div class="border-t border-slate-400 pt-2 text-right text-slate-600">
                    {{ __('Received By') }}@if ($transfer->receivedBy) ({{ $transfer->receivedBy->name }})@endif
                </div>
            </div>
            <div class="h-1.5 -mx-8 -mb-8 mt-8 rounded-b-2xl bg-brand-800"></div>
        </div>
    </div>

    <style>
        @media print {
            body { background: #fff !important; }
            body * { visibility: hidden; }
            .print-area, .print-area * { visibility: visible; }
            .print-area {
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                max-width: 100%;
                min-height: calc(297mm - 24mm);
                display: flex;
                flex-direction: column;
                box-shadow: none;
                border: none;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .print-footer {
                margin-top: auto !important;
                page-break-inside: avoid;
                break-inside: avoid;
            }
            @page { size: A4; margin: 12mm; }
        }
    </style>

    <script>
        window.addEventListener('load', function () { window.print(); });
    </script>
</x-app-layout>
