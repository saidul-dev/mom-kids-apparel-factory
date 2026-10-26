@php
    $editing = isset($product);
    $hasCostHistory = $hasCostHistory ?? false;
@endphp

<div class="grid grid-cols-1 lg:grid-cols-3 gap-4"
     x-data="productForm({
        type: @js(old('type', $product->type ?? request('type', 'finished_good'))),
        preview: @js($product->image_url ?? null),
        removeImage: false,
        hasVariants: {{ old('has_variants', $product->has_variants ?? false) ? 'true' : 'false' }},
     })">

    <!-- Type -->
    <div class="lg:col-span-3 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <h3 class="font-bold text-brand-900">{{ __('Product Type') }}</h3>
        <p class="mb-4 text-xs text-slate-400">{{ __('Raw material is used in production; finished goods are what you make and sell.') }}</p>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            @foreach ([
                'raw_material' => [__('Raw Material'), __('Fabric, thread, buttons, poly bags…'), 'M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z'],
                'finished_good' => [__('Finished Goods'), __('Garments ready to sell — frock, t-shirt, romper…'), 'M15.75 10.5V6a3.75 3.75 0 1 0-7.5 0v4.5m11.356-1.993 1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 0 1-1.12-1.243l1.264-12A1.125 1.125 0 0 1 5.513 7.5h12.974c.576 0 1.059.435 1.119 1.007ZM8.625 10.5a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm7.5 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z'],
            ] as $value => [$label, $hint, $icon])
            <label class="flex cursor-pointer items-center gap-4 rounded-xl p-4 transition"
                   :class="type === '{{ $value }}' ? 'ring-2 ring-brand-600 bg-brand-50' : 'ring-1 ring-slate-200 hover:bg-slate-50'">
                <input type="radio" name="type" value="{{ $value }}" x-model="type" class="sr-only"
                       @change="if (type === 'raw_material') hasVariants = false">
                <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl"
                      :class="type === '{{ $value }}' ? 'bg-brand-800 text-white' : 'bg-slate-100 text-slate-500'">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}"/></svg>
                </span>
                <span>
                    <span class="block text-sm font-semibold text-slate-800">{{ $label }}</span>
                    <span class="block text-xs text-slate-500">{{ $hint }}</span>
                </span>
            </label>
            @endforeach
        </div>
        <x-input-error class="mt-2" :messages="$errors->get('type')" />
    </div>

    <!-- Image -->
    <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <div class="mb-4">
            <h3 class="font-bold text-brand-900">{{ __('Image') }}</h3>
            <p class="text-xs text-slate-400">{{ __('Optional · PNG or JPG, up to 2MB') }}</p>
        </div>

        <div class="flex flex-col items-center gap-4">
            <div class="grid h-28 w-28 place-items-center overflow-hidden rounded-2xl bg-slate-100 ring-1 ring-slate-200">
                <template x-if="preview">
                    <img :src="preview" alt="Product preview" class="h-full w-full object-cover">
                </template>
                <template x-if="!preview">
                    <svg class="h-10 w-10 text-slate-300" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z"/></svg>
                </template>
            </div>

            <label class="cursor-pointer rounded-lg bg-brand-800/5 px-4 py-2 text-xs font-semibold text-brand-800 hover:bg-brand-800/10">
                {{ __('Choose Image') }}
                <input type="file" name="image" accept="image/*" class="hidden"
                       @change="removeImage = false; const f = $event.target.files[0]; if (f) preview = URL.createObjectURL(f)">
            </label>

            @if ($editing && $product->image_url)
            <label class="flex items-center gap-2 text-xs text-slate-500">
                <input type="checkbox" name="remove_image" value="1" x-model="removeImage"
                       @change="if (removeImage) preview = null; else preview = @js($product->image_url)"
                       class="rounded border-slate-300 text-rose-600 focus:ring-rose-500">
                {{ __('Remove current image') }}
            </label>
            @endif
        </div>
        <x-input-error class="mt-3" :messages="$errors->get('image')" />
    </div>

    <!-- Details -->
    <div class="lg:col-span-2 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200 space-y-4">
        <h3 class="font-bold text-brand-900">{{ __('Details') }}</h3>

        <div>
            <x-input-label for="name" :value="__('Name')" />
            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full"
                          :value="old('name', $product->name ?? '')" required autofocus
                          x-bind:placeholder="type === 'raw_material' ? '{{ __('e.g. Silk Fabric') }}' : '{{ __('e.g. Girls Frock') }}'" />
            <x-input-error class="mt-2" :messages="$errors->get('name')" />
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <x-input-label for="sku" :value="__('Code / SKU')" />
                <x-text-input id="sku" name="sku" type="text" class="mt-1 block w-full"
                              :value="old('sku', $product->sku ?? '')" required
                              x-bind:placeholder="type === 'raw_material' ? 'RM-SILK-01' : 'FRK-01'" />
                <x-input-error class="mt-2" :messages="$errors->get('sku')" />
            </div>
            <div x-show="type === 'finished_good'" x-cloak>
                <x-input-label for="barcode" :value="__('Barcode (optional)')" />
                <x-text-input id="barcode" name="barcode" type="text" class="mt-1 block w-full"
                              :value="old('barcode', $product->barcode ?? '')" />
                <x-input-error class="mt-2" :messages="$errors->get('barcode')" />
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <x-input-label for="category_id" :value="__('Category')" />
                <select id="category_id" name="category_id" required
                        class="mt-1 block w-full rounded-lg border-slate-300 focus:border-accent-500 focus:ring-accent-500">
                    <option value="">{{ __('Select category') }}</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected(old('category_id', $product->category_id ?? '') == $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
                <x-input-error class="mt-2" :messages="$errors->get('category_id')" />
                @if ($categories->isEmpty())
                    <p class="mt-1 text-xs text-amber-600">{{ __('No categories yet —') }} <a href="{{ route('categories.create') }}" class="underline">{{ __('add one first') }}</a>.</p>
                @endif
            </div>
            <div>
                <x-input-label for="stock_unit_id" :value="__('Unit')" />
                <select id="stock_unit_id" name="stock_unit_id" required
                        class="mt-1 block w-full rounded-lg border-slate-300 focus:border-accent-500 focus:ring-accent-500">
                    <option value="">{{ __('Select unit') }}</option>
                    @foreach ($units as $unit)
                        <option value="{{ $unit->id }}" @selected(old('stock_unit_id', $product->stock_unit_id ?? '') == $unit->id)>{{ $unit->name }} ({{ $unit->short_name }})</option>
                    @endforeach
                </select>
                <p class="mt-1 text-[11px] text-slate-400" x-show="type === 'raw_material'">{{ __('The unit you buy and use it in — e.g. Roll or Gaj.') }}</p>
                <x-input-error class="mt-2" :messages="$errors->get('stock_unit_id')" />
                @if ($units->isEmpty())
                    <p class="mt-1 text-xs text-amber-600">{{ __('No units yet —') }} <a href="{{ route('units.create') }}" class="underline">{{ __('add one first') }}</a>.</p>
                @endif
            </div>
        </div>
    </div>

    @if ($ecommerceEnabled ?? false)
    <!-- Product Gallery -->
    <div class="lg:col-span-3 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200"
         x-data="productGallery({
            existing: @js(($product->images ?? collect())->map(fn ($img) => ['id' => $img->id, 'url' => $img->url])),
         })">
        <div class="mb-4">
            <h3 class="font-bold text-brand-900">{{ __('Product Gallery') }}</h3>
            <p class="text-xs text-slate-400">{{ __('Extra photos shown on the online store\'s product page, in addition to the main Product Image above. PNG or JPG, up to 2MB each.') }}</p>
        </div>

        <div class="flex flex-wrap gap-3">
            <template x-for="img in existing" :key="'existing-'+img.id">
                <div class="relative h-24 w-24 shrink-0 overflow-hidden rounded-xl ring-1 ring-slate-200">
                    <img :src="img.url" alt="" class="h-full w-full object-cover">
                    <button type="button" @click="removeExisting(img.id)"
                            class="absolute right-1 top-1 grid h-5 w-5 place-items-center rounded-full bg-white/90 text-rose-600 shadow hover:bg-white">
                        <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                    </button>
                </div>
            </template>

            <template x-for="id in removeIds" :key="'remove-'+id">
                <input type="hidden" name="remove_gallery_ids[]" :value="id">
            </template>

            <template x-for="(item, i) in newFiles" :key="'new-'+i">
                <div class="relative h-24 w-24 shrink-0 overflow-hidden rounded-xl ring-1 ring-slate-200">
                    <img :src="item.url" alt="" class="h-full w-full object-cover">
                    <button type="button" @click="removeNew(i)"
                            class="absolute right-1 top-1 grid h-5 w-5 place-items-center rounded-full bg-white/90 text-rose-600 shadow hover:bg-white">
                        <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                    </button>
                </div>
            </template>

            <label class="grid h-24 w-24 shrink-0 cursor-pointer place-items-center rounded-xl border border-dashed border-slate-300 text-slate-400 hover:bg-slate-50">
                <span class="text-center text-[11px] font-semibold leading-tight">{{ __('Add Photos') }}</span>
                <input type="file" name="gallery_images[]" x-ref="galleryInput" accept="image/*" multiple class="hidden" @change="addFiles($event.target.files)">
            </label>
        </div>
        <x-input-error class="mt-3" :messages="$errors->get('gallery_images')" />
        <x-input-error class="mt-1" :messages="$errors->get('gallery_images.*')" />
    </div>
    @endif

    <!-- Price & stock -->
    <div class="lg:col-span-3 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <div class="mb-4">
            <h3 class="font-bold text-brand-900">{{ __('Price & Stock Alert') }}</h3>
            <p class="text-xs text-slate-400">{{ __('Cost is updated automatically from purchases and production.') }}</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <x-input-label for="estimated_cost" :value="__('Cost')" />
                <x-text-input id="estimated_cost" name="estimated_cost" type="number" step="0.01" min="0"
                              :readonly="$hasCostHistory"
                              class="mt-1 block w-full {{ $hasCostHistory ? 'bg-slate-50 text-slate-500 cursor-not-allowed' : '' }}"
                              :value="old('estimated_cost', $product->estimated_cost ?? '0')" required />
                @if ($hasCostHistory)
                    <p class="mt-1 text-[11px] text-amber-600">{{ __('Auto-calculated from stock history.') }}</p>
                @endif
                <x-input-error class="mt-2" :messages="$errors->get('estimated_cost')" />
            </div>

            <div x-show="type === 'finished_good'" x-cloak>
                <x-input-label for="selling_price" :value="__('Selling Price')" />
                <x-text-input id="selling_price" name="selling_price" type="number" step="0.01" min="0" class="mt-1 block w-full"
                              :value="old('selling_price', $product->selling_price ?? '0')" required />
                <x-input-error class="mt-2" :messages="$errors->get('selling_price')" />
            </div>

            <div>
                <x-input-label for="reorder_level" :value="__('Low Stock Alert At')" />
                <x-text-input id="reorder_level" name="reorder_level" type="number" min="0" class="mt-1 block w-full"
                              :value="old('reorder_level', $product->reorder_level ?? '0')" required />
                <x-input-error class="mt-2" :messages="$errors->get('reorder_level')" />
            </div>
        </div>
    </div>

    <!-- Sizes -->
    <div class="lg:col-span-3 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200" x-show="type === 'finished_good'" x-cloak>
        <label class="flex items-start gap-3">
            <input type="checkbox" name="has_variants" value="1" x-model="hasVariants"
                   class="mt-0.5 rounded border-slate-300 text-accent-600 focus:ring-accent-500">
            <span>
                <span class="block text-sm font-semibold text-brand-900">{{ __('Comes in sizes / colors') }}</span>
                <span class="block text-xs text-slate-400">{{ __('Each size is stocked, produced and sold separately.') }}</span>
            </span>
        </label>

        @include('admin.products._variants')
    </div>

    @if ($ecommerceEnabled ?? false)
    <!-- Long Description -->
    <div class="lg:col-span-3 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <div class="mb-4">
            <h3 class="font-bold text-brand-900">{{ __('Long Description') }}</h3>
            <p class="text-xs text-slate-400">{{ __('Shown in the Description section of the product\'s online store page — formatting (bold, lists, headings) is preserved') }}</p>
        </div>

        <input id="description" type="hidden" name="description" value="{{ old('description', $product->description ?? '') }}">
        <trix-editor input="description" class="block w-full"></trix-editor>
        <x-input-error class="mt-2" :messages="$errors->get('description')" />
    </div>
    @endif
