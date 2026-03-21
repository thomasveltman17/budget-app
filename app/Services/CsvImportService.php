<?php

namespace App\Services;

use App\Models\Transaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use League\Csv\Reader;

class CsvImportService
{
    // ──────────────────────────────────────────────────────────────────────────
    //  Public entry point
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Parse and import a CSV file as Transaction records.
     *
     * Supported formats:
     *  - 'generic'          — expects columns: date, description, amount
     *  - 'rabobank'         — Dutch Rabobank export (Datum / Naam tegenpartij / Bedrag)
     *  - 'revolut'          — Revolut multi-section investment / bank statement
     *  - 'revolut_personal' — Revolut Personal account transaction export
     *
     * @return array{imported: int, skipped: int, duplicates: int}
     */
    public function import(string $absolutePath, int $accountId, string $format = 'generic'): array
    {
        $detected = $this->detectFormat($absolutePath);

        if ($detected !== $format) {
            Log::info("CsvImportService: auto-detected format '{$detected}', overriding '{$format}'");
            $format = $detected;
        }

        return match ($format) {
            'rabobank'         => $this->importRabobank($absolutePath, $accountId),
            'revolut'          => $this->importRevolut($absolutePath, $accountId),
            'revolut_personal' => $this->importRevolutPersonal($absolutePath, $accountId),
            default            => $this->importGeneric($absolutePath, $accountId),
        };
    }

