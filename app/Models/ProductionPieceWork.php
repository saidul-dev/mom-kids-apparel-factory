<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pieces one piece-rate worker did in a production stage entry, and what
 * they earned for them (quantity × the rate at the time).
 */
class ProductionPieceWork extends Model
{
    protected $fillable = [
        'production_order_id',
        'production_stage_entry_id',
        'employee_id',
        'stage',
        'quantity',
        'rate',
        'amount',
        'entry_date',
        'created_by',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'rate' => 'decimal:2',
        'amount' => 'decimal:2',
        'entry_date' => 'date',
    ];

    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class);
    }

    public function stageEntry(): BelongsTo
    {
        return $this->belongsTo(ProductionStageEntry::class, 'production_stage_entry_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function getStageLabelAttribute(): string
    {
        return ProductionOrder::STAGES[$this->stage] ?? ucfirst($this->stage);
    }
}
