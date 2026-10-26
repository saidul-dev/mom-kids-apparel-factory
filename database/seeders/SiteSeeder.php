<?php

namespace Database\Seeders;

use App\Models\Site;
use App\Models\User;
use Illuminate\Database\Seeder;

class SiteSeeder extends Seeder
{
    /**
     * The business runs from exactly two Sites: the Factory & Central
     * Warehouse (purchase, production, finished stock, direct customer
     * orders) and the Wholesale Outlet (finished goods transferred in from
     * the Factory and sold there).
     */
    public function run(): void
    {
        $factory = Site::firstOrCreate(
            ['code' => 'FAC-01'],
            [
                'name' => 'Factory & Central Warehouse',
                'type' => 'Factory',
                'address' => 'Dhaka, Bangladesh',
                'status' => true,
            ]
        );

        $wholesale = Site::firstOrCreate(
            ['code' => 'WO-01'],
            [
                'name' => 'Wholesale Outlet',
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