    /**
     * Detect the CSV format by inspecting the file's first line.
     */
    protected function detectFormat(string $path): string
    {
        $handle = fopen($path, 'r');
        $firstLine = fgets($handle) ?: '';
        fclose($handle);

        $firstLine = trim($firstLine);

        // Revolut investment: multi-section file whose first line starts with "Transactions for"
        if (str_starts_with($firstLine, 'Transactions for ')) {
            return 'revolut';
        }

        $headers = array_map(
            fn (string $h) => strtolower(trim(trim($h, '"'))),
            str_getcsv($firstLine)
        );

        // Revolut Personal: header row contains both 'started date' and 'completed date'
        if (in_array('started date', $headers, true) && in_array('completed date', $headers, true)) {
            return 'revolut_personal';
        }

        // Rabobank: has both 'datum' and 'bedrag' as column headers
        if (in_array('datum', $headers, true) && in_array('bedrag', $headers, true)) {
            return 'rabobank';
        }

        return 'generic';
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  Format-specific parsers
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Generic parser — expects headers: date, description, amount.
     *
     * @return array{imported: int, skipped: int, duplicates: int}
     */
    protected function importGeneric(string $path, int $accountId): array
    {
        $reader = Reader::createFromPath($path, 'r');
        $reader->setHeaderOffset(0);

        $imported   = 0;
        $skipped    = 0;
        $duplicates = 0;

        foreach ($reader->getRecords() as $offset => $record) {
            $record = array_change_key_case($record, CASE_LOWER);

            $date        = $this->parseDate($record['date'] ?? null);
            $description = isset($record['description']) ? trim($record['description']) : null;
            $rawAmount   = $this->parseAmount($record['amount'] ?? null);

            if ($date === null || ! $description || $rawAmount === null) {
                Log::warning('CsvImportService[generic]: skipping row ' . $offset, ['record' => $record]);
                $skipped++;
                continue;
            }

            if ($this->createTransaction($accountId, $date, $description, $rawAmount)) {
                $imported++;
            } else {
                $duplicates++;
            }
        }

        return ['imported' => $imported, 'skipped' => $skipped, 'duplicates' => $duplicates];
    }

    /**
     * Rabobank parser — Dutch column names, European amount format.
     *
     * Columns used:
     *  - Datum             → date  (Y-m-d)
     *  - Naam tegenpartij  → description (primary)
     *  - Omschrijving-1    → description fallback when counterparty is empty
     *  - Bedrag            → amount  (-2,70 / +500,00)
     *
     * @return array{imported: int, skipped: int, duplicates: int}
     */
    protected function importRabobank(string $path, int $accountId): array
    {
        $reader = Reader::createFromPath($path, 'r');
        $reader->setHeaderOffset(0);

        $imported   = 0;
        $skipped    = 0;
        $duplicates = 0;

        foreach ($reader->getRecords() as $offset => $record) {
            $record = array_change_key_case($record, CASE_LOWER);

            $date = $this->parseDate($record['datum'] ?? null);

            // Prefer the counterparty name; fall back to the first description field
            $description = trim($record['naam tegenpartij'] ?? '');
            if ($description === '') {
                $description = trim($record['omschrijving-1'] ?? '');
            }

            $rawAmount = $this->parseAmount($record['bedrag'] ?? null);

            if ($date === null || $description === '' || $rawAmount === null) {
                Log::warning('CsvImportService[rabobank]: skipping row ' . $offset, ['record' => $record]);
                $skipped++;
                continue;
            }

            if ($this->createTransaction($accountId, $date, $description, $rawAmount)) {
                $imported++;
            } else {
                $duplicates++;
            }
        }

        return ['imported' => $imported, 'skipped' => $skipped, 'duplicates' => $duplicates];
    }

    /**
     * Revolut parser — handles the multi-section annual investment statement.
     *
     * The file contains several sections, each starting with a line like:
     *   "Transactions for Flexible Cash Funds - EUR"
     *   "Transactions for Brokerage Account dividends - EUR"
     *
     * Each section has its own column header row (first cell = "Date") which may
     * be preceded by metadata lines that must be skipped.
     *
     * Amount column priority: net amount base currency → value → gross amount base currency
     *
     * @return array{imported: int, skipped: int, duplicates: int}
     */
    protected function importRevolut(string $path, int $accountId): array
    {
        $imported       = 0;
        $skipped        = 0;
        $duplicates     = 0;
        $inTransactions = false;
        $headers        = null;

        $handle = fopen($path, 'r');

        while (($raw = fgets($handle)) !== false) {
            $line = trim($raw);

            if ($line === '') {
                continue;
            }

            // Parse the line as a CSV row (handles quoted fields)
            $row = str_getcsv($line);

            // Detect a new "Transactions for …" section
            $firstCell = trim($row[0] ?? '');
            if (str_starts_with($firstCell, 'Transactions for ')) {
                $inTransactions = true;
                $headers        = null; // Reset; next header row follows
                continue;
            }

            if (! $inTransactions) {
                continue;
            }

            // Within a transaction section: first row whose first cell is "Date" is the header
            if ($headers === null) {
                if (strtolower($firstCell) === 'date') {
                    $headers = array_map(
                        fn (string $h) => strtolower(trim($h)),
                        $row
                    );
                }
                // All other lines before the header (metadata) are silently skipped
                continue;
            }

            // Data row — combine with headers
            if (count($row) !== count($headers)) {
                $skipped++;
                continue;
            }

            $record = array_combine($headers, $row);

            $date = $this->parseDate($record['date'] ?? null);

            // Description: prefer 'description', fall back to 'security name' + " dividend"
            $description = trim($record['description'] ?? '');
            if ($description === '' && isset($record['security name'])) {
                $description = trim($record['security name']);
                if ($description !== '') {
                    $description .= ' dividend';
                }
            }

            // Amount: try several column names in order of preference
            $amountRaw = $record['net amount base currency']
                ?? $record['value']
                ?? $record['gross amount base currency']
                ?? $record['gross amount']
                ?? null;

            $rawAmount = $this->parseAmount($amountRaw);

            if ($date === null || $description === '' || $rawAmount === null) {
                Log::warning('CsvImportService[revolut]: skipping row', ['record' => $record]);
                $skipped++;
                continue;
            }

            // Skip zero-amount rows (e.g. opening balance summaries)
            if ($rawAmount == 0.0) {
                $skipped++;
                continue;
            }

            if ($this->createTransaction($accountId, $date, $description, $rawAmount)) {
                $imported++;
            } else {
                $duplicates++;
            }
        }

        fclose($handle);

        return ['imported' => $imported, 'skipped' => $skipped, 'duplicates' => $duplicates];
    }

    /**
     * Revolut Personal parser — standard transaction export from the Revolut app.
     *
     * Columns used:
     *  - Completed Date → date  (Y-m-d H:i:s)
     *  - Description    → description
     *  - Amount         → amount (signed float, negative = debit)
     *  - Fee            → if non-zero, creates a separate expense transaction
     *  - State          → only COMPLETED rows are imported
     *
     * @return array{imported: int, skipped: int, duplicates: int}
     */
    protected function importRevolutPersonal(string $path, int $accountId): array
    {
        $reader = Reader::createFromPath($path, 'r');
        $reader->setHeaderOffset(0);

        $imported   = 0;
        $skipped    = 0;
        $duplicates = 0;

        foreach ($reader->getRecords() as $offset => $record) {
            $record = array_change_key_case($record, CASE_LOWER);

            // Only import fully settled transactions
            if (strtoupper(trim($record['state'] ?? '')) !== 'COMPLETED') {
                $skipped++;
                continue;
            }

            $date        = $this->parseDate($record['completed date'] ?? null);
            $description = trim($record['description'] ?? '');
            $rawAmount   = $this->parseAmount($record['amount'] ?? null);

            if ($date === null || $description === '' || $rawAmount === null) {
                Log::warning('CsvImportService[revolut_personal]: skipping row ' . $offset, ['record' => $record]);
                $skipped++;
                continue;
            }

            if ($this->createTransaction($accountId, $date, $description, $rawAmount)) {
                $imported++;
            } else {
                $duplicates++;
            }

            // Import non-zero fees as a separate expense transaction
            $feeRaw = $this->parseAmount($record['fee'] ?? null);

            if ($feeRaw !== null && $feeRaw != 0.0) {
                if ($this->createTransaction($accountId, $date, 'Revolut Fee', -abs($feeRaw))) {
                    $imported++;
                } else {
                    $duplicates++;
                }
            }
        }

        return ['imported' => $imported, 'skipped' => $skipped, 'duplicates' => $duplicates];
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  Shared helpers
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Insert a single Transaction record, skipping duplicates.
     *
     * A duplicate is an existing (non-deleted) transaction on the same account
     * with the same date, amount, type, and description.
     * rawAmount is a signed float in the account currency (negative = expense).
     *
     * Returns true when a transaction was created, false when skipped as duplicate.
     */
    protected function createTransaction(
        int    $accountId,
        Carbon $date,
        string $description,
        float  $rawAmount,
    ): bool {
        $type        = $rawAmount < 0 ? 'expense' : 'income';
        $amountCents = (int) round(abs($rawAmount) * 100);

        $isDuplicate = Transaction::query()
            ->where('account_id', $accountId)
            ->whereDate('date', $date->toDateString())
            ->where('amount', $amountCents)
            ->where('type', $type)
            ->where('description', $description)
            ->exists();

        if ($isDuplicate) {
            return false;
        }

        $transaction = Transaction::create([
            'account_id'  => $accountId,
            'amount'      => $amountCents,
            'type'        => $type,
            'description' => $description,
            'date'        => $date,
            'is_reviewed' => false,
        ]);

        // Apply keyword rules synchronously — fast and free.
        app(\App\Services\CategoryRuleService::class)->applyToTransaction($transaction);

        return true;
    }

    /**
     * Try several common date formats; return Carbon or null on failure.
     */
    protected function parseDate(?string $value): ?Carbon
    {
        if (! $value || trim($value) === '') {
            return null;
        }

        $value = trim($value);

        $formats = ['Y-m-d', 'd/m/Y', 'd-m-Y', 'm/d/Y', 'd.m.Y', 'Y/m/d'];

        foreach ($formats as $format) {
            try {
                $date = Carbon::createFromFormat($format, $value);

                if ($date !== false && $date->format($format) === $value) {
                    return $date->startOfDay();
                }
            } catch (\Exception) {
                // This format didn't match — try the next one
                continue;
            }
        }

        // Last resort: let Carbon parse naturally (handles "Jan 5, 2026" etc.)
        try {
            return Carbon::parse($value)->startOfDay();
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * Parse amount strings like "1.234,56", "-12.50", "€ 50", "+1,234.56".
     * Returns a signed float or null if unparseable.
     */
    protected function parseAmount(?string $value): ?float
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        // Strip currency symbols, spaces, and chars other than digits . , - +
        $value = preg_replace('/[^\d.,\-+]/', '', trim($value));

        if ($value === '' || $value === '-' || $value === '+') {
            return null;
        }

        // European format: 1.234,56 (dot = thousands separator, comma = decimal)
        // Also accepts a leading + or - sign.
        if (preg_match('/^[+-]?\d{1,3}(\.\d{3})*(,\d+)?$/', $value)) {
            $value = str_replace('.', '', $value);  // remove thousands dots
            $value = str_replace(',', '.', $value); // comma → decimal point
        } elseif (preg_match('/^[+-]?\d{1,3}(,\d{3})*(\.\d+)?$/', $value)) {
            // US format: 1,234.56
            $value = str_replace(',', '', $value);
        } else {
            // Plain number — normalise any comma used as decimal separator
            $value = str_replace(',', '.', $value);
        }

        if (! is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }
}
