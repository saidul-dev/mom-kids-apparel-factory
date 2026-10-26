<x-app-layout>
    <x-slot name="title">{{ __('New Production Lot') }}</x-slot>
    <x-slot name="header">
        <div>
            <h2 class="text-2xl font-bold text-brand-900">{{ __('New Production Lot') }}</h2>
            <p class="text-sm text-slate-500 mt-0.5">{{ __('Open a lot for one finished item. Cutting, sewing and finishing are recorded on the lot afterwards.') }}</p>
        </div>
    </x-slot>

    <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200 max-w-3xl">
        <form method="POST" action="{{ route('production-orders.store') }}" class="space-y-5">
            @csrf

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <x-input-label for="site_id" :value="__('Factory / Site')" />
                    <div class="mt-1">
                        <x-searchable-select name="site_id" :options="$sites" :selected="old('site_id')"
                                              placeholder="{{ __('Select a site…') }}" />
                    </div>
                    <x-input-error class="mt-2" :messages="$errors->get('site_id')" />
                </div>
                <div>
                    <x-input-label for="start_date" :value="__('Start Date')" />
                    <x-text-input id="start_date" name="start_date" type="date" class="mt-1 block w-full"
                                  :value="old('start_date', now()->toDateString())" required />
                    <x-input-error class="mt-2" :messages="$errors->get('start_date')" />
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <x-input-label for="item" :value="__('Finished Item')" />
                    <div class="mt-1">
                        <x-searchable-select name="item" :options="$items" :selected="old('item')"
                                              placeholder="{{ __('What is being made…') }}" />
                    </div>
                    <x-input-error class="mt-2" :messages="$errors->get('item')" />
                </div>
                <div>
                    <x-input-label for="planned_quantity" :value="__('Planned Quantity (optional)')" />
                    <x-text-input id="planned_quantity" name="planned_quantity" type="number" step="1" min="1"
                                  class="mt-1 block w-full" :value="old('planned_quantity')" placeholder="e.g. 200" />
                    <x-input-error class="mt-2" :messages="$errors->get('planned_quantity')" />
                </div>
            </div>

            <div>
                <x-input-label for="note" :value="__('Note (optional)')" />
                <textarea id="note" name="note" rows="2"
                          class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500">{{ old('note') }}</textarea>
                <x-input-error class="mt-2" :messages="$errors->get('note')" />
            </div>

            <button type="submit"
                    class="inline-flex items-center gap-2 rounded-lg bg-brand-800 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-accent-500 focus:ring-offset-2">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                {{ __('Start Lot') }}
            </button>
        </form>
    </div>
</x-app-layout>
