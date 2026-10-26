@php
    $qty = fn ($v) => rtrim(rtrim(number_format((float) $v, 2), '0'), '.') ?: '0';
@endphp
<x-app-layout>
    <x-slot name="title">{{ __('Production') }}</x-slot>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-2xl font-bold text-brand-900">{{ __('Production') }}</h2>
                <p class="text-sm text-slate-500 mt-0.5">{{ __('Track every production from cutting to finished stock.') }}</p>
            </div>
            @can('inventory.create')
            <a href="{{ route('production-orders.create') }}"
               class="inline-flex items-center gap-2 rounded-lg bg-brand-800 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                {{ __('New Production') }}
            </a>
            @endcan
        </div>
    </x-slot>

    <!-- Summary -->
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3 mb-6">
        <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <span class="block text-[11px] font-semibold uppercase tracking-wide text-slate-400">{{ __('Running Productions') }}</span>
            <span class="mt-1 block text-3xl font-bold text-brand-900">{{ $summary['running'] }}</span>
        </div>
        <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <span class="flex items-center gap-2 text-[11px] font-semibold uppercase tracking-wide text-slate-400">
                <span class="h-2 w-2 rounded-full bg-amber-400"></span>{{ __('Waiting for Sewing') }}
            </span>
            <span class="mt-1 block text-3xl font-bold text-slate-800">{{ $qty($summary['waitingSewing']) }} <span class="text-sm font-medium text-slate-400">{{ __('pcs') }}</span></span>
        </div>
        <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <span class="flex items-center gap-2 text-[11px] font-semibold uppercase tracking-wide text-slate-400">
                <span class="h-2 w-2 rounded-full bg-sky-500"></span>{{ __('Waiting for Finishing') }}
            </span>
            <span class="mt-1 block text-3xl font-bold text-slate-800">{{ $qty($summary['waitingFinishing']) }} <span class="text-sm font-medium text-slate-400">{{ __('pcs') }}</span></span>
        </div>
    </div>

    <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200 mb-6">
        <form method="GET" action="{{ route('production-orders.index') }}" class="flex flex-nowrap items-end gap-4 overflow-x-auto">
            <div class="w-64 shrink-0">
                <x-input-label for="q" :value="__('Tracking No. / Item')" />
                <input id="q" name="q" type="search" value="{{ $search }}" placeholder="PRD-000001"
                       class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500">
            </div>
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
            <button type="submit" class="shrink-0 rounded-lg bg-slate-100 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-200">{{ __('Search') }}</button>
        </form>
    </div>

    <div class="rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
        <table class="w-full min-w-[980px] text-sm">
            <thead>
                <tr class="border-b border-slate-100 bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <th class="px-5 py-3 font-semibold">{{ __('Tracking No.') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('Item / Size') }}</th>
                    <th class="px-5 py-3 font-semibold text-right">{{ __('Cut') }}</th>
                    <th class="px-5 py-3 font-semibold text-right">{{ __('Sewn') }}</th>
                    <th class="px-5 py-3 font-semibold text-right">{{ __('Finished') }}</th>
                    <th class="px-5 py-3 font-semibold w-56">{{ __('Progress') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('Status') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($orders as $order)
                @php
                    $t = $totals[$order->id] ?? collect();
                    $cut = (float) ($t['cutting'] ?? 0);
                    $sewn = (float) ($t['sewing'] ?? 0);
                    $done = (float) ($t['finishing'] ?? 0);
                    $p = fn ($v) => $cut > 0 ? min(100, round($v / $cut * 100)) : 0;
                @endphp
                <tr class="cursor-pointer hover:bg-slate-50" onclick="window.location='{{ route('production-orders.show', $order) }}'">
                    <td class="px-5 py-3">
                        <a href="{{ route('production-orders.show', $order) }}" class="font-mono font-semibold text-brand-800 hover:text-brand-600">{{ $order->production_no }}</a>
                        <span class="block text-xs text-slate-400">{{ $order->start_date->format('d M, Y') }} · {{ $order->site->name }}</span>
                    </td>
                    <td class="px-5 py-3">
                        <span class="font-semibold text-slate-800">{{ $order->product->name }}</span>
                        @if ($order->productVariant)
                        <span class="ml-1 inline-flex rounded-md bg-brand-50 px-2 py-0.5 text-xs font-semibold text-brand-700 ring-1 ring-brand-100">{{ $order->productVariant->label }}</span>
                        @endif
                    </td>
                    <td class="px-5 py-3 text-right font-semibold text-slate-800">{{ $qty($cut) }}</td>
                    <td class="px-5 py-3 text-right font-semibold text-slate-800">{{ $qty($sewn) }}</td>
                    <td class="px-5 py-3 text-right font-semibold text-emerald-700">{{ $qty($done) }}</td>
                    <td class="px-5 py-3">
                        <div class="flex h-2 w-full overflow-hidden rounded-full bg-slate-100">
                            <div class="bg-emerald-500" style="width: {{ $p($done) }}%"></div>
                            <div class="bg-sky-500" style="width: {{ $p(max($sewn - $done, 0)) }}%"></div>
                            <div class="bg-amber-400" style="width: {{ $p(max($cut - $sewn, 0)) }}%"></div>
                        </div>
                        <span class="mt-1 block text-xs text-slate-400">{{ $p($done) }}% {{ __('finished') }}</span>
                    </td>
                    <td class="px-5 py-3">
                        @if ($order->status === 'in_progress')
                            <span class="inline-flex rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-semibold text-amber-600 ring-1 ring-amber-200">{{ __('In Progress') }}</span>
                        @else
                            <span class="inline-flex rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-600 ring-1 ring-emerald-200">{{ __('Completed') }}</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="px-5 py-10 text-center text-slate-400">{{ $search !== '' ? __('No production matches ":q".', ['q' => $search]) : __('No production yet.') }}</td>
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
