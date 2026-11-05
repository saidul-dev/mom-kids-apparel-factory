<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasAttachments;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class Employee extends Model
{
    use Auditable, HasAttachments;

    public const EMPLOYMENT_TYPES = ['permanent', 'probation', 'contractual', 'part_time'];

    public const EMPLOYMENT_STATUSES = ['active', 'resigned', 'terminated', 'on_leave'];

    protected $fillable = [
        'site_id',
        'department_id',
        'designation_id',
        'reporting_manager_id',
        'name',
        'phone',
        'email',
        'nid_no',
        'photo_path',
        'joining_date',
        'employment_type',
        'employment_status',
        'notes',
    ];

    protected $casts = [
        'joining_date' => 'date',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function designation(): BelongsTo
    {
        return $this->belongsTo(Designation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reportingManager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'reporting_manager_id');
    }

    public function subordinates(): HasMany
    {
        return $this->hasMany(Employee::class, 'reporting_manager_id');
    }

    public function attendanceLogs(): HasMany
    {
        return $this->hasMany(AttendanceLog::class);
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function leaveBalances(): HasMany
    {
        return $this->hasMany(LeaveBalance::class);
    }

    public function salaryStructure(): HasOne
    {
        return $this->hasOne(SalaryStructure::class);
    }

    public function payrollRunItems(): HasMany
    {
        return $this->hasMany(PayrollRunItem::class);
    }

    public function pieceWorks(): HasMany
    {
        return $this->hasMany(ProductionPieceWork::class);
    }

    /**
     * Active employees paid by the piece — the workers a production stage
     * entry can be split between.
     */
    public function scopePieceRateWorkers($query, ?int $siteId = null)
    {
        return $query->where('employment_status', 'active')
            ->when($siteId, fn ($q) => $q->where('site_id', $siteId))
            ->whereHas('salaryStructure', fn ($q) => $q->where('mode', 'piece_rate'));
    }

    /**
     * Three tiers, per docs/hrm-employee-management.md §1.5's RBAC list:
     * HR/Admin (hrm.edit) see everyone; a Manager (hrm.approve without
     * hrm.edit — "Manager (own team only)") sees their own direct
     * reports; anyone else (a plain Employee-role login) sees only
     * themselves. Used by Attendance/Leave list views.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->can('hrm.edit')) {
            return $query;
        }

        if ($user->can('hrm.approve')) {
            return $query->where('reporting_manager_id', $user->employee?->id ?? 0);
        }

        return $query->where('id', $user->employee?->id ?? 0);
    }

    public function getPhotoUrlAttribute(): ?string
    {
        return $this->photo_path ? Storage::disk('public')->url($this->photo_path) : null;
    }

    public function hasActiveLogin(): bool
    {
        return (bool) ($this->user_id && $this->user?->is_active);
    }

    /**
     * Turns login on. If a User row already exists (previously disabled),
     * it's simply re-activated — a second row is never created, so the
     * original User stays intact as created_by/approved_by on years of
     * historical rows, and keeps whatever role it already had ($roleName,
     * $email and $password are only used the first time, when provisioning
     * a brand-new User).
     */
    public function enableLogin(?string $roleName = null, ?string $email = null, ?string $password = null): void
    {
        if ($this->user_id) {
            $this->user->update(['is_active' => true]);

            return;
        }

        if (! $roleName || ! $email || ! $password) {
            throw new RuntimeException('A role, email, and password are required to create this employee\'s login.');
        }

        if (User::where('email', $email)->exists()) {
            throw new RuntimeException("A user account with email \"{$email}\" already exists — choose a different login email.");
        }

        $user = User::create([
            'name' => $this->name,
            'email' => $email,
            'password' => $password,
            'is_active' => true,
            'current_site_id' => $this->site_id,
        ]);

        $user->assignRole($roleName);
        $user->sites()->attach($this->site_id, ['is_default' => true]);

        // user_id is deliberately not mass-assignable (never exposed to the
        // Employee create/update form), so it's set directly here.
        $this->user_id = $user->id;
        $this->save();
    }

    /**
     * Turns login off without ever deleting the User row — same "flag,
     * don't delete" convention used elsewhere in this app.
     */
    public function disableLogin(): void
    {
        $this->user?->update(['is_active' => false]);
    }
}
