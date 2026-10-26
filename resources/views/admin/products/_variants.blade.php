{{-- Variant builder. Shown only when "Has variants" is checked. --}}
<div class="mt-6 border-t border-slate-100 pt-6" x-show="hasVariants" x-cloak>
    <h4 class="font-semibold text-brand-900">{{ __('Variants') }}</h4>
    <p class="text-xs text-slate-400 mb-4">{{ __('Pick attributes and values, then generate the combinations. Each variant has its own price, SKU and barcode.') }}</p>

    @if ($attributes->isEmpty())
        <p class="text-sm text-amber-600">{{ __('No attributes yet —') }} <a href="{{ route('attributes.index') }}" class="underline font-semibold">{{ __('create Color/Size first') }}</a>.</p>
    @else
        {{-- Attribute + value pickers --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            @foreach ($attributes as $attribute)
                <div class="rounded-lg border border-slate-200 p-3" data-attribute-id="{{ $attribute->id }}">
                    <p class="text-sm font-semibold text-brand-900">{{ $attribute->name }}</p>
                    <div class="mt-2 flex flex-wrap gap-2">
                        @foreach ($attribute->values as $value)
                            <label class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-3 py-1 text-xs">
                                <input type="checkbox"
                                       @if (($product ?? null) && $product->has_variants && $product->variants->contains(fn ($v) => $v->attributeValues->contains('id', $value->id))) checked @endif
                                       @change="toggleValue({{ $attribute->id }}, {{ $value->id }}, '{{ addslashes($value->value) }}', $event.target.checked)"
                                       class="rounded border-slate-300 text-accent-600 focus:ring-accent-500">
                                {{ $value->value }}
                            </label>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>

        <button type="button" @click="generateVariants()"
                class="mt-4 rounded-lg bg-brand-800 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-900">{{ __('Generate Variants') }}</button>

        {{-- Generated variant rows --}}
        <div class="mt-4 overflow-x-auto" x-show="variants.length" x-cloak>
            <table class="min-w-full text-sm">
                <thead class="text-left text-xs font-semibold uppercase text-slate-500">
                    <tr>
                        <th class="py-2 pr-3">{{ __('Variant') }}</th>
                        <th class="py-2 pr-3">{{ __('SKU') }}</th>
                        <th class="py-2 pr-3">{{ __('Barcode') }}</th>
                        <th class="py-2 pr-3">{{ __('Selling Price') }}</th>
                        <th class="py-2 pr-3">{{ __('Cost') }}</th>
                        <th class="py-2 pr-3">{{ __('Active') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="(variant, i) in variants" :key="variant.key">
                        <tr class="border-t border-slate-100">
                            <td class="py-2 pr-3 font-medium text-brand-900" x-text="variant.label"></td>
                            <td class="py-2 pr-3">
                                <input type="hidden" :name="'variants[' + i + '][id]'" :value="variant.id || ''">
                                <template x-for="(valueId, attrId) in variant.values" :key="attrId">
                                    <input type="hidden" :name="'variants[' + i + '][values][' + attrId + ']'" :value="valueId">
                                </template>
                                <input type="text" :name="'variants[' + i + '][sku]'" x-model="variant.sku" placeholder="{{ __('auto') }}"
                                       class="block w-32 rounded-lg border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500">
                            </td>
                            <td class="py-2 pr-3">
                                <input type="text" :name="'variants[' + i + '][barcode]'" x-model="variant.barcode"
                                       class="block w-32 rounded-lg border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500">
                            </td>
                            <td class="py-2 pr-3">
                                <input type="number" step="0.01" min="0" :name="'variants[' + i + '][selling_price]'" x-model="variant.selling_price" required
                                       class="block w-24 rounded-lg border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500">
                            </td>
                            <td class="py-2 pr-3">
                                <input type="number" step="0.01" min="0" :name="'variants[' + i + '][estimated_cost]'" x-model="variant.estimated_cost"
                                       :readonly="variant.has_cost_history"
                                       :class="variant.has_cost_history ? 'bg-slate-50 text-slate-500 cursor-not-allowed' : ''"
                                       :title="variant.has_cost_history ? '{{ __('Auto-calculated from stock movement history — correct it via Stock Adjustment.') }}' : ''"
                                       class="block w-24 rounded-lg border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500">
                            </td>
                            <td class="py-2 pr-3">
                                <input type="hidden" :name="'variants[' + i + '][status]'" :value="variant.status ? '1' : '0'">
                                <input type="checkbox" x-model="variant.status"
                                       class="rounded border-slate-300 text-accent-600 focus:ring-accent-500">
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
            <x-input-error class="mt-2" :messages="$errors->get('variants')" />
        </div>
    @endif
</div>
