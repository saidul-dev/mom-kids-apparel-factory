<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\ProductionPieceWork;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * Piece work sheet: who did how many pieces of what, and what they
 * earned, for a day (today by default) or any date range. Entries are made
 * on the production page; this only reads them.
 */
class PieceWorkController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:hrm.view', only: ['index']),
        ];
    }

    public function index(Request $request)
    {
        $from = $request->date('from')?->toDateString() ?? today()->toDateString();
        $to = $request->date('to')?->toDateString() ?? $from;
        $employeeId = $request->filled('employee_id') ? $request->integer('employee_id') : null;

        $works = ProductionPieceWork::with([
            'employee', 'productionOrder.product', 'productionOrder.productVariant.attributeValues',
        ])
            ->whereDate('entry_date', '>=', $from)
            ->whereDate('entry_date', '<=', $to)
            ->when($employeeId, fn ($q) => $q->where('employee_id', $employeeId))
            ->orderByDesc('entry_date')
            ->orderByDesc('id')
            ->get();

        $byEmployee = $works->groupBy('employee_id')->map(fn ($rows) => [
            'employee' => $rows->first()->employee,
            'pcs' => $rows->sum(fn ($w) => (float) $w->quantity),
            'amount' => $rows->sum(fn ($w) => (float) $w->amount),
        ])->sortBy(fn ($r) => $r['employee']->name)->values();

        $employees = Employee::pieceRateWorkers()->orderBy('name')->get(['id', 'name']);

        return view('admin.piece-works.index', compact('works', 'byEmployee', 'employees', 'from', 'to', 'employeeId'));
    }
}
