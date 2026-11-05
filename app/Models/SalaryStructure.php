<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalaryStructure extends Model
{
    /**
     * piece_rate: no fixed pay — the month's production piece work
     * (pieces × PieceRate) is the salary.
     */
    public const MODES = ['flat', 'components', 'piece_rate'];

    protected $fillable = [
        'employee_id',
        'mode',
        'flat_amount',
        'basic',
        'house_rent',
        'medical',
        'conveyance',
        'effective_from',
    ];

    protected $casts = [
        'flat_amount' => 'decimal:2',
        'basic' => 'decimal:2',
        'house_rent' => 'decimal:2',
        'medical' => 'decimal:2',
        'conveyance' => 'decimal:2',
        'effective_from' => 'date',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function grossAmount(): float
    {
        if ($this->mode === 'flat') {
            return (float) $this->flat_amount;
        }

        if ($this->mode === 'piece_rate') {
            return 0;
        }

        return (float) $this->basic + (float) $this->house_rent + (float) $this->medical + (float) $this->conveyance;
    }
}
