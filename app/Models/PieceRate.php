<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pay per piece for one item at one production stage.
 */
class PieceRate extends Model
{
    /**
     * Production stages paid by the piece (a subset of
     * ProductionOrder::STAGES — cutting isn't).
     */
    public const STAGES = ['sewing', 'finishing', 'packing'];

    protected $fillable = [
        'product_id',
        'stage',
        'rate',
    ];

    protected $casts = [
        'rate' => 'decimal:2',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
