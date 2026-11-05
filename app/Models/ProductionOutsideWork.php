<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Third-party work on a production lot (embroidery, print...) — its
 * amount is part of the lot's WIP cost and owed to the vendor.
 */
class ProductionOutsideWork extends Model
{
    /**
     * Suggestions only — any other work type can be typed in.
     */
    public const WORK_TYPES = ['Embroidery', 'Print', 'Wash', 'Dyeing'];

    protected $fillable = [
        'production_order_id',
        'party_id',
        'work_type',
        'quantity',
        'unit_price',
        'amount',
        'entry_date',
        'note',
        'created_by',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'unit_price' => 'decimal:4',
        'amount' => 'decimal:2',
        'entry_date' => 'date',
    ];

    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class);
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
