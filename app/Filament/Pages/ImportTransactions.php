<?php

namespace App\Filament\Pages;

use App\Filament\Resources\TransactionResource;
use App\Jobs\AiCategorizeTransactionsJob;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\Trip;
use App\Services\CsvImportService;
use App\Services\TripService;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ImportTransactions extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrow-up-tray';

    protected static ?string $navigationLabel = 'Import Transactions';

    protected string $view = 'filament.pages.import-transactions';

    /** @var array<string, mixed> */
    public array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                FileUpload::make('csv_file')
                    ->label('CSV File')
                    ->acceptedFileTypes(['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'])
                    ->disk('local')
                    ->directory('csv-imports')
                    ->helperText('Supports Rabobank, Revolut, and generic CSV exports. The format is detected automatically.')
                    ->required(),

                Select::make('account_id')
                    ->label('Account')
                    ->options(fn () => Account::where('is_active', true)->pluck('name', 'id'))
                    ->required()
                    ->searchable()
                    ->helperText('All imported transactions will be assigned to this account.'),

                Checkbox::make('use_ai_categorization')
                    ->label('Use AI categorization')
                    ->helperText('Applies keyword rules first; AI runs only on transactions that remain uncategorized after that.')
                    ->default(false),

                Select::make('trip_id')
                    ->label('Assign to trip')
                    ->options(fn () => Trip::orderByDesc('start_date')->pluck('name', 'id'))
                    ->nullable()
                    ->searchable()
                    ->helperText('Optional — imported transactions dated inside the trip go straight onto it.'),
            ]);
    }

    public function import(): void
    {
        $data = $this->form->getState();

        $storedPath = $data['csv_file'];
        $fullPath = Storage::disk('local')->path($storedPath);
        $accountId = (int) $data['account_id'];
        $useAi = (bool) ($data['use_ai_categorization'] ?? false);
        $importStarted = now();

        $result = app(CsvImportService::class)->import($fullPath, $accountId);

        Storage::disk('local')->delete($storedPath);

        $tripAssigned = 0;

        if (filled($data['trip_id'] ?? null)) {
            $trip = Trip::findOrFail((int) $data['trip_id']);
            $tripAssigned = app(TripService::class)->autoAssignByDateRange($trip);
        }

        // Dispatch AI categorization as a background job so the user is not blocked.
        if ($useAi && $result['imported'] > 0) {
            $uncategorizedIds = Transaction::query()
                ->where('account_id', $accountId)
                ->whereNull('category_id')
                ->where('created_at', '>=', $importStarted)
                ->pluck('id')
                ->toArray();

            if (! empty($uncategorizedIds)) {
                AiCategorizeTransactionsJob::dispatch($uncategorizedIds);
            }
        }

        $reviewUrl = TransactionResource::getUrl('index').'?'.
            http_build_query(['tableFilters' => ['is_reviewed' => ['value' => false]]]);

        $lines = [];
        $lines[] = $result['imported'].' transaction'.($result['imported'] === 1 ? '' : 's').' imported.';

        if ($result['duplicates'] > 0) {
            $lines[] = $result['duplicates'].' duplicate'.($result['duplicates'] === 1 ? '' : 's').' skipped.';
        }

        if ($result['skipped'] > 0 && $result['imported'] === 0) {
            $lines[] = $result['skipped'].' row'.($result['skipped'] === 1 ? '' : 's').' skipped — unsupported format.';
        } elseif ($result['skipped'] > 0) {
            $lines[] = $result['skipped'].' row'.($result['skipped'] === 1 ? '' : 's').' skipped (invalid data).';
        }

        if ($tripAssigned > 0) {
            $lines[] = $tripAssigned.' transaction'.($tripAssigned === 1 ? '' : 's').' assigned to '.$trip->name.'.';
        }

        if ($useAi && $result['imported'] > 0) {
            $lines[] = 'AI categorization is running in the background.';
        }

        $notification = Notification::make()
            ->title('Import complete')
            ->body(implode("\n", $lines))
            ->icon('heroicon-o-arrow-up-tray')
            ->success()
            ->actions([
                Action::make('review')
                    ->label('Review transactions')
                    ->url($reviewUrl)
                    ->button()
                    ->markAsRead(),
            ]);

        $notification->send();
        $notification->sendToDatabase(Auth::user());

        $this->redirect($reviewUrl);
    }
}
