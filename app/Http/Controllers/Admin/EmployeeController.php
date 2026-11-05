<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\RestrictsPermissionGrants;
use App\Http\Controllers\Controller;
use App\Models\Attachment;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\Site;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use RuntimeException;

class EmployeeController extends Controller implements HasMiddleware
{
    use RestrictsPermissionGrants;

    public static function middleware(): array
    {
        return [
            // Basic records use employees.*; turning a login on stays HR's.
            new Middleware('permission:employees.view', only: ['index']),
            new Middleware('permission:employees.create', only: ['create', 'store']),
            new Middleware('permission:employees.edit', only: ['edit', 'update', 'destroyAttachment']),
            new Middleware('permission:hrm.edit', only: ['toggleLogin']),
            new Middleware('permission:hrm.delete', only: ['destroy']),
        ];
    }

    public function index(Request $request)
    {
        $employees = Employee::with(['department', 'designation', 'site'])
            ->when($request->filled('department_id'), fn ($q) => $q->where('department_id', $request->department_id))
            ->when($request->filled('designation_id'), fn ($q) => $q->where('designation_id', $request->designation_id))
            ->when($request->filled('employment_status'), fn ($q) => $q->where('employment_status', $request->employment_status))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = $request->q;
                $q->where(function ($q) use ($term) {
                    $q->where('name', 'like', "%{$term}%")
                        ->orWhere('phone', 'like', "%{$term}%");
                });
            })
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('admin.employees.index', [
            'employees' => $employees,
            'departments' => Department::orderBy('name')->get(),
            'designations' => Designation::orderBy('name')->get(),
        ]);
    }

    public function create()
    {
        return view('admin.employees.create', $this->formData());
    }

    public function store(Request $request)
    {
        $validated = $this->validated($request);

        if ($request->hasFile('photo')) {
            $validated['photo_path'] = $request->file('photo')->store('employees', 'public');
        }

        $employee = Employee::create(collect($validated)->except(['photo', 'remove_photo', 'documents', 'document_labels'])->all());

        $this->storeDocuments($request, $employee);

        return redirect()->route('employees.index')->with('success', "Employee \"{$employee->name}\" created.");
    }

    public function edit(Employee $employee)
    {
        return view('admin.employees.edit', $this->formData($employee) + [
            'employee' => $employee,
            'assignableRoles' => $this->grantableRoles()->sortBy('name')->values(),
        ]);
    }

    public function update(Request $request, Employee $employee)
    {
        $validated = $this->validated($request, $employee);

        if ($request->boolean('remove_photo') && $employee->photo_path) {
            Storage::disk('public')->delete($employee->photo_path);
            $validated['photo_path'] = null;
        }

        if ($request->hasFile('photo')) {
            if ($employee->photo_path) {
                Storage::disk('public')->delete($employee->photo_path);
            }
            $validated['photo_path'] = $request->file('photo')->store('employees', 'public');
        }

        $employee->update(collect($validated)->except(['photo', 'remove_photo', 'documents', 'document_labels'])->all());

        $this->storeDocuments($request, $employee);

        return redirect()->route('employees.index')->with('success', "Employee \"{$employee->name}\" updated.");
    }

    public function toggleLogin(Request $request, Employee $employee)
    {
        if ($employee->hasActiveLogin()) {
            $employee->disableLogin();

            return back()->with('success', "Login disabled for \"{$employee->name}\".");
        }

        // A brand-new login needs a role, email, and password chosen up
        // front; re-activating a previously-disabled one just keeps
        // whatever role/email/password it already had.
        $roleName = null;
        $email = null;
        $password = null;
        $isNewLogin = ! $employee->user_id;

        if ($isNewLogin) {
            $validated = $request->validate([
                'role' => ['required', Rule::in($this->grantableRoles()->pluck('name'))],
                'email' => ['required', 'email', 'max:255'],
                'password' => ['required', 'confirmed', 'min:8'],
            ]);
            $roleName = $validated['role'];
            $email = $validated['email'];
            $password = $validated['password'];
        }

        try {
            $employee->enableLogin($roleName, $email, $password);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $isNewLogin
            ? "Login enabled for \"{$employee->name}\" with email \"{$email}\"."
            : "Login re-enabled for \"{$employee->name}\".");
    }

    public function destroyAttachment(Employee $employee, Attachment $attachment)
    {
        abort_unless(
            $attachment->attachable_type === Employee::class && $attachment->attachable_id === $employee->id,
            404
        );

        Storage::disk('public')->delete($attachment->path);
        $attachment->delete();

        return back()->with('success', "Document \"{$attachment->label}\" removed.");
    }

    public function destroy(Employee $employee)
    {
        if ($employee->subordinates()->exists()) {
            return back()->with('error', "\"{$employee->name}\" is still someone else's reporting manager — reassign their direct reports first.");
        }

        if ($employee->hasActiveLogin()) {
            $employee->disableLogin();
        }

        foreach ($employee->attachments as $attachment) {
            Storage::disk('public')->delete($attachment->path);
        }

        if ($employee->photo_path) {
            Storage::disk('public')->delete($employee->photo_path);
        }

        $employee->delete();

        return redirect()->route('employees.index')->with('success', "Employee \"{$employee->name}\" deleted.");
    }

    protected function formData(?Employee $employee = null): array
    {
        return [
            'sites' => Site::orderBy('name')->get(),
            'departments' => Department::where('status', true)->orderBy('name')->get(),
            'designations' => Designation::where('status', true)->orderBy('name')->get(),
            'managers' => Employee::where('id', '!=', $employee?->id)->orderBy('name')->get(),
            'employmentTypes' => Employee::EMPLOYMENT_TYPES,
            'employmentStatuses' => Employee::EMPLOYMENT_STATUSES,
        ];
    }

    protected function validated(Request $request, ?Employee $employee = null): array
    {
        return $request->validate([
            'site_id' => ['required', 'integer', 'exists:sites,id'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'designation_id' => ['nullable', 'integer', 'exists:designations,id'],
            'reporting_manager_id' => [
                'nullable', 'integer', 'exists:employees,id',
                Rule::notIn([$employee?->id]),
            ],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30', Rule::unique('employees', 'phone')->ignore($employee?->id)],
            'email' => ['required', 'email', 'max:255', Rule::unique('employees', 'email')->ignore($employee?->id)],
            'nid_no' => ['nullable', 'string', 'max:50'],
            'photo' => ['nullable', 'image', 'max:2048'],
            'remove_photo' => ['nullable', 'boolean'],
            'joining_date' => ['required', 'date'],
            'employment_type' => ['required', Rule::in(Employee::EMPLOYMENT_TYPES)],
            'employment_status' => ['required', Rule::in(Employee::EMPLOYMENT_STATUSES)],
            'notes' => ['nullable', 'string', 'max:2000'],
            'documents' => ['nullable', 'array'],
            'documents.*' => ['nullable', 'file', 'max:5120'],
            'document_labels' => ['nullable', 'array'],
            'document_labels.*' => ['nullable', 'string', 'max:255'],
        ]);
    }

    protected function storeDocuments(Request $request, Employee $employee): void
    {
        if (! $request->hasFile('documents')) {
            return;
        }

        $labels = $request->input('document_labels', []);

        foreach ($request->file('documents') as $index => $file) {
            if (! $file) {
                continue;
            }

            $employee->attachments()->create([
                'label' => $labels[$index] ?? $file->getClientOriginalName(),
                'path' => $file->store('employees/documents', 'public'),
                'original_name' => $file->getClientOriginalName(),
                'uploaded_by' => auth()->id(),
            ]);
        }
    }
}
