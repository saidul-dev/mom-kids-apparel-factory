<x-app-layout>
    <x-slot name="title">{{ __('Piece Rates') }}</x-slot>
    <x-slot name="header">
        <div>
            <h2 class="text-2xl font-bold text-brand-900">{{ __('Piece Rates') }}</h2>
            <p class="text-sm text-slate-500 mt-0.5">{{ __('What a piece-rate worker earns per piece, by item and stage. Leave a cell blank if that stage isn\'t paid by the piece.') }}</p>
        </div>
    </x-slot>

    <form method="POST" action="{{ route('piece-rates.update') }}">
        @csrf
        @method('PUT')
        <div class="rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 overflow-hidden">
            <div class="overflow-x-auto">
            <table class="w-full min-w-[640px] text-sm">
                <thead>
                    <tr class="border-b border-slate-100 bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                        <th class="px-5 py-3 font-semibold">{{ __('Item') }}</th>
                        @foreach (\App\Models\PieceRate::STAGES as $stage)
                        <th class="px-5 py-3 font-semibold text-right">{{ __(\App\Models\ProductionOrder::STAGES[$stage]) }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($products as $product)
                    <tr class="hover:bg-slate-50">
                        <td class="px-5 py-2.5">
                            <span class="block font-semibold text-slate-800">{{ $product->name }}</span>
                            <span class="block text-xs text-slate-400">{{ $product->sku }}</span>
                        </td>
                        @foreach (\App\Models\PieceRate::STAGES as $stage)
                        <td class="px-5 py-2.5 text-right">
                            <input type="number" step="0.01" min="0" name="rates[{{ $product->id }}][{{ $stage }}]"
                                   value="{{ old("rates.{$product->id}.{$stage}", $rates[$product->id][$stage] ?? '') }}"
                                   @cannot('hrm.edit') disabled @endcannot
                                   class="w-28 rounded-lg border-slate-300 text-right text-sm focus:border-accent-500 focus:ring-accent-500" placeholder="—">
                        </td>
                        @endforeach
                    </tr>
                    @empty
                    <tr><td colspan="4" class="px-5 py-10 text-center text-slate-400">{{ __('No finished items yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
            </div>
        </div>
        <x-input-error class="mt-2" :messages="$errors->get('rates.*')" />

        @can('hrm.edit')
        @if ($products->isNotEmpty())
        <div class="mt-4 flex justify-end">
            <x-primary-button>{{ __('Save Rates') }}</x-primary-button>
        </div>
        @endif
        @endcan
    </form>
</x-app-layout>
