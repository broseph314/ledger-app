<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class EntitySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $entities = [
            ['name' => 'Joes Flooring Moonta', 'type' => 'Flooring', 'business_id' => 1, 'location' => 'Moonta'],
            ['name' => 'Joes Flooring Plympton', 'type' => 'Flooring', 'business_id' => 1, 'location' => 'Adelaide'],
            ['name' => 'Joes Carpet Cleaning Mile End', 'type' => 'Cleaning', 'business_id' => 2, 'location' => 'Adelaide'],
            ['name' => 'Joes Mowing Pt Lincoln', 'type' => 'Services', 'business_id' => 3,'location' => 'Port Lincoln'],
            ['name' => 'Joes Consulting Adelaide', 'type' => 'Services', 'business_id' => 4, 'location' => 'Adelaide'],
        ];
        foreach ($entities as $entity) {
            \App\Models\Entity::create($entity);
        }
    }
}
