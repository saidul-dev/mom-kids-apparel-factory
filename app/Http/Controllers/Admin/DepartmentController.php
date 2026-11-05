<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class DepartmentController extends Controller implements HasMiddleware
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
        $departments = Department::withCount('employees')->orderBy('name')->paginate(15);

        return view('admin.departments.index', compact('departments'));
    }

    public function create()
    {
        return view('admin.departments.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $department = Department::create($validated);

        return redirect()->route('departments.index')->with('success', "Department \"{$department->name}\" created.");
    }

    public function edit(Department $department)
    {
        return view('admin.departments.edit', ['department' => $department]);
    }

    public function update(Request $request, Department $department)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $department->update($validated);

        return redirect()->route('departments.index')->with('success', "Department \"{$department->name}\" updated.");
    }

    public function toggleStatus(Department $department)
    {
        $department->update(['status' => ! $department->status]);

        return back()->with('success', "Department \"{$department->name}\" is now ".($department->status ? 'active' : 'inactive').'.');
    }

    public function destroy(Department $department)
    {
        if ($department->employees()->exists()) {
            return back()->with('error', "Department \"{$department->name}\" still has employees assigned — reassign them first.");
        }

        $department->delete();

        return redirect()->route('departments.index')->with('success', "Department \"{$department->name}\" deleted.");
    }
}
