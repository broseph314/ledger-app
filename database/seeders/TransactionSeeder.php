<?php

namespace Database\Seeders;

use App\Models\Ledger;
use App\Models\Recurring;
use App\Models\Transaction;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Faker\Factory as Faker;

class TransactionSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create();
        $faker->seed(424242);
        $now = Carbon::now();

        /** @var \Illuminate\Database\Eloquent\Collection<int, Ledger> $ledgers */
        $ledgers = Ledger::query()->with('entity')->get();

        foreach ($ledgers as $ledger) {
            switch (strtolower($ledger->type)) {
                case 'revenue':
                    // we'll do a couple hundred sales credits (last ~4 months)
                    $sales = random_int(150, 290);
                    for ($i = 0; $i < $sales; $i++) {
                        $date = $this->randomDateTimeInPast($now, 120, 8, 18);
                        $amount = $faker->randomFloat(2, 50, 2500); // sale size
                        $this->makeTx(
                            ledgerId: $ledger->id,
                            type: 'credit',
                            amount: $amount,
                            description: 'Sale: ' . $faker->words(2, true),
                            occurredAt: $date
                        );
                    }

                    // and 12–16 refunds (debits)
                    $refunds = random_int(2, 6);
                    for ($i = 0; $i < $refunds; $i++) {
                        $date = $this->randomDateTimeInPast($now, 120, 8, 18);
                        $amount = $faker->randomFloat(2, 20, 500);
                        $this->makeTx(
                            ledgerId: $ledger->id,
                            type: 'debit',
                            amount: $amount,
                            description: 'Refund: ' . $faker->sentence(2),
                            occurredAt: $date
                        );
                    }

                    //Some recurring transfers to other ledgers (e.g. payroll, services)
                    $transfers = random_int(3, 10);
                    $otherLedgers = $ledgers->where('id', '!=', $ledger->id)->values();
                    for ($i = 0; $i < $transfers; $i++) {
                        $date = $this->randomDateTimeInPast($now, 120, 8, 18);
                        $amount = $faker->randomFloat(2, 100, 2000);
                        $frequency = $faker->randomElement([
                            null,
                            'weekly',
                            'fortnightly',
                            'monthly',
                            'quarterly',
                            'yearly',
                        ]);
                        $end_date = now()->addMonths(random_int(1, 12));
                        $toLedger = $otherLedgers->random();
                        $this->makeTx(
                            ledgerId: $ledger->id,
                            type: 'debit',
                            amount: $amount,
                            description: 'Transfer to Ledger #' . $toLedger->id,
                            occurredAt: $date,
                            frequency: $frequency,
                            from_ledger_id: $toLedger->id,
                            end_date: $end_date
                        );
                    }

                    break;

                case 'services':
                    // 30–60 supplier/service debits
                    $count = random_int(30, 60);
                    for ($i = 0; $i < $count; $i++) {
                        $date = $this->randomDateTimeInPast($now, 120, 7, 17);
                        $amount = $faker->randomFloat(2, 40, 1200);
                        $desc = $faker->randomElement([
                            'Supplies',
                            'Fuel',
                            'Consumables',
                            'Tooling',
                            'Maintenance',
                            'Subscription',
                        ]);
                        $this->makeTx(
                            ledgerId: $ledger->id,
                            type: 'debit',
                            amount: $amount,
                            description: $desc,
                            occurredAt: $date
                        );
                    }

                    //add a few recurring service payments
                    $recurringCount = random_int(5, 30);
                    for ($i = 0; $i < $recurringCount; $i++) {
                        $date = $this->randomDateTimeInPast($now, 120, 7, 17);
                        $amount = $faker->randomFloat(2, 20, 300);
                        $end_date = now()->addMonths(random_int(1, 12));
                        $frequency = $faker->randomElement([
                            'weekly',
                            'fortnightly',
                            'monthly',
                            'quarterly',
                            'yearly',
                        ]);
                        $desc = $faker->randomElement([
                            'Subscription',
                            'Membership',
                            'Hosting',
                            'Software License',
                            'Service Fee',
                        ]);
                        $this->makeTx(
                            ledgerId: $ledger->id,
                            type: 'debit',
                            amount: $amount,
                            description: 'Recurring: ' . $desc,
                            occurredAt: $date,
                            frequency: $frequency,
                            end_date: $end_date,
                        );
                    }

                    // 1–3 small credit adjustments
                    $credits = random_int(1, 3);
                    for ($i = 0; $i < $credits; $i++) {
                        $date = $this->randomDateTimeInPast($now, 120, 7, 17);
                        $amount = $faker->randomFloat(2, 10, 200);
                        $this->makeTx(
                            ledgerId: $ledger->id,
                            type: 'credit',
                            amount: $amount,
                            description: 'Adjustment',
                            occurredAt: $date
                        );
                    }
                    break;

                case 'payroll':
                    // Fortnightly payroll debits (last ~14 weeks)
                    $start = $now->copy()->startOfWeek()->subWeeks(14); // roughly 7 fortnights
                    $cursor = $start;
                    while ($cursor->lte($now)) {
                        $amount = $faker->randomFloat(2, 1500, 8000); // depends on entity size
                        $this->makeTx(
                            ledgerId: $ledger->id,
                            type: 'debit',
                            amount: $amount,
                            description: 'Payroll',
                            occurredAt: $cursor->copy()->setTime(9, random_int(0, 59))
                        );
                        $cursor->addWeeks(2);
                    }

                    // Occasional PAYG or super credit adjustment (refund/rebate)
                    if (random_int(0, 1) === 1) {
                        $date = $this->randomDateTimeInPast($now, 60, 9, 16);
                        $amount = $faker->randomFloat(2, 100, 500);
                        $this->makeTx(
                            ledgerId: $ledger->id,
                            type: 'credit',
                            amount: $amount,
                            description: 'Payroll Adjustment',
                            occurredAt: $date
                        );
                    }
                    break;

                default:
                    // Generic behavior for other ledger types (if any)
                    $count = random_int(10, 20);
                    for ($i = 0; $i < $count; $i++) {
                        $date = $this->randomDateTimeInPast($now, 90, 8, 18);
                        $isDebit = (bool) random_int(0, 1);
                        $amount = $faker->randomFloat(2, 25, 600);
                        $this->makeTx(
                            ledgerId: $ledger->id,
                            type: $isDebit ? 'debit' : 'credit',
                            amount: $amount,
                            description: $faker->sentence(2),
                            occurredAt: $date
                        );
                    }
                    break;
            }
        }
    }

    private function makeTx(
        int $ledgerId,
        string $type,
        float $amount,
        string $description,
        Carbon $occurredAt,
        ?string $frequency = null,
        ?int $from_ledger_id = null,
        ?string $end_date = null
    ): void {

        $recurring = null;

        if($frequency !== null){
            if(strtolower($type) === 'debit'){
                $recurring = $this->createRecurringExpense([
                    'ledger_id'   => $ledgerId,
                    'description' => $description,
                    'amount'      => $amount,
                    'frequency'  => $frequency,
                    'date'      => $occurredAt,
                    'end_date'  => $end_date,
                ]);
            } else {
                $recurring = $this->createRecurringIncome([
                    'ledger_id'   => $ledgerId,
                    'description' => $description,
                    'amount'      => $amount,
                    'frequency'  => $frequency,
                    'date'      => $occurredAt,
                    'end_date'  => $end_date,
                ]);
            }
        }

        if($from_ledger_id !== null){
            $description = $description . " (Transfer from Ledger #$from_ledger_id)";

            // also create an expense on the from_ledger_id
            $this->makeTx(
                ledgerId: $from_ledger_id,
                type: 'debit',
                amount: $amount,
                description: "Transfer to Ledger #$ledgerId",
                occurredAt: $occurredAt,
                frequency: $frequency,
                end_date: $end_date
            );
        }

        Transaction::create([
            'ledger_id'   => $ledgerId,
            'type'        => strtolower($type),
            'amount'      => strtolower($type) === 'debit'
                ? -abs($amount)
                : abs($amount),
            'occurred_at' => $occurredAt,
            'description' => $description,
            'recurring_id' => $recurring?->id ?? null,
        ]);
    }

    private function createRecurringExpense(array $data): Recurring
    {
        $firstAt = Carbon::parse($data['date'] ?? now());
        $nextAt  = $this->nextFromFrequency($firstAt, $data['frequency']);

        return Recurring::create([
            'ledger_id'         => $data['ledger_id'],
            'description'       => $data['description'] ?? 'Expense',
            'amount'            => -1 * abs($data['amount']),
            'type'              => 'debit',
            'frequency'         => $data['frequency'],
            'end_date'          => $data['end_date'] ?? null,
            'start_date'        => $firstAt,
            'last_payment_date' => $firstAt,
            'next_payment_date' => $nextAt,
        ]);
    }

    private function createRecurringIncome(array $data): Recurring
    {
        $firstAt = Carbon::parse($v['date'] ?? now());
        $nextAt  = $this->nextFromFrequency($firstAt, $data['frequency']);

        return Recurring::create([
            'ledger_id'         => $data['ledger_id'],
            'description'       => $data['description'] ?? 'Expense',
            'amount'            => -1 * abs($data['amount']),
            'type'              => 'credit',
            'frequency'         => $data['frequency'],
            'end_date'          => $data['end_date'] ?? null,
            'start_date'        => $firstAt,
            'last_payment_date' => $firstAt,
            'next_payment_date' => $nextAt,
        ]);
    }

    private function nextFromFrequency(Carbon $from, string $frequency): Carbon
    {
        return match (strtolower($frequency)) {
            'daily'       => $from->copy()->addDay(),
            'weekly'      => $from->copy()->addWeek(),
            'fortnightly' => $from->copy()->addWeeks(2),
            'monthly'     => $from->copy()->addMonthNoOverflow(),
            'quarterly'   => $from->copy()->addMonthsNoOverflow(3),
            'yearly', 'annually' => $from->copy()->addYearNoOverflow(),
            default       => $from->copy()->addMonthNoOverflow(),
        };
    }

    private function randomDateTimeInPast(
        Carbon $now,
        int $maxDaysBack = 120,
        int $hourStart = 8,
        int $hourEnd = 18
    ): Carbon {
        return $now->copy()
            ->subDays(random_int(0, $maxDaysBack))
            ->setTime(random_int($hourStart, $hourEnd), random_int(0, 59), random_int(0, 59));
    }
}
