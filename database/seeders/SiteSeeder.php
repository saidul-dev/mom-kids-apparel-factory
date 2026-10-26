<?php

namespace Database\Seeders;

use App\Models\Site;
use App\Models\User;
use Illuminate\Database\Seeder;

class SiteSeeder extends Seeder
{
    /**
     * The business runs from exactly two Sites: the Factory (purchase,
     * production, direct customer orders) and the Wholesale Point
     * (finished goods transferred in from the Factory and sold there).
     */
    public function run(): void
    {
        $factory = Site::firstOrCreate(
            ['code' => 'FAC-01'],
            [
                'name' => 'Factory',
                'type' => 'Factory',
                'address' => 'Dhaka, Bangladesh',
                'status' => true,
            ]
        );

        $wholesale = Site::firstOrCreate(
            ['code' => 'WP-01'],
            [
                'name' => 'Wholesale Point',
                'type' => 'Outlet',
                'address' => 'Dhaka, Bangladesh',
                'status' => true,
            ]
        );

        $manager = User::where('email', 'manager@businesserp.test')->first();

        if ($manager) {
            $manager->sites()->syncWithoutDetaching([
                $factory->id => ['is_default' => true],
                $wholesale->id => ['is_default' => false],
            ]);

            $manager->update(['current_site_id' => $factory->id]);
        }
    }
}
