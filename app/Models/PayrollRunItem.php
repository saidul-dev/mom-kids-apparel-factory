<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollRunItem extends Model
{
    use Auditable;

    protected $fillable = [
        'payroll_run_id',
        'employee_id',
        'mode',
        'flat_amount',
        'basic',
        'house_rent',
        'medical',
        'conveyance',
        'piece_quantity',
        'gross_amount',
        'absence_days',
        'absence_deduction',
        'advance_recovery',
        'net_amount',
        'paid_from_account_id',
        'ledger_transaction_id',
    ];

    protected $casts = [
        'flat_amount' => 'decimal:2',
        'basic' => 'decimal:2',
        'house_rent' => 'decimal:2',
        'medical' => 'decimal:2',
        'conveyance' => 'decimal:2',
        'piece_quantity' => 'decimal:4',
        'gross_amount' => 'decimal:2',
        'absence_days' => 'decimal:1',
        'absence_deduction' => 'decimal:2',
        'advance_recovery' => 'decimal:2',
        'net_amount' => 'decimal:2',
    ];

    public function payrollRun(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function paidFromAccount(): BelongsTo
    {
        return $this->belongsTo(LedgerAccount::class, 'paid_from_account_id');
    }

    public function ledgerTransaction(): BelongsTo
    {
        return $this->belongsTo(LedgerTransaction::class);
    }

    public function recomputeNet(): void
    {
        $this->net_amount = round(
            (float) $this->gross_amount - (float) $this->absence_deduction - (float) $this->advance_recovery,
            2
        );
    }
}
