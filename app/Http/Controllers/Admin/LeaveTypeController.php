<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LeaveType;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class LeaveTypeController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:hrm.edit|hrm.approve', only: ['index']),
            new Middleware('permission:hrm.create', only: ['create', 'store']),
            new Middleware('permission:hrm.edit', only: ['edit', 'update', 'toggleStatus']),
            new Middleware('permission:hrm.delete', only: ['destroy']),
        ];
    }

    public function index()
    {
        $leaveTypes = LeaveType::withCount('requests')->orderBy('name')->paginate(15);

        return view('admin.leave-types.index', compact('leaveTypes'));
    }

    public function create()
    {
        return view('admin.leave-types.create');
    }

    public function store(Request $request)
    {
        $validated = $this->validated($request);

        $leaveType = LeaveType::create($validated);

        return redirect()->route('leave-types.index')->with('success', "Leave type \"{$leaveType->name}\" created.");
    }

    public function edit(LeaveType $leaveType)
    {
        return view('admin.leave-types.edit', ['leaveType' => $leaveType]);
    }

    public function update(Request $request, LeaveType $leaveType)
    {
        $validated = $this->validated($request);

        $leaveType->update($validated);

        return redirect()->route('leave-types.index')->with('success', "Leave type \"{$leaveType->name}\" updated.");
    }

    public function toggleStatus(LeaveType $leaveType)
    {
        $leaveType->update(['status' => ! $leaveType->status]);

        return back()->with('success', "Leave type \"{$leaveType->name}\" is now ".($leaveType->status ? 'active' : 'inactive').'.');
    }

    public function destroy(LeaveType $leaveType)
    {
        if ($leaveType->requests()->exists() || $leaveType->balances()->exists()) {
            return back()->with('error', "Leave type \"{$leaveType->name}\" already has requests or balances recorded against it — it can only be deactivated, not deleted.");
        }

        $leaveType->delete();

        return redirect()->route('leave-types.index')->with('success', "Leave type \"{$leaveType->name}\" deleted.");
    }

    protected function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'default_days_per_year' => ['required', 'integer', 'min:0'],
        ]);
    }
}
