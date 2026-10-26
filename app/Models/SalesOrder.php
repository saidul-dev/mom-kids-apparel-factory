<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * A customer order shipped from the Factory or the wholesale Outlet.
 * Recording it reserves nothing — stock leaves and the customer is
 * billed only on Deliver, when every line must be in stock at the Site.
 * Payment is collected later via Collections (Payment In).
 */
class SalesOrder extends Model
{
    public const STATUSES = ['pending', 'delivered', 'cancelled'];

    protected $fillable = [
        'order_no',
        'site_id',
        'party_id',
        'order_date',
        'status',
        'subtotal',
        'discount',
        'total',
        'note',
        'created_by',
        'delivered_at',
        'delivered_by',
    ];

    protected $casts = [
        'order_date' => 'date',
        'delivered_at' => 'date',
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
        return $this->hasMany(SalesOrderItem::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function deliveredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delivered_by');
    }

    public function movements(): MorphMany
    {
        return $this->morphMany(StockMovement::class, 'reference');
    }

    public function ledgerTransactions(): MorphMany
    {
        return $this->morphMany(LedgerTransaction::class, 'reference');
    }
}
