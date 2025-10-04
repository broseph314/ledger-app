<?php

namespace Tests\Feature;

use App\Models\Ledger;
use App\Models\Recurring;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class StoreExpenseTest extends TestCase
{
    use RefreshDatabase;
    protected bool $seed = true;

    protected string $seeder = \Database\Seeders\DatabaseSeeder::class;

    /** @test */
    public function it_stores_a_standard_expense_using_seeded_ledger(): void
    {
        Carbon::setTestNow('2025-01-15 09:00:00');

        $ledger = Ledger::where('type', 'services')->firstOrFail();

        $payload = [
            'ledger_id'   => $ledger->id,
            'amount'      => 123.45,
            'date'        => '2025-01-14',
            'description' => 'Coffee',
        ];

        $res = $this->postJson('/api/expense', $payload);

        $res->assertCreated()
            ->assertJsonPath('message', 'Expense recorded.')
            ->assertJsonPath('transaction.ledger_id', $ledger->id)
            ->assertJsonPath('transaction.type', 'debit');

        // DB assertions
        $this->assertDatabaseHas('transactions', [
            'ledger_id' => $ledger->id,
            'type'      => 'debit',
            'description' => 'Coffee',
        ]);

        $txn = Transaction::latest('id')->first();
        $this->assertEquals(-123.45, (float) $txn->amount);
        $this->assertTrue(Carbon::parse($txn->occurred_at)->isSameDay('2025-01-14'));
    }

    /** @test */
    public function it_stores_a_recurring_expense_and_creates_a_schedule(): void
    {
        Carbon::setTestNow('2025-01-15 10:00:00');

        $ledger = Ledger::where('type', 'payroll')->firstOrFail();

        $payload = [
            'ledger_id'   => $ledger->id,
            'amount'      => 200,
            'description' => 'Rent',
            'frequency'   => 'monthly',     // triggers recurring schedule
            'date'        => '2025-01-31',  // first occurrence date
            'end_date'    => '2025-12-31',
        ];

        $res = $this->postJson('/api/expense', $payload);

        $res->assertCreated()
            ->assertJsonPath('message', 'Expense recorded.')
            ->assertJsonPath('transaction.ledger_id', $ledger->id)
            ->assertJsonPath('transaction.type', 'debit');

        // First transaction persisted and negative
        $this->assertDatabaseHas('transactions', [
            'ledger_id'   => $ledger->id,
            'type'        => 'debit',
            'description' => 'Rent',
        ]);

        $txn = Transaction::latest('id')->first();
        $this->assertEquals(-200.0, (float) $txn->amount);
        $this->assertTrue(Carbon::parse($txn->occurred_at)->isSameDay('2025-01-31'));

        // Recurring schedule created by controller helper
        $rec = Recurring::where('ledger_id', $ledger->id)
            ->where('description', 'Rent')
            ->first();

        $this->assertNotNull($rec, 'Recurring row was not created');
        $this->assertEquals(-200.0, (float) $rec->amount);
        $this->assertEquals('debit', $rec->type);
        $this->assertEquals('monthly', strtolower($rec->frequency));
        $this->assertEquals('2025-12-31', Carbon::parse($rec->end_date)->toDateString());
        $this->assertTrue(Carbon::parse($rec->last_payment_date)->isSameDay('2025-01-31'));

        // Monthly from Jan 31 should no-overflow to Feb 28, 2025
        $expectedNext = Carbon::parse('2025-02-28')->toDateString();
        if ($rec->next_payment_date) {
            $this->assertTrue(Carbon::parse($rec->next_payment_date)->isSameDay($expectedNext));
        }
    }
}
