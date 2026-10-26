<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * A production lot for one finished garment (product or variant) at one
 * Site. Pieces flow Cutting → Sewing → Finishing (iron) → Blister/Poly;
 * each stage can only take what the previous stage has handed over and
 * not yet passed on, e.g. 500 cut → 300 sewn → 200 ironed → 150 packed
 * leaves 200 at cutting, 100 at sewing and 50 at finishing. Only
 * Blister/Poly puts goods into finished stock.
 * See docs/garments-production.md.
 */
class ProductionOrder extends Model
{
    public const STATUSES = ['in_progress', 'completed'];

    /**
     * Fixed stage order — each stage draws from the one before it.
     */
    public const STAGES = [
        'cutting' => 'Cutting',
        'sewing' => 'Sewing',
        'finishing' => 'Finishing (Iron)',
        'packing' => 'Blister / Poly',
    ];

    /**
     * The stage whose output goes into finished-goods stock.
     */
    public const FINAL_STAGE = 'packing';

    protected $fillable = [
        'production_no',
        'site_id',
        'product_id',
        'product_variant_id',
        'planned_quantity',
        'start_date',
        'status',
        'note',
        'created_by',
        'completed_at',
        'completed_by',
        'wastage_value',
    ];

    protected $casts = [
        'planned_quantity' => 'decimal:4',
        'start_date' => 'date',
        'completed_at' => 'date',
        'wastage_value' => 'decimal:2',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function stageEntries(): HasMany
    {
        return $this->hasMany(ProductionStageEntry::class);
    }

    public function materials(): HasMany
    {
        return $this->hasMany(ProductionMaterial::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function movements(): MorphMany
    {
        return $this->morphMany(StockMovement::class, 'reference');
    }

    public function ledgerTransactions(): MorphMany
    {
        return $this->morphMany(LedgerTransaction::class, 'reference');
    }

    public function getItemLabelAttribute(): string
    {
        return $this->productVariant
            ? "{$this->product->name} — {$this->productVariant->label}"
            : $this->product->name;
    }

    /**
     * Total pieces recorded per stage, e.g. ['cutting' => 200, 'sewing' => 100, 'finishing' => 50].
     */
    public function stageTotals(): array
    {
        $sums = $this->stageEntries()
            ->selectRaw('stage, SUM(quantity) as total')
            ->groupBy('stage')
            ->pluck('total', 'stage');

        return collect(self::STAGES)->map(fn ($label, $stage) => (float) ($sums[$stage] ?? 0))->all();
    }

    /**
     * Pieces a stage can still take: whatever the previous stage has
     * produced that this stage hasn't processed yet. Cutting has no
     * upstream stage, so it's unbounded (null).
     */
    public function availableFor(string $stage, ?array $totals = null): ?float
    {
        $totals ??= $this->stageTotals();
        $stages = array_keys(self::STAGES);
        $index = array_search($stage, $stages, true);

        if ($index === false || $index === 0) {
            return null;
        }

        return max($totals[$stages[$index - 1]] - $totals[$stage], 0);
    }

    /**
     * Pieces cut but not yet packed into finished stock — everything still
     * on the floor.
     */
    public function piecesInProcess(?array $totals = null): float
    {
        $totals ??= $this->stageTotals();

        return max($totals['cutting'] - $totals[self::FINAL_STAGE], 0);
    }

    /**
     * Where the pieces are right now: done at a stage but not yet taken by
     * the next one. 'stock' is what has been packed into finished goods.
     */
    public function currentlyAt(?array $totals = null): array
    {
        $totals ??= $this->stageTotals();
        $stages = array_keys(self::STAGES);
        $current = [];

        foreach ($stages as $i => $stage) {
            $next = $stages[$i + 1] ?? null;
            $current[$stage] = $next ? max($totals[$stage] - $totals[$next], 0) : 0;
        }
        $current['stock'] = $totals[self::FINAL_STAGE];
        unset($current[self::FINAL_STAGE]);

        return $current;
    }

    /**
     * Cost sitting in Work-in-Progress for this lot: every material issued
     * to it, less what Blister/Poly has already moved into finished stock
     * and whatever was written off on close.
     */
    public function wipValue(): float
    {
        $issued = (float) $this->materials()->selectRaw('COALESCE(SUM(quantity * unit_cost), 0) as v')->value('v');
        $finished = (float) $this->stageEntries()->where('stage', self::FINAL_STAGE)
            ->selectRaw('COALESCE(SUM(quantity * unit_cost), 0) as v')->value('v');

        return round(max($issued - $finished - (float) $this->wastage_value, 0), 2);
    }

    public function isOpen(): bool
    {
        return $this->status === 'in_progress';
    }
}
