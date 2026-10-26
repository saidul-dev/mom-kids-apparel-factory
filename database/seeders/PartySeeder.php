<?php

namespace Database\Seeders;

use App\Models\Party;
use Illuminate\Database\Seeder;

class PartySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Party::firstOrCreate(
            ['phone' => '01700000001'],
            [
                'name' => 'Walk-in Customer',
                'is_customer' => true,
                'is_supplier' => false,
                'status' => true,
            ]
        );

        Party::firstOrCreate(
            ['phone' => '01700000002'],
            [
                'name' => 'Fabric Supplier',
                'is_customer' => false,
                'is_supplier' => true,
                'status' => true,
            ]
        );
    }
}
