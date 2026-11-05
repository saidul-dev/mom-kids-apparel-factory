<x-app-layout>
    <x-slot name="title">{{ __('Payroll Run') }} — {{ $payrollRun->monthLabel() }}</x-slot>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold text-brand-900">{{ __('Payroll Run') }} — {{ $payrollRun->monthLabel() }}</h2>
                <p class="text-sm text-slate-500 mt-0.5">
                    {{ $payrollRun->site->name }} · {{ $payrollRun->items->count() }} {{ __('employees') }} ·
                    {{ __('Total') }}: {{ number_format($payrollRun->total_amount, 2) }}
                </p>
            </div>
            <div class="flex items-center gap-2">
                <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold ring-1
                    {{ $payrollRun->status === 'approved' ? 'bg-emerald-50 text-emerald-600 ring-emerald-200' : 'bg-amber-50 text-amber-600 ring-amber-200' }}">
                    {{ ucfirst($payrollRun->status) }}
                </span>
                @can('hrm.approve')
                @if ($payrollRun->status === 'draft')
                <form method="POST" action="{{ route('payroll-runs.approve', $payrollRun) }}"
                      onsubmit="return confirm('{{ __('Approve and post this payroll run to Accounts? This cannot be undone.') }}');">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="rounded-lg bg-brand-800 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Approve & Post') }}</button>
                </form>
                @endif
                @endcan
            </div>
        </div>
    </x-slot>

    <div class="rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
        <table class="w-full min-w-[1100px] text-sm">
            <thead>
                <tr class="border-b border-slate-100 bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <th class="px-4 py-3 font-semibold">{{ __('Employee') }}</th>
                    <th class="px-4 py-3 font-semibold text-right">{{ __('Gross') }}</th>
                    <th class="px-4 py-3 font-semibold text-center">{{ __('Absence Days') }}</th>
                    <th class="px-4 py-3 font-semibold text-right">{{ __('Absence Deduction') }}</th>
                    <th class="px-4 py-3 font-semibold text-right">{{ __('Advance Recovery') }}</th>
                    <th class="px-4 py-3 font-semibold text-right">{{ __('Net') }}</th>
                    <th class="px-4 py-3 font-semibold">{{ __('Pay From') }}</th>
                    <th class="px-4 py-3 font-semibold text-right">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($payrollRun->items as $item)
                <tr class="hover:bg-slate-50">
                    <td class="px-4 py-3 font-semibold text-slate-800">
                        {{ $item->employee->name }}
                        @if ($item->mode === 'piece_rate')
                        <span class="block text-xs font-normal text-slate-400">{{ __('Piece rate') }}</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-right text-slate-600">
                        {{ number_format($item->gross_amount, 2) }}
                        @if ($item->mode === 'piece_rate')
                        <span class="block text-xs text-slate-400">{{ rtrim(rtrim(number_format((float) $item->piece_quantity, 2), '0'), '.') }} {{ __('pcs') }}</span>
                        @endif
                    </td>
                    @if ($payrollRun->status === 'draft')
                    <td class="px-4 py-3 text-center">
                        <input form="item-form-{{ $item->id }}" type="number" step="0.5" min="0" name="absence_days" value="{{ $item->absence_days }}"
                               class="w-20 mx-auto rounded-lg border-slate-300 text-sm text-center focus:border-accent-500 focus:ring-accent-500">
                    </td>
                    <td class="px-4 py-3 text-right">
                        <input form="item-form-{{ $item->id }}" type="number" step="0.01" min="0" name="absence_deduction" value="{{ $item->absence_deduction }}"
                               class="w-28 ml-auto rounded-lg border-slate-300 text-sm text-right focus:border-accent-500 focus:ring-accent-500">
                    </td>
                    <td class="px-4 py-3 text-right">
                        <input form="item-form-{{ $item->id }}" type="number" step="0.01" min="0" name="advance_recovery" value="{{ $item->advance_recovery }}"
                               class="w-28 ml-auto rounded-lg border-slate-300 text-sm text-right focus:border-accent-500 focus:ring-accent-500">
                    </td>
                    <td class="px-4 py-3 text-right font-semibold text-slate-800">{{ number_format($item->net_amount, 2) }}</td>
                    <td class="px-4 py-3">
                        <select form="item-form-{{ $item->id }}" name="paid_from_account_id"
                                class="rounded-lg border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500">
                            <option value="">{{ __('Select…') }}</option>
                            @foreach ($cashAccounts as $account)
                                <option value="{{ $account->id }}" @selected($item->paid_from_account_id === $account->id)>{{ $account->name }}</option>
                            @endforeach
                        </select>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <button type="submit" form="item-form-{{ $item->id }}"
                                class="rounded-lg px-2.5 py-1 text-xs font-semibold text-brand-700 ring-1 ring-brand-200 hover:bg-brand-50">{{ __('Save') }}</button>
                    </td>
                    @else
                    <td class="px-4 py-3 text-center text-slate-600">{{ $item->absence_days }}</td>
                    <td class="px-4 py-3 text-right text-slate-600">{{ number_format($item->absence_deduction, 2) }}</td>
                    <td class="px-4 py-3 text-right text-slate-600">{{ number_format($item->advance_recovery, 2) }}</td>
                    <td class="px-4 py-3 text-right font-semibold text-slate-800">{{ number_format($item->net_amount, 2) }}</td>
                    <td class="px-4 py-3 text-slate-600">{{ $item->paidFromAccount?->name ?? '—' }}</td>
                    <td class="px-4 py-3 text-right">
                        <a href="{{ route('payroll-runs.items.payslip', $item) }}" target="_blank"
                           class="rounded-lg px-2.5 py-1 text-xs font-semibold text-brand-700 ring-1 ring-brand-200 hover:bg-brand-50">{{ __('Payslip') }}</a>
                    </td>
                    @endif
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="px-4 py-10 text-center text-slate-400">{{ __('No employees on this run.') }}</td>
                </tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </div>

    {{-- Each row's inputs above bind here via the form="" attribute rather
         than a <form> physically wrapping the <tr> — table rows can't
         validly contain/be wrapped by a <form>, and each of these posts to
         a different item besides. --}}
    @if ($payrollRun->status === 'draft')
    @foreach ($payrollRun->items as $item)
    <form id="item-form-{{ $item->id }}" method="POST" action="{{ route('payroll-runs.items.update', [$payrollRun, $item]) }}" class="hidden">
        @csrf
        @method('PATCH')
    </form>
    @endforeach
    @endif
</x-app-layout>
