<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CompanySetting;
use App\Models\LedgerAccount;
use App\Models\PayrollRun;
use App\Models\PayrollRunItem;
use App\Models\Site;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

class PayrollRunController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:hrm.view', only: ['index', 'show']),
            new Middleware('permission:hrm.create', only: ['create', 'store']),
            new Middleware('permission:hrm.edit', only: ['updateItem']),
            new Middleware('permission:hrm.approve', only: ['approve']),
            new Middleware('permission:hrm.delete', only: ['destroy']),
        ];
    }

    public function index(Request $request)
    {
        $runs = PayrollRun::with('site')
            ->when($request->filled('site_id'), fn ($q) => $q->where('site_id', $request->site_id))
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->paginate(15)
            ->withQueryString();

        return view('admin.payroll-runs.index', [
            'runs' => $runs,
            'sites' => Site::orderBy('name')->get(),
        ]);
    }

    public function create()
    {
        return view('admin.payroll-runs.create', [
            'sites' => Site::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'site_id' => ['required', 'integer', 'exists:sites,id'],
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'min:2000'],
        ]);

        $exists = PayrollRun::where('site_id', $validated['site_id'])
            ->where('month', $validated['month'])
            ->where('year', $validated['year'])
            ->exists();

        if ($exists) {
            return back()->withInput()->with('error', 'A payroll run already exists for this site and month.');
        }

        $run = PayrollRun::create([
            'site_id' => $validated['site_id'],
            'month' => $validated['month'],
            'year' => $validated['year'],
            'status' => 'draft',
            'generated_by' => Auth::id(),
        ]);

        $skipped = $run->generateItems();

        $message = "Payroll run generated for {$run->monthLabel()}.";
        if ($skipped) {
            $message .= ' Skipped (no salary structure set): '.implode(', ', $skipped).'.';
        }

        return redirect()->route('payroll-runs.show', $run)->with('success', $message);
    }

    public function show(PayrollRun $payrollRun)
    {
        $payrollRun->load(['items.employee', 'items.paidFromAccount', 'site']);

        return view('admin.payroll-runs.show', [
            'payrollRun' => $payrollRun,
            'cashAccounts' => LedgerAccount::where('group', 'cash_bank')->where('status', true)->orderBy('name')->get(),
        ]);
    }

    public function updateItem(Request $request, PayrollRun $payrollRun, PayrollRunItem $item)
    {
        abort_unless($payrollRun->status === 'draft', 403, 'This payroll run has already been approved.');
        abort_unless($item->payroll_run_id === $payrollRun->id, 404);

        $validated = $request->validate([
            'absence_days' => ['required', 'numeric', 'min:0'],
            'absence_deduction' => ['required', 'numeric', 'min:0'],
            'advance_recovery' => ['required', 'numeric', 'min:0'],
            'paid_from_account_id' => ['nullable', 'integer', 'exists:ledger_accounts,id'],
        ]);

        $item->fill($validated);
        $item->recomputeNet();
        $item->save();

        $payrollRun->update(['total_amount' => $payrollRun->items()->sum('net_amount')]);

        return back()->with('success', "Updated {$item->employee->name}'s payroll line.");
    }

    public function approve(PayrollRun $payrollRun)
    {
        try {
            $payrollRun->approveAndPost(Auth::user());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('payroll-runs.show', $payrollRun)
            ->with('success', "Payroll run for {$payrollRun->monthLabel()} approved and posted to Accounts.");
    }

    public function destroy(PayrollRun $payrollRun)
    {
        abort_unless($payrollRun->status === 'draft', 403, 'Only a draft payroll run can be deleted.');

        $payrollRun->delete();

        return redirect()->route('payroll-runs.index')->with('success', 'Payroll run deleted.');
    }

    public function payslip(PayrollRunItem $item)
    {
        $user = Auth::user();
        abort_unless($user->can('hrm.view') || $item->employee_id === $user->employee?->id, 403);

        $item->load(['employee', 'payrollRun.site', 'paidFromAccount']);
        $company = CompanySetting::current();

        // Piece work behind a piece-rate payslip, one line per item, stage and rate.
        $pieceLines = $item->mode !== 'piece_rate' ? collect() : $item->payrollRun->pieceWorkFor($item->employee)
            ->with('productionOrder.product')
            ->get()
            ->groupBy(fn ($w) => $w->productionOrder->product_id.'|'.$w->stage.'|'.$w->rate)
            ->map(fn ($rows) => (object) [
                'item' => $rows->first()->productionOrder->product->name,
                'stage' => $rows->first()->stage_label,
                'pcs' => $rows->sum(fn ($w) => (float) $w->quantity),
                'rate' => (float) $rows->first()->rate,
                'amount' => $rows->sum(fn ($w) => (float) $w->amount),
            ])
            ->sortBy('item')
            ->values();

        return view('admin.payroll-runs.payslip', compact('item', 'company', 'pieceLines'));
    }
}
