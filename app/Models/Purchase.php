<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * Goods received from a Supplier — for this factory mostly fabric and
 * other raw material. Posting is immediate (no draft/approve step): one
 * `purchase` stock movement per line at the receiving Site, plus a
 * Debit Inventory / Credit Accounts Payable voucher. Settled later via
 * Payments (Payment Out).
 */
class Purchase extends Model
{
    protected $fillable = [
        'purchase_no',
        'site_id',
        'party_id',
        'purchase_date',
        'supplier_invoice_no',
        'subtotal',
        'discount',
        'total',
        'note',
        'created_by',
    ];

    protected $casts = [
        'purchase_date' => 'date',
        'subtotal' => 'decimal:2',
        'discount' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function movements(): MorphMany
    {
        return $this->morphMany(StockMovement::class, 'reference');
    }

    public function ledgerTransaction(): MorphOne
    {
        return $this->morphOne(LedgerTransaction::class, 'reference');
    }
}
