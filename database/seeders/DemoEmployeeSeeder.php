<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\Site;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoEmployeeSeeder extends Seeder
{
    /**
     * A piece-rate sewing worker at the Factory with her own login (Employee
     * role) — the demo account for the self-service side. Safe to re-run
     * (`php artisan db:seed --class=DemoEmployeeSeeder`).
     */
    public function run(): void
    {
        $factory = Site::where('type', 'Factory')->orderBy('id')->first();

        if (! $factory) {
            return;
        }

        $employee = Employee::firstOrCreate(
            ['email' => 'employee@businesserp.test'],
            [
                'site_id' => $factory->id,
                'name' => 'Salma Akter',
                'phone' => '01700000099',
                'joining_date' => '2026-01-01',
                'employment_type' => 'permanent',
                'employment_status' => 'active',
            ]
        );

        $employee->salaryStructure()->firstOrCreate([], ['mode' => 'piece_rate', 'effective_from' => '2026-01-01']);

        if (! $employee->user_id) {
            $employee->enableLogin('Employee', 'employee@businesserp.test', 'password');
        }

        $this->linkSupervisor($factory);
    }

    /**
     * The seeded supervisor login gets an employee record of its own (on a
     * monthly salary), so My Profile has something to show.
     */
    protected function linkSupervisor(Site $factory): void
    {
        $user = User::where('email', 'supervisor@businesserp.test')->first();

        if (! $user || $user->employee) {
            return;
        }

        $supervisor = Employee::firstOrCreate(
            ['email' => 'supervisor@businesserp.test'],
            [
                'site_id' => $factory->id,
                'name' => 'Jamal Uddin',
                'phone' => '01700000097',
                'joining_date' => '2026-01-01',
                'employment_type' => 'permanent',
                'employment_status' => 'active',
            ]
        );
        $supervisor->salaryStructure()->firstOrCreate([], ['mode' => 'flat', 'flat_amount' => 25000, 'effective_from' => '2026-01-01']);

        // user_id isn't mass-assignable (see Employee::enableLogin()).
        $supervisor->user_id = $user->id;
        $supervisor->save();
    }
}
