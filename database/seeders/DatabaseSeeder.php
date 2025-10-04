<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // super barebones script to setup some test data
        $seeders = [
            BusinessSeeder::class,
            EntitySeeder::class,
            LedgerSeeder::class,
            TransactionSeeder::class,
        ];
        foreach ($seeders as $seeder) {
            $this->call($seeder);
        }
    }
}
