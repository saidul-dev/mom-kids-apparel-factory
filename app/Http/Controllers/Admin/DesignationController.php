<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Designation;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class DesignationController extends Controller implements HasMiddleware
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
        $designations = Designation::withCount('employees')->orderBy('name')->paginate(15);

        return view('admin.designations.index', compact('designations'));
    }

    public function create()
    {
        return view('admin.designations.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $designation = Designation::create($validated);

        return redirect()->route('designations.index')->with('success', "Designation \"{$designation->name}\" created.");
    }

    public function edit(Designation $designation)
    {
        return view('admin.designations.edit', ['designation' => $designation]);
    }

    public function update(Request $request, Designation $designation)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $designation->update($validated);

        return redirect()->route('designations.index')->with('success', "Designation \"{$designation->name}\" updated.");
    }

    public function toggleStatus(Designation $designation)
    {
        $designation->update(['status' => ! $designation->status]);

        return back()->with('success', "Designation \"{$designation->name}\" is now ".($designation->status ? 'active' : 'inactive').'.');
    }

    public function destroy(Designation $designation)
    {
        if ($designation->employees()->exists()) {
            return back()->with('error', "Designation \"{$designation->name}\" still has employees assigned — reassign them first.");
        }

        $designation->delete();

        return redirect()->route('designations.index')->with('success', "Designation \"{$designation->name}\" deleted.");
    }
}
