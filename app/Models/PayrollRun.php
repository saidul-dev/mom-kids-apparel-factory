<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Services\LedgerService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PayrollRun extends Model
{
    use Auditable;

    public const STATUSES = ['draft', 'approved'];

    protected $fillable = [
        'site_id',
        'month',
        'year',
        'status',
        'generated_by',
        'approved_by',
        'approved_at',
        'total_amount',
    ];

    protected $casts = [
        'month' => 'integer',
        'year' => 'integer',
        'approved_at' => 'datetime',
        'total_amount' => 'decimal:2',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PayrollRunItem::class);
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function monthLabel(): string
    {
        return Carbon::create($this->year, $this->month, 1)->format('F Y');
    }

    /**
     * One item per active employee at this run's site who has a
     * SalaryStructure. Absence days are pre-filled from AttendanceLog as
     * a convenience default only — the doc explicitly keeps this
     * "entered, not rule-engine yet", so HR can edit every figure before
     * approving. Returns the names of employees skipped for having no
     * SalaryStructure yet.
     *
     * Piece-rate employees earn the month's production piece work (pieces
     * × rate) instead of a fixed amount, with nothing deducted for absence;
     * one with no piece work that month gets no line.
     */
    public function generateItems(): array
    {
        $daysInMonth = Carbon::create($this->year, $this->month, 1)->daysInMonth;

        $employees = Employee::where('site_id', $this->site_id)
            ->where('employment_status', 'active')
            ->with('salaryStructure')
            ->get();

        $skipped = [];

        foreach ($employees as $employee) {
            $structure = $employee->salaryStructure;

            if (! $structure) {
                $skipped[] = $employee->name;

                continue;
            }

            $gross = $structure->grossAmount();
            $pieceQuantity = null;

            if ($structure->mode === 'piece_rate') {
                $work = $this->pieceWorkFor($employee)
                    ->selectRaw('COALESCE(SUM(quantity), 0) as pcs, COALESCE(SUM(amount), 0) as amount')
                    ->first();

                if ((float) $work->amount <= 0) {
                    continue;
                }

                $gross = round((float) $work->amount, 2);
                $pieceQuantity = (float) $work->pcs;
            }

            $absenceDays = AttendanceLog::where('employee_id', $employee->id)
                ->where('status', 'absent')
                ->whereMonth('date', $this->month)
                ->whereYear('date', $this->year)
                ->count();

            $perDayRate = $daysInMonth > 0 && $structure->mode !== 'piece_rate' ? $gross / $daysInMonth : 0;
            $absenceDeduction = round($perDayRate * $absenceDays, 2);

            $this->items()->create([
                'employee_id' => $employee->id,
                'mode' => $structure->mode,
                'flat_amount' => $structure->flat_amount,
                'basic' => $structure->basic,
                'house_rent' => $structure->house_rent,
                'medical' => $structure->medical,
                'conveyance' => $structure->conveyance,
                'piece_quantity' => $pieceQuantity,
                'gross_amount' => $gross,
                'absence_days' => $absenceDays,
                'absence_deduction' => $absenceDeduction,
                'advance_recovery' => 0,
                'net_amount' => round($gross - $absenceDeduction, 2),
            ]);
        }

        $this->update(['total_amount' => $this->items()->sum('net_amount')]);

        return $skipped;
    }

    /**
     * The employee's production piece work in this run's month.
     */
    public function pieceWorkFor(Employee $employee)
    {
        return ProductionPieceWork::where('employee_id', $employee->id)
            ->whereMonth('entry_date', $this->month)
            ->whereYear('entry_date', $this->year);
    }

    /**
     * Dr Salary Expense / Cr [employee's payment account] per item — the
     * same two-line, no-party pattern ExpenseController::store() already
     * uses via LedgerService::post(). Approval and posting happen
     * atomically; there is no separate "posted" state.
     */
    public function approveAndPost(User $actor): void
    {
        if ($this->status !== 'draft') {
            throw new RuntimeException('Only a draft payroll run can be approved.');
        }

        $salaryExpense = LedgerAccount::where('code', 'salary_expense')->firstOrFail();

        DB::transaction(function () use ($actor, $salaryExpense) {
            foreach ($this->items as $item) {
                if (! $item->paid_from_account_id) {
                    throw new RuntimeException("Set a payment account for \"{$item->employee->name}\" before approving.");
                }

                $transaction = LedgerService::post([
                    'type' => 'salary',
                    'date' => now(),
                    'site_id' => $this->site_id,
                    'narration' => "Salary for {$item->employee->name} — {$this->monthLabel()}",
                    'reference' => $item,
                    'created_by' => $actor->id,
                    'lines' => [
                        ['account' => $salaryExpense, 'debit' => $item->net_amount],
                        ['account' => $item->paidFromAccount, 'credit' => $item->net_amount],
                    ],
                ]);

                $item->update(['ledger_transaction_id' => $transaction->id]);
            }

            $this->update([
                'status' => 'approved',
                'approved_by' => $actor->id,
                'approved_at' => now(),
            ]);
        });
    }
}
