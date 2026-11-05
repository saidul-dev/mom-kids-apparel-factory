<?php

namespace App\Http\Controllers;

use App\Models\ProductionPieceWork;
use Illuminate\Support\Facades\Auth;

/**
 * Self-service: the logged-in user's own employee record, read-only — job
 * details, pay type, this month's piece work and their approved payslips.
 * Never anyone else's, so it needs no HR permission.
 */
class MyProfileController extends Controller
{
    public function show()
    {
        $employee = Auth::user()->employee;
        abort_unless($employee, 403, 'No employee profile is linked to your login.');

        $employee->load(['site', 'department', 'designation', 'reportingManager', 'salaryStructure']);

        $pieceWork = null;
        if ($employee->salaryStructure?->mode === 'piece_rate') {
            $pieceWork = ProductionPieceWork::where('employee_id', $employee->id)
                ->whereBetween('entry_date', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString().' 23:59:59'])
                ->selectRaw('COALESCE(SUM(quantity), 0) as pcs, COALESCE(SUM(amount), 0) as amount')
                ->first();
        }

        $payslips = $employee->payrollRunItems()
            ->whereHas('payrollRun', fn ($q) => $q->where('status', 'approved'))
            ->with('payrollRun')
            ->get()
            ->sortByDesc(fn ($item) => $item->payrollRun->year * 100 + $item->payrollRun->month)
            ->values();

        return view('my-profile', compact('employee', 'pieceWork', 'payslips'));
    }
}
