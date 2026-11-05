@props([
    // 'customer' or 'supplier'.
    'type',
    // id of the searchable-select the new party is added to and selected in.
    'select',
])

@php
    $label = $type === 'supplier' ? __('Supplier') : __('Customer');
    $modal = "quick-add-{$select}";
@endphp

{{-- Sits inside the page's main form, so nothing here has a name or
     submits: the party is saved over AJAX and dropped into the picker. --}}
@can('parties.create')
<span x-data="{
        name: '', phone: '', address: '', errors: {}, saving: false,
        open() {
            this.name = ''; this.phone = ''; this.address = ''; this.errors = {};
            $dispatch('open-modal', @js($modal));
        },
        save() {
            this.saving = true;
            this.errors = {};
            axios.post(@js(route('parties.quick-store')), { type: @js($type), name: this.name, phone: this.phone, address: this.address })
                .then(({ data }) => {
                    const picker = Alpine.$data(document.getElementById(@js($select)));
                    const opt = { id: String(data.id), name: data.name };
                    picker.options.push(opt);
                    picker.select(opt);
                    $dispatch('close-modal', @js($modal));
                })
                .catch((err) => {
                    this.errors = err.response?.data?.errors ?? { name: [@js(__('Could not save. Try again.'))] };
                })
                .finally(() => { this.saving = false; });
        },
    }">
    <button type="button" @click="open()" class="text-xs font-semibold text-accent-700 hover:text-accent-800">+ {{ __('New :type', ['type' => $label]) }}</button>

    <x-modal :name="$modal" max-width="md">
        <div class="p-6" @keydown.enter.prevent="save()">
            <h2 class="text-lg font-bold text-brand-900">{{ __('New :type', ['type' => $label]) }}</h2>
            <p class="mt-0.5 text-xs text-slate-400">{{ __('Add the rest of the details later from Parties.') }}</p>

            <div class="mt-4 space-y-4">
                <div>
                    <x-input-label :value="__('Name')" />
                    <input type="text" x-model="name" class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500">
                    <template x-for="msg in errors.name || []"><p class="mt-1 text-sm text-rose-600" x-text="msg"></p></template>
                </div>
                <div>
                    <x-input-label :value="__('Phone')" />
                    <input type="tel" x-model="phone" class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500">
                    <template x-for="msg in errors.phone || []"><p class="mt-1 text-sm text-rose-600" x-text="msg"></p></template>
                </div>
                <div>
                    <x-input-label :value="__('Address (optional)')" />
                    <textarea x-model="address" rows="2" class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500"></textarea>
                </div>
            </div>

            <div class="mt-6 flex items-center gap-3">
                <button type="button" @click="save()" :disabled="saving"
                        class="rounded-lg bg-brand-800 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700 disabled:opacity-60">
                    <span x-show="!saving">{{ __('Save & Select') }}</span>
                    <span x-show="saving" x-cloak>{{ __('Saving…') }}</span>
                </button>
                <button type="button" @click="$dispatch('close-modal', @js($modal))"
                        class="rounded-lg px-4 py-2 text-sm font-semibold text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50">{{ __('Cancel') }}</button>
            </div>
        </div>
    </x-modal>
</span>
@endcan
