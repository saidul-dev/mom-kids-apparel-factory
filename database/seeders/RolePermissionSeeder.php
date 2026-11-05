<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    /**
     * Canonical display order for role lists (Roles & Permissions page,
     * the Users form role picker). Not creation order — just presentation.
     */
    public const ROLE_ORDER = ['Super Admin', 'Admin', 'Manager', 'Production Supervisor', 'HR', 'Accountant', 'Employee'];

    /**
     * Base modules (README §3) and the actions available on each.
     * Permission names follow the "module.action" convention.
     */
    protected array $modules = [
        'inventory' => ['view', 'create', 'edit', 'delete', 'approve'],
        'parties'   => ['view', 'create', 'edit', 'delete'],
        'sales'     => ['view', 'create', 'edit', 'approve'],
        'accounts'  => ['view', 'create', 'edit', 'delete', 'approve'],
        'projects'  => ['view', 'create', 'edit', 'delete'],
        'tasks'     => ['view', 'create', 'edit', 'delete'],
        'hrm'       => ['view', 'create', 'edit', 'delete', 'approve'],
        // Employee records only (profile, documents) — narrower than hrm.*,
        // which also covers salary, payroll, piece rates and login access.
        'employees' => ['view', 'create', 'edit'],
        // Production lots and stage entries; approve = complete a lot.
        'production' => ['view', 'create', 'approve'],
        'reports'   => ['view'],
        'website'   => ['view', 'delete'],
        'settings'  => ['view', 'edit'],
        'users'     => ['view', 'create', 'edit', 'delete'],
        'roles'     => ['view', 'create', 'edit', 'delete'],
        'sites'     => ['view', 'create', 'edit', 'delete'],
    ];

    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach ($this->modules as $module => $actions) {
            foreach ($actions as $action) {
                Permission::findOrCreate("$module.$action");
            }
        }

        // Super Admin gets everything through Gate::before (see AppServiceProvider)
        Role::findOrCreate('Super Admin');

        // Admin = company owner, full access to every site and every module.
        // A user becomes a given Site's manager simply by holding the
        // "Manager" role and being assigned to that Site — no separate
        // "Site Manager" role is needed.
        Role::findOrCreate('Admin')
            ->syncPermissions(Permission::all());

        Role::findOrCreate('Manager')->syncPermissions([
            'inventory.view', 'inventory.create', 'inventory.edit', 'inventory.approve',
            'production.view', 'production.create', 'production.approve',
            'parties.view', 'parties.create', 'parties.edit',
            'sales.view', 'sales.create', 'sales.edit', 'sales.approve',
            'projects.view', 'projects.create', 'projects.edit',
            'tasks.view', 'tasks.create', 'tasks.edit',
            // "Manager (own team only)" — view/approve their direct reports'
            // attendance & leave (Employee::visibleTo scopes this), but not
            // full HR record management (no hrm.create/edit/delete).
            'hrm.view', 'hrm.approve',
            'reports.view',
        ]);

        // Factory floor: records production (cutting, stage entries, who did
        // the pieces) and sees their own employee profile (My Profile needs
        // no permission). No employee list, salary/payroll/piece rates,
        // purchases or stock, and can't complete a lot (that writes off
        // wastage).
        Role::findOrCreate('Production Supervisor')->syncPermissions([
            'production.view', 'production.create',
        ]);

        Role::findOrCreate('HR')->syncPermissions([
            'hrm.view', 'hrm.create', 'hrm.edit', 'hrm.approve',
            'employees.view', 'employees.create', 'employees.edit',
            'projects.view', 'projects.create', 'projects.edit',
            'tasks.view', 'tasks.create', 'tasks.edit',
            'parties.view',
            'reports.view',
        ]);

        Role::findOrCreate('Accountant')->syncPermissions([
            'accounts.view', 'accounts.create', 'accounts.edit', 'accounts.approve',
            'parties.view',
            // "Accountant (payroll approval)" — reviews and approves salary
            // runs before they post to Accounts, but doesn't manage HR records.
            'hrm.view', 'hrm.approve',
            'reports.view',
        ]);

        // Auto-provisioned on Employee::enableLogin() — self-service, view-only
        // for now; full ESS scoping (own payslip/leave/attendance) is Level 2.
        Role::findOrCreate('Employee')->syncPermissions([
            'hrm.view',
        ]);
    }
}
