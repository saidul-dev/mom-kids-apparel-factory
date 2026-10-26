<x-app-layout>
    <x-slot name="title">{{ __('Purchases') }}</x-slot>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-2xl font-bold text-brand-900">{{ __('Purchases') }}</h2>
                <p class="text-sm text-slate-500 mt-0.5">{{ __('Fabric and raw material received from suppliers.') }}</p>
            </div>
            @can('inventory.create')
            <a href="{{ route('purchases.create') }}"
               class="inline-flex items-center gap-2 rounded-lg bg-brand-800 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                {{ __('New Purchase') }}
            </a>
            @endcan
        </div>
    </x-slot>

    <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200 mb-6">
        <form method="GET" action="{{ route('purchases.index') }}" class="flex flex-nowrap items-end gap-4 overflow-x-auto">
            <div class="w-56 shrink-0">
                <x-input-label for="site_id" :value="__('Site')" />
                <div class="mt-1">
                    <x-searchable-select name="site_id" :options="$sites" :selected="$siteId"
                                          placeholder="{{ __('All sites') }}" :auto-submit="true" />
                </div>
            </div>
            <div class="w-56 shrink-0">
                <x-input-label for="party_id" :value="__('Supplier')" />
                <div class="mt-1">
                    <x-searchable-select name="party_id" :options="$suppliers" :selected="$partyId"
                                          placeholder="{{ __('All suppliers') }}" :auto-submit="true" />
                </div>
            </div>
        </form>
    </div>

    <div class="rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
        <table class="w-full min-w-[860px] text-sm">
            <thead>
                <tr class="border-b border-slate-100 bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <th class="px-5 py-3 font-semibold">{{ __('Purchase') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('Date') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('Supplier') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('Site') }}</th>
                    <th class="px-5 py-3 font-semibold text-right">{{ __('Total') }}</th>
                    <th class="px-5 py-3 font-semibold"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($purchases as $purchase)
                <tr class="hover:bg-slate-50">
                    <td class="px-5 py-3">
                        <span class="block font-semibold text-slate-800">{{ $purchase->purchase_no }}</span>
                        @if ($purchase->supplier_invoice_no)
                        <span class="block text-xs text-slate-400">{{ __('Inv.') }} {{ $purchase->supplier_invoice_no }}</span>
                        @endif
                    </td>
                    <td class="px-5 py-3 text-slate-600">{{ $purchase->purchase_date->format('d M, Y') }}</td>
                    <td class="px-5 py-3 text-slate-600">{{ $purchase->party->name }}</td>
                    <td class="px-5 py-3 text-slate-600">{{ $purchase->site->name }}</td>
                    <td class="px-5 py-3 text-right font-semibold text-slate-800">{{ number_format($purchase->total, 2) }}</td>
                    <td class="px-5 py-3 text-right">
                        <a href="{{ route('purchases.show', $purchase) }}" class="font-semibold text-accent-600 hover:text-accent-800">{{ __('View') }}</a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="px-5 py-10 text-center text-slate-400">{{ __('No purchases yet.') }}</td>
                </tr>
                @endforelse
            </tbody>
        </table>
        </div>

        @if ($purchases->hasPages())
        <div class="border-t border-slate-100 px-5 py-4">
            {{ $purchases->links() }}
        </div>
        @endif
    </div>
</x-app-layout>
