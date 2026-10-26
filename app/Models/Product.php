<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Product extends Model
{
    /**
     * Raw material is consumed by production; finished goods are what
     * production makes and sales sell. Plain string column, no BOM.
     */
    public const TYPES = [
        'raw_material' => 'Raw Material',
        'finished_good' => 'Finished Goods',
    ];

    protected $fillable = [
        'name',
        'type',
        'sku',
        'barcode',
        'category_id',
        'brand_id',
        'stock_unit_id',
        'purchase_unit_id',
        'purchase_unit_conversion',
        'sale_unit_id',
        'sale_unit_conversion',
        'description',
        'short_description',
        'estimated_cost',
        'selling_price',
        'reorder_level',
        'image_path',
        'status',
        'has_variants',
        'track_batch',
        'track_expiry',
        'track_serial',
        'warranty_period',
        'warranty_unit',
        'guarantee_period',
        'guarantee_unit',
    ];

    protected $casts = [
        'status' => 'boolean',
        // 4dp: a perpetual weighted-average, recalculated on every costed "in"
        // movement (see StockMovement::recalculateAverageCost()) — rounding
        // it to money precision here would drift qty × cost off the ledger's
        // true total. UI display still formats to 2dp.
        'estimated_cost' => 'decimal:4',
        'selling_price' => 'decimal:2',
        'purchase_unit_conversion' => 'decimal:4',
        'sale_unit_conversion' => 'decimal:4',
        'reorder_level' => 'integer',
        'has_variants' => 'boolean',
        'track_batch' => 'boolean',
        'track_expiry' => 'boolean',
        'track_serial' => 'boolean',
        'warranty_period' => 'integer',
        'guarantee_period' => 'integer',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /**
     * The unit stock quantity is tracked in — the canonical unit.
     */
    public function stockUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'stock_unit_id');
    }

    /**
     * Unit used when purchasing this product. Falls back to the stock
     * unit (1:1) when not set — see purchase_unit_conversion.
     */
    public function purchaseUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'purchase_unit_id');
    }

    /**
     * Unit used when selling this product. Falls back to the stock
     * unit (1:1) when not set — see sale_unit_conversion.
     */
    public function saleUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'sale_unit_id');
    }

    public function getImageUrlAttribute(): ?string
    {
        return $this->image_path ? Storage::disk('public')->url($this->image_path) : null;
    }

    /**
     * Extra gallery photos shown on the storefront product detail page —
     * Product::image_path/image_url stays the cover image used everywhere
     * else (admin list, storefront cards, related products).
     */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /**
     * Next auto SKU for a product name: the first letter of each word (up
     * to 3) plus a 3-digit running number for that code, e.g.
     * "Girls Silk Frock" → GSF-001, a second "Silk Fabric" → SF-002.
     * Generated once on create and never changed with the name, so printed
     * labels and past records stay valid.
     */
    public static function nextSku(string $name): string
    {
        $words = preg_split('/[^A-Za-z0-9]+/', Str::ascii($name), -1, PREG_SPLIT_NO_EMPTY);
        $code = strtoupper(implode('', array_map(fn ($w) => $w[0], array_slice($words, 0, 3)))) ?: 'P';

        $max = static::where('sku', 'like', "{$code}-%")->pluck('sku')
            ->map(fn ($sku) => preg_match('/^'.$code.'-(\d+)$/', $sku, $m) ? (int) $m[1] : 0)
            ->max() ?? 0;

        return sprintf('%s-%03d', $code, $max + 1);
    }

    /**
     * Auto barcode: an EAN-13 number in the "2x" in-store range — 2-digit
     * prefix (20 = product, 21 = variant) + the row id padded to 10 digits
     * + check digit. Unique by construction, numeric so any shop scanner or
     * POS reads it; a real GS1 barcode can still be typed in over it.
     */
    public static function internalBarcode(string $prefix, int $id): string
    {
        $digits = $prefix.str_pad((string) $id, 10, '0', STR_PAD_LEFT);
        $sum = 0;
        foreach (str_split($digits) as $i => $d) {
            $sum += (int) $d * ($i % 2 === 0 ? 1 : 3);
        }

        return $digits.((10 - $sum % 10) % 10);
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function isRawMaterial(): bool
    {
        return $this->type === 'raw_material';
    }

    public function isVariable(): bool
    {
        return (bool) $this->has_variants;
    }
}
