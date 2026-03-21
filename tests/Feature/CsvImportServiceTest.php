<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use App\Services\AiCategorizationService;
use App\Services\CategoryRuleService;
use App\Services\CsvImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CsvImportServiceTest extends TestCase
{
    use RefreshDatabase;

    private CsvImportService $service;

    private Account $account;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());

        $this->account = Account::factory()->create();

        // Prevent real AI and rule calls during import
        $this->mock(CategoryRuleService::class)
            ->shouldReceive('applyToTransaction')
            ->andReturn(false);

        $this->mock(AiCategorizationService::class)
            ->shouldReceive('categorizeOne');

        $this->service = app(CsvImportService::class);
    }

    protected function tearDown(): void
    {
        Storage::disk('local')->deleteDirectory('test-csvs');
        parent::tearDown();
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Format detection
    // ─────────────────────────────────────────────────────────────────────────

    public function test_detects_rabobank_format(): void
    {
        $path = $this->writeTempCsv("Datum,Naam tegenpartij,Omschrijving-1,Bedrag\n2026-01-01,AH,Groceries,\"-12,50\"");

        $result = $this->service->import($path, $this->account->id, 'generic');

        $this->assertSame(1, $result['imported']);
    }

    public function test_detects_revolut_personal_format(): void
    {
        $path = $this->writeTempCsv(
            "Type,Product,Started Date,Completed Date,Description,Amount,Fee,Currency,State,Balance\n" .
            "CARD_PAYMENT,Current,2026-01-01 10:00:00,2026-01-01 10:00:00,Coffee Shop,-5.50,0.00,EUR,COMPLETED,994.50"
        );

        $result = $this->service->import($path, $this->account->id, 'generic');

        $this->assertSame(1, $result['imported']);
    }

    public function test_detects_revolut_investment_format(): void
    {
        $path = $this->writeTempCsv(
            "Transactions for Flexible Cash - EUR\n" .
            "Date,Description,Value\n" .
            "2026-01-01,DIVIDEND,50.00"
        );

        $result = $this->service->import($path, $this->account->id, 'generic');

        $this->assertSame(1, $result['imported']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Generic format
    // ─────────────────────────────────────────────────────────────────────────

    public function test_generic_imports_expense_and_income(): void
    {
        $path = $this->writeTempCsv(
            "date,description,amount\n" .
            "2026-01-01,Salary,2000.00\n" .
            "2026-01-02,Coffee,-5.50"
        );

        $result = $this->service->import($path, $this->account->id, 'generic');

        $this->assertSame(2, $result['imported']);
        $this->assertSame(0, $result['skipped']);
        $this->assertSame(0, $result['duplicates']);

        $this->assertDatabaseHas('transactions', [
            'account_id'  => $this->account->id,
            'description' => 'Salary',
            'amount'      => 200000,
            'type'        => 'income',
            'is_reviewed' => false,
        ]);

        $this->assertDatabaseHas('transactions', [
            'account_id'  => $this->account->id,
            'description' => 'Coffee',
            'amount'      => 550,
            'type'        => 'expense',
            'is_reviewed' => false,
        ]);
    }

    public function test_generic_skips_rows_with_missing_data(): void
    {
        $path = $this->writeTempCsv(
            "date,description,amount\n" .
            ",Missing date,10.00\n" .
            "2026-01-01,,10.00\n" .
            "2026-01-01,Missing amount,"
        );

        $result = $this->service->import($path, $this->account->id, 'generic');

        $this->assertSame(0, $result['imported']);
        $this->assertSame(3, $result['skipped']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Rabobank format
    // ─────────────────────────────────────────────────────────────────────────

    public function test_rabobank_imports_with_european_amounts(): void
    {
        $path = $this->writeTempCsv(
            "Datum,Naam tegenpartij,Omschrijving-1,Bedrag\n" .
            "2026-01-05,Albert Heijn,Groceries,\"-45,30\"\n" .
            "2026-01-06,Employer AG,Salary,\"+3.000,00\""
        );

        $result = $this->service->import($path, $this->account->id, 'rabobank');

        $this->assertSame(2, $result['imported']);

        $this->assertDatabaseHas('transactions', [
            'description' => 'Albert Heijn',
            'amount'      => 4530,
            'type'        => 'expense',
        ]);

        $this->assertDatabaseHas('transactions', [
            'description' => 'Employer AG',
            'amount'      => 300000,
            'type'        => 'income',
        ]);
    }

    public function test_rabobank_falls_back_to_omschrijving_when_counterparty_empty(): void
    {
        $path = $this->writeTempCsv(
            "Datum,Naam tegenpartij,Omschrijving-1,Bedrag\n" .
            "2026-01-05,,ATM Withdrawal,\"-200,00\""
        );

        $result = $this->service->import($path, $this->account->id, 'rabobank');

        $this->assertSame(1, $result['imported']);
        $this->assertDatabaseHas('transactions', ['description' => 'ATM Withdrawal']);
    }

    public function test_rabobank_skips_rows_with_missing_data(): void
    {
        $path = $this->writeTempCsv(
            "Datum,Naam tegenpartij,Omschrijving-1,Bedrag\n" .
            ",,No name or date,\"-5,00\""
        );

        $result = $this->service->import($path, $this->account->id, 'rabobank');

        $this->assertSame(0, $result['imported']);
        $this->assertSame(1, $result['skipped']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Revolut Personal format
    // ─────────────────────────────────────────────────────────────────────────

    public function test_revolut_personal_imports_completed_transactions(): void
    {
        $path = $this->writeTempCsv(
            "Type,Product,Started Date,Completed Date,Description,Amount,Fee,Currency,State,Balance\n" .
            "CARD_PAYMENT,Current,2026-01-10 09:00:00,2026-01-10 09:30:00,Starbucks,-4.50,0.00,EUR,COMPLETED,500.00\n" .
            "TOPUP,Current,2026-01-11 08:00:00,2026-01-11 08:00:00,Top-Up by *1234,1000.00,0.00,EUR,COMPLETED,1500.00"
        );

        $result = $this->service->import($path, $this->account->id, 'revolut_personal');

        $this->assertSame(2, $result['imported']);

        $this->assertDatabaseHas('transactions', [
            'description' => 'Starbucks',
            'amount'      => 450,
            'type'        => 'expense',
            'is_reviewed' => false,
        ]);

        $this->assertDatabaseHas('transactions', [
            'description' => 'Top-Up by *1234',
            'amount'      => 100000,
            'type'        => 'income',
        ]);
    }

    public function test_revolut_personal_skips_non_completed_transactions(): void
    {
        $path = $this->writeTempCsv(
            "Type,Product,Started Date,Completed Date,Description,Amount,Fee,Currency,State,Balance\n" .
            "CARD_PAYMENT,Current,2026-01-10 09:00:00,2026-01-10 09:00:00,Pending Shop,-10.00,0.00,EUR,PENDING,490.00\n" .
            "CARD_PAYMENT,Current,2026-01-10 10:00:00,2026-01-10 10:00:00,Declined Shop,-10.00,0.00,EUR,DECLINED,490.00\n" .
            "CARD_PAYMENT,Current,2026-01-10 11:00:00,2026-01-10 11:00:00,Real Coffee,-3.00,0.00,EUR,COMPLETED,487.00"
        );

        $result = $this->service->import($path, $this->account->id, 'revolut_personal');

        $this->assertSame(1, $result['imported']);
        $this->assertSame(2, $result['skipped']);
        $this->assertDatabaseHas('transactions', ['description' => 'Real Coffee']);
    }

    public function test_revolut_personal_imports_fee_as_separate_transaction(): void
    {
        $path = $this->writeTempCsv(
            "Type,Product,Started Date,Completed Date,Description,Amount,Fee,Currency,State,Balance\n" .
            "EXCHANGE,Current,2026-01-12 10:00:00,2026-01-12 10:00:00,USD Exchange,-100.00,0.50,EUR,COMPLETED,399.50"
        );

        $result = $this->service->import($path, $this->account->id, 'revolut_personal');

        // Main transaction + fee transaction
        $this->assertSame(2, $result['imported']);

        $this->assertDatabaseHas('transactions', [
            'description' => 'USD Exchange',
            'amount'      => 10000,
            'type'        => 'expense',
        ]);

        $this->assertDatabaseHas('transactions', [
            'description' => 'Revolut Fee',
            'amount'      => 50,
            'type'        => 'expense',
        ]);
    }

    public function test_revolut_personal_zero_fee_does_not_create_extra_transaction(): void
    {
        $path = $this->writeTempCsv(
            "Type,Product,Started Date,Completed Date,Description,Amount,Fee,Currency,State,Balance\n" .
            "CARD_PAYMENT,Current,2026-01-10 09:00:00,2026-01-10 09:00:00,Coffee,-3.00,0.00,EUR,COMPLETED,497.00"
        );

        $result = $this->service->import($path, $this->account->id, 'revolut_personal');

        $this->assertSame(1, $result['imported']);
        $this->assertDatabaseMissing('transactions', ['description' => 'Revolut Fee']);
    }

    public function test_revolut_personal_uses_completed_date_not_started_date(): void
    {
        $path = $this->writeTempCsv(
            "Type,Product,Started Date,Completed Date,Description,Amount,Fee,Currency,State,Balance\n" .
            "CARD_PAYMENT,Current,2026-01-09 23:00:00,2026-01-10 08:30:00,Late Night Purchase,-20.00,0.00,EUR,COMPLETED,480.00"
        );

        $this->service->import($path, $this->account->id, 'revolut_personal');

        $transaction = Transaction::where('description', 'Late Night Purchase')->first();
        $this->assertNotNull($transaction);
        $this->assertSame('2026-01-10', $transaction->date->format('Y-m-d'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Duplicate detection
    // ─────────────────────────────────────────────────────────────────────────

    public function test_duplicate_transaction_is_skipped_on_reimport(): void
    {
        $csv = "date,description,amount\n2026-01-15,Netflix,-15.99";
        $path = $this->writeTempCsv($csv);

        $first = $this->service->import($path, $this->account->id, 'generic');
        $this->assertSame(1, $first['imported']);
        $this->assertSame(0, $first['duplicates']);

        // Re-import the same file
        $path2 = $this->writeTempCsv($csv);
        $second = $this->service->import($path2, $this->account->id, 'generic');
        $this->assertSame(0, $second['imported']);
        $this->assertSame(1, $second['duplicates']);

        // Only one transaction should exist
        $this->assertSame(1, Transaction::where('description', 'Netflix')->count());
    }

    public function test_same_description_different_amount_is_not_a_duplicate(): void
    {
        $path1 = $this->writeTempCsv("date,description,amount\n2026-01-15,Netflix,-15.99");
        $path2 = $this->writeTempCsv("date,description,amount\n2026-01-15,Netflix,-19.99");

        $this->service->import($path1, $this->account->id, 'generic');
        $result = $this->service->import($path2, $this->account->id, 'generic');

        $this->assertSame(1, $result['imported']);
        $this->assertSame(0, $result['duplicates']);
        $this->assertSame(2, Transaction::where('description', 'Netflix')->count());
    }

    public function test_same_amount_different_date_is_not_a_duplicate(): void
    {
        $path1 = $this->writeTempCsv("date,description,amount\n2026-01-15,Netflix,-15.99");
        $path2 = $this->writeTempCsv("date,description,amount\n2026-02-15,Netflix,-15.99");

        $this->service->import($path1, $this->account->id, 'generic');
        $result = $this->service->import($path2, $this->account->id, 'generic');

        $this->assertSame(1, $result['imported']);
        $this->assertSame(0, $result['duplicates']);
    }

    public function test_duplicate_check_is_scoped_to_account(): void
    {
        $otherAccount = Account::factory()->create();
        $csv = "date,description,amount\n2026-01-15,Netflix,-15.99";

        $path1 = $this->writeTempCsv($csv);
        $path2 = $this->writeTempCsv($csv);

        $this->service->import($path1, $this->account->id, 'generic');
        $result = $this->service->import($path2, $otherAccount->id, 'generic');

        // Same transaction on a different account is not a duplicate
        $this->assertSame(1, $result['imported']);
        $this->assertSame(0, $result['duplicates']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Shared helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Write CSV content to a temp file and return its absolute path.
     */
    private function writeTempCsv(string $content): string
    {
        $filename = 'test-csvs/' . uniqid('csv_', true) . '.csv';
        Storage::disk('local')->put($filename, $content);

        return Storage::disk('local')->path($filename);
    }
}
