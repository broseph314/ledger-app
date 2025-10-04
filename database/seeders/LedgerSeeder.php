<?php

namespace Database\Seeders;

use App\Models\Ledger;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class LedgerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {

        $entities = \App\Models\Entity::all();

        foreach($entities as $entity) {
            $balance1 = random_int(10000, 25000);
            $balance2 = random_int(10000, 25000);
            $balance3 = random_int(10000, 25000);
            $ledgers = [
                ['name' => $entity->name.' Revenue', 'entity_id' => $entity->id, 'type' => 'revenue', 'starting_balance' => $balance1, 'current_balance' => $balance1, 'current_as_of' => now()],
                ['name' => $entity->name.' Services', 'entity_id' => $entity->id, 'type' => 'services', 'starting_balance' => $balance2, 'current_balance' => $balance2, 'current_as_of' => now()],
                ['name' => $entity->name.' Payroll', 'entity_id' => $entity->id, 'type' => 'payroll','starting_balance' => $balance3, 'current_balance' => $balance3, 'current_as_of' => now()],
            ];
            foreach ($ledgers as $ledger) {
                Ledger::create($ledger);
            }
        }

    }
}
