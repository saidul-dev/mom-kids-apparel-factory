@php
    $qty = fn ($v) => rtrim(rtrim(number_format((float) $v, 2), '0'), '.') ?: '0';
@endphp
<x-app-layout>
    <x-slot name="title">{{ __('Piece Work') }}</x-slot>
    <x-slot name="header">
        <div>
            <h2 class="text-2xl font-bold text-brand-900">{{ __('Piece Work') }}</h2>
            <p class="text-sm text-slate-500 mt-0.5">{{ __('Pieces each piece-rate worker did and earned — recorded from the production page.') }}</p>
        </div>
    </x-slot>

    <form method="GET" class="mb-4 flex flex-wrap items-end gap-3 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
        <div>
            <x-input-label for="from" :value="__('From')" />
            <x-text-input id="from" name="from" type="date" class="mt-1" :value="$from" />
        </div>
        <div>
            <x-input-label for="to" :value="__('To')" />
            <x-text-input id="to" name="to" type="date" class="mt-1" :value="$to" />
        </div>
        <div class="min-w-[200px]">
            <x-input-label for="employee_id" :value="__('Worker')" />
            <select id="employee_id" name="employee_id" class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500">
                <option value="">{{ __('All workers') }}</option>
                @foreach ($employees as $employee)
                <option value="{{ $employee->id }}" @selected($employeeId === $employee->id)>{{ $employee->name }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="rounded-lg bg-brand-800 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Show') }}</button>
        <a href="{{ route('piece-works.index', ['from' => now()->startOfMonth()->toDateString(), 'to' => now()->toDateString()]) }}"
           class="rounded-lg px-3 py-2 text-sm font-semibold text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50">{{ __('This month') }}</a>
    </form>

    <!-- Per worker -->
    <div class="mb-4 rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 overflow-hidden">
        <div class="border-b border-slate-100 px-5 py-3">
            <h3 class="text-sm font-semibold text-slate-800">{{ __('By worker') }}</h3>
        </div>
        <div class="overflow-x-auto">
        <table class="w-full min-w-[480px] text-sm">
            <thead>
                <tr class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <th class="px-5 py-2.5 font-semibold">{{ __('Worker') }}</th>
                    <th class="px-5 py-2.5 font-semibold text-right">{{ __('Pieces') }}</th>
                    <th class="px-5 py-2.5 font-semibold text-right">{{ __('Earned') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($byEmployee as $row)
                <tr>
                    <td class="px-5 py-2.5 font-semibold text-slate-800">{{ $row['employee']->name }}</td>
                    <td class="px-5 py-2.5 text-right text-slate-700">{{ $qty($row['pcs']) }}</td>
                    <td class="px-5 py-2.5 text-right font-semibold text-slate-800">{{ number_format($row['amount'], 2) }}</td>
                </tr>
                @empty
                <tr><td colspan="3" class="px-5 py-8 text-center text-slate-400">{{ __('No piece work in this period.') }}</td></tr>
                @endforelse
            </tbody>
            @if ($byEmployee->isNotEmpty())
            <tfoot>
                <tr class="bg-slate-50">
                    <td class="px-5 py-2.5 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Total') }}</td>
                    <td class="px-5 py-2.5 text-right font-bold text-slate-800">{{ $qty($byEmployee->sum('pcs')) }}</td>
                    <td class="px-5 py-2.5 text-right font-bold text-slate-800">{{ number_format($byEmployee->sum('amount'), 2) }}</td>
                </tr>
            </tfoot>
            @endif
        </table>
        </div>
    </div>

    <!-- Every entry -->
    @if ($works->isNotEmpty())
    <div class="rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 overflow-hidden">
        <div class="border-b border-slate-100 px-5 py-3">
            <h3 class="text-sm font-semibold text-slate-800">{{ __('Entries') }}</h3>
        </div>
        <div class="overflow-x-auto">
        <table class="w-full min-w-[820px] text-sm">
            <thead>
                <tr class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <th class="px-5 py-2.5 font-semibold">{{ __('Date') }}</th>
                    <th class="px-5 py-2.5 font-semibold">{{ __('Worker') }}</th>
                    <th class="px-5 py-2.5 font-semibold">{{ __('Production') }}</th>
                    <th class="px-5 py-2.5 font-semibold">{{ __('Stage') }}</th>
                    <th class="px-5 py-2.5 font-semibold text-right">{{ __('Pieces') }}</th>
                    <th class="px-5 py-2.5 font-semibold text-right">{{ __('Rate') }}</th>
                    <th class="px-5 py-2.5 font-semibold text-right">{{ __('Amount') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach ($works as $work)
                <tr>
                    <td class="px-5 py-2.5 text-slate-600">{{ $work->entry_date->format('d M, Y') }}</td>
                    <td class="px-5 py-2.5 font-semibold text-slate-800">{{ $work->employee->name }}</td>
                    <td class="px-5 py-2.5">
                        <a href="{{ route('production-orders.show', $work->productionOrder) }}" class="font-mono text-xs font-semibold text-accent-700 hover:underline">{{ $work->productionOrder->production_no }}</a>
                        <span class="block text-xs text-slate-500">{{ $work->productionOrder->item_label }}</span>
                    </td>
                    <td class="px-5 py-2.5 text-slate-600">{{ __($work->stage_label) }}</td>
                    <td class="px-5 py-2.5 text-right text-slate-800">{{ $qty($work->quantity) }}</td>
                    <td class="px-5 py-2.5 text-right text-slate-600">{{ number_format($work->rate, 2) }}</td>
                    <td class="px-5 py-2.5 text-right font-semibold text-slate-800">{{ number_format($work->amount, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        </div>
    </div>
    @endif
</x-app-layout>