</div>

@if (($product ?? null) && $product->has_variants)
@php
    $selectedValues = $product->variants
        ->flatMap->attributeValues
        ->groupBy(fn ($av) => $av->pivot->attribute_id)
        ->map(fn ($group) => $group->mapWithKeys(fn ($av) => [$av->id => $av->value]));

    $existingVariants = $product->variants->map(fn ($v) => [
        'key' => $v->attributeValues->map(fn ($av) => $av->pivot->attribute_id.':'.$av->id)->sort()->implode('|'),
        'id' => $v->id,
        'label' => $v->label,
        'values' => $v->attributeValues->mapWithKeys(fn ($av) => [$av->pivot->attribute_id => $av->id]),
        'sku' => $v->sku,
        'barcode' => $v->barcode,
        'selling_price' => $v->selling_price,
        'estimated_cost' => $v->estimated_cost,
        'has_cost_history' => $v->stockMovements()->exists(),
        'status' => (bool) $v->status,
    ]);
@endphp
<script>
    window.__selectedValues = @json($selectedValues);
    window.__existingVariants = @json($existingVariants);
</script>
@endif

<div class="mt-5 flex items-center gap-3">
    <x-primary-button>{{ $editing ? __('Update Product') : __('Create Product') }}</x-primary-button>
    <a href="{{ route('products.index') }}" class="rounded-lg px-4 py-2 text-sm font-semibold text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50">{{ __('Cancel') }}</a>
</div>
