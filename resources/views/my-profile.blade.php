@php
    $structure = $employee->salaryStructure;
    $payType = match ($structure?->mode) {
        'piece_rate' => __('Piece rate (production)'),
        'flat' => __('Monthly — flat amount'),
        'components' => __('Monthly — Basic + allowances'),
        default => __('Not set yet'),
    };
    $details = [
        __('Phone') => $employee->phone,
        __('Email') => $employee->email,
        __('NID') => $employee->nid_no ?: '—',
        __('Site') => $employee->site?->name ?? '—',
        __('Department') => $employee->department?->name ?? '—',
        __('Designation') => $employee->designation?->name ?? '—',
        __('Reports to') => $employee->reportingManager?->name ?? '—',
        __('Joined') => $employee->joining_date?->format('d M, Y') ?? '—',
        __('Employment') => __(ucfirst(str_replace('_', ' ', $employee->employment_type))),
        __('Status') => __(ucfirst(str_replace('_', ' ', $employee->employment_status))),
    ];
@endphp
<x-app-layout>
    <x-slot name="title">{{ __('My Profile') }}</x-slot>
    <x-slot name="header">
        <div>
            <h2 class="text-2xl font-bold text-brand-900">{{ __('My Profile') }}</h2>
            <p class="text-sm text-slate-500 mt-0.5">{{ __('Your employee record. Ask HR if anything here needs changing.') }}</p>
        </div>
    </x-slot>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <!-- Who -->
        <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200 lg:col-span-2">
            <div class="flex items-center gap-4">
                @if ($employee->photo_url)
                <img src="{{ $employee->photo_url }}" alt="{{ $employee->name }}" class="h-16 w-16 rounded-full object-cover ring-2 ring-slate-100">
                @else
                <span class="grid h-16 w-16 place-items-center rounded-full bg-brand-800 text-xl font-bold text-white">{{ mb_strtoupper(mb_substr($employee->name, 0, 1)) }}</span>
                @endif
                <div>
                    <p class="text-xl font-bold text-brand-900">{{ $employee->name }}</p>
                    <p class="text-sm text-slate-500">{{ collect([$employee->designation?->name, $employee->department?->name])->filter()->implode(' · ') ?: __('Employee') }}</p>
                </div>
            </div>

            <dl class="mt-6 grid grid-cols-1 gap-x-6 gap-y-4 text-sm sm:grid-cols-2">
                @foreach ($details as $label => $value)
                <div>
                    <dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">{{ $label }}</dt>
                    <dd class="mt-0.5 break-words text-slate-800">{{ $value }}</dd>
                </div>
                @endforeach
            </dl>
        </div>

        <!-- Pay -->
        <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
            <h3 class="text-sm font-semibold text-slate-800">{{ __('Pay') }}</h3>
            <p class="mt-2 text-sm text-slate-600">{{ $payType }}</p>
            @if ($structure && $structure->mode !== 'piece_rate')
            <p class="mt-1 text-2xl font-bold text-brand-900">{{ number_format($structure->grossAmount(), 2) }} <span class="text-sm font-medium text-slate-400">/ {{ __('month') }}</span></p>
            @endif

            @if ($pieceWork)
            <div class="mt-4 rounded-xl bg-slate-50 p-4 ring-1 ring-slate-200">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">{{ __('This month so far') }}</p>
                <div class="mt-2 flex items-baseline justify-between gap-3">
                    <span class="text-sm text-slate-600">{{ rtrim(rtrim(number_format((float) $pieceWork->pcs, 2), '0'), '.') ?: '0' }} {{ __('pcs') }}</span>
                    <span class="text-xl font-bold text-brand-900">{{ number_format((float) $pieceWork->amount, 2) }}</span>
                </div>
            </div>
            @endif
        </div>
    </div>

    <!-- Payslips -->
    <div class="mt-4 rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 overflow-hidden">
        <div class="border-b border-slate-100 px-5 py-3">
            <h3 class="text-sm font-semibold text-slate-800">{{ __('My Payslips') }}</h3>
        </div>
        <div class="overflow-x-auto">
        <table class="w-full min-w-[420px] text-sm">
            <thead>
                <tr class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <th class="px-5 py-2.5 font-semibold">{{ __('Month') }}</th>
                    <th class="px-5 py-2.5 font-semibold text-right">{{ __('Net Pay') }}</th>
                    <th class="px-5 py-2.5"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($payslips as $item)
                <tr>
                    <td class="px-5 py-2.5 font-semibold text-slate-800">{{ $item->payrollRun->monthLabel() }}</td>
                    <td class="px-5 py-2.5 text-right font-semibold text-slate-800">{{ number_format($item->net_amount, 2) }}</td>
                    <td class="px-5 py-2.5 text-right">
                        <a href="{{ route('payroll-runs.items.payslip', $item) }}" target="_blank"
                           class="rounded-lg px-2.5 py-1 text-xs font-semibold text-brand-700 ring-1 ring-brand-200 hover:bg-brand-50">{{ __('Payslip') }}</a>
                    </td>
                </tr>
                @empty
                <tr><td colspan="3" class="px-5 py-8 text-center text-slate-400">{{ __('No payslips yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </div>
</x-app-layout>
