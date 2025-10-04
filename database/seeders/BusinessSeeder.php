<?php

namespace Database\Seeders;

use App\Models\Business;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class BusinessSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $businesses = [
            ['name' => 'Joes Flooring', 'type' => 'Flooring'],
            ['name' => 'Joes Carpet Cleaning', 'type' => 'Cleaning'],
            ['name' => 'Joes Mowing', 'type' => 'Services'],
            ['name' => 'Joes Consulting', 'type' => 'Services'],
        ];

        foreach ($businesses as $business) {
            Business::create($business);
        }
    }
}
