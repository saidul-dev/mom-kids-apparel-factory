@php
    $amountInWords = \App\Support\AmountInWords::convert((float) $item->net_amount, $company->currency_label);
@endphp
<x-app-layout>
    <x-slot name="title">{{ __('Payslip') }} — {{ $item->employee->name }}</x-slot>
    <x-slot name="header">
        <div>
            <h2 class="text-2xl font-bold text-brand-900">{{ __('Payslip') }}</h2>
            <p class="text-sm text-slate-500 mt-0.5">{{ $item->employee->name }} · {{ $item->payrollRun->monthLabel() }}</p>
        </div>
    </x-slot>

    <div class="no-print mb-4 flex justify-end gap-3">
        <button type="button" onclick="window.print()"
                class="inline-flex items-center gap-2 rounded-lg bg-brand-800 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">
            {{ __('Print') }}
        </button>
    </div>

    <div class="print-area voucher-print mx-auto max-w-3xl rounded-2xl bg-white p-8 ring-1 ring-slate-200">
        <div class="h-1.5 -mx-8 -mt-8 mb-6 rounded-t-2xl bg-brand-800"></div>

        <div class="flex items-start justify-between gap-6 border-b-2 border-brand-800 pb-6">
            <div class="flex items-center gap-3">
                @if ($company->logo_url)
                <img src="{{ $company->logo_url }}" alt="{{ $company->name }}" class="h-14 w-14 rounded-lg object-cover">
                @endif
                <div>
                    <h1 class="text-xl font-bold text-brand-800">{{ $company->name }}</h1>
                    @if ($company->address)<p class="text-xs text-slate-500">{{ $company->address }}</p>@endif
                </div>
            </div>
            <div class="text-right">
                <h2 class="text-lg font-bold uppercase tracking-wide text-brand-800">{{ __('Payslip') }}</h2>
                <p class="text-sm font-semibold text-slate-800">{{ $item->payrollRun->monthLabel() }}</p>
                @if ($item->ledgerTransaction)
                <p class="text-xs text-slate-500">{{ $item->ledgerTransaction->voucher_no }}</p>
                @endif
            </div>
        </div>

        <div class="mt-6 grid grid-cols-2 gap-6 text-sm">
            <div>
                <p class="mb-1 text-[11px] font-semibold uppercase tracking-wide text-slate-400">{{ __('Employee') }}</p>
                <p class="font-semibold text-slate-800">{{ $item->employee->name }}</p>
                <p class="text-slate-500">{{ $item->employee->designation?->name ?? '—' }}</p>
            </div>
            <div class="text-right">
                <p class="mb-1 text-[11px] font-semibold uppercase tracking-wide text-slate-400">{{ __('Paid From') }}</p>
                <p class="font-semibold text-slate-800">{{ $item->paidFromAccount?->name ?? '—' }}</p>
                <p class="text-slate-500">{{ $item->payrollRun->site->name }}</p>
            </div>
        </div>

        <div class="mt-6 text-sm">
            <table class="w-full">
                <tbody class="divide-y divide-slate-100">
                    @if ($item->mode === 'piece_rate')
                    <tr class="text-[11px] uppercase tracking-wide text-slate-400">
                        <td class="py-1.5">{{ __('Piece Work') }}</td>
                        <td class="py-1.5 text-right">{{ __('Pieces × Rate') }}</td>
                    </tr>
                    @foreach ($pieceLines as $line)
                    <tr>
                        <td class="py-1.5 text-slate-600">{{ $line->item }} — {{ __($line->stage) }}</td>
                        <td class="py-1.5 text-right text-slate-800">
                            <span class="text-xs text-slate-500">{{ rtrim(rtrim(number_format($line->pcs, 2), '0'), '.') }} × {{ number_format($line->rate, 2) }} =</span>
                            {{ number_format($line->amount, 2) }}
                        </td>
                    </tr>
                    @endforeach
                    @elseif ($item->mode === 'flat')
                    <tr><td class="py-1.5 text-slate-600">{{ __('Flat Salary') }}</td><td class="py-1.5 text-right text-slate-800">{{ number_format($item->flat_amount, 2) }}</td></tr>
                    @else
                    <tr><td class="py-1.5 text-slate-600">{{ __('Basic') }}</td><td class="py-1.5 text-right text-slate-800">{{ number_format($item->basic, 2) }}</td></tr>
                    <tr><td class="py-1.5 text-slate-600">{{ __('House Rent') }}</td><td class="py-1.5 text-right text-slate-800">{{ number_format($item->house_rent, 2) }}</td></tr>
                    <tr><td class="py-1.5 text-slate-600">{{ __('Medical') }}</td><td class="py-1.5 text-right text-slate-800">{{ number_format($item->medical, 2) }}</td></tr>
                    <tr><td class="py-1.5 text-slate-600">{{ __('Conveyance') }}</td><td class="py-1.5 text-right text-slate-800">{{ number_format($item->conveyance, 2) }}</td></tr>
                    @endif
                    <tr class="font-semibold"><td class="py-1.5 text-slate-700">{{ __('Gross') }}</td><td class="py-1.5 text-right text-slate-800">{{ number_format($item->gross_amount, 2) }}</td></tr>
                    <tr><td class="py-1.5 text-rose-600">{{ __('Absence Deduction') }} ({{ $item->absence_days }} {{ __('days') }})</td><td class="py-1.5 text-right text-rose-600">−{{ number_format($item->absence_deduction, 2) }}</td></tr>
                    <tr><td class="py-1.5 text-rose-600">{{ __('Advance Recovery') }}</td><td class="py-1.5 text-right text-rose-600">−{{ number_format($item->advance_recovery, 2) }}</td></tr>
                </tbody>
            </table>
        </div>

        <div class="mt-6 rounded-xl border-2 border-amber-200 bg-amber-50 px-6 py-5">
            <div class="flex items-baseline justify-between gap-4">
                <span class="text-[11px] font-semibold uppercase tracking-wide text-amber-800">{{ __('Net Pay') }}</span>
                <span class="font-mono text-3xl font-black tabular-nums text-amber-800">{{ number_format($item->net_amount, 2) }}</span>
            </div>
            <p class="mt-2 border-t border-amber-200 pt-2 text-xs italic text-slate-600">
                {{ __('In Words') }}: {{ $amountInWords }}
            </p>
        </div>

        <div class="mt-16 print-footer">
            <div class="grid grid-cols-2 gap-6 text-sm">
                <div class="border-t border-slate-400 pt-2 text-slate-600">{{ __('Prepared By') }}</div>
                <div class="border-t border-slate-400 pt-2 text-right text-slate-600">{{ __('Employee Signature') }}</div>
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
</x-app-layout>
