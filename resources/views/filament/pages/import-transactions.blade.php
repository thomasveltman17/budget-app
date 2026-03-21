<x-filament-panels::page>
    <x-filament::section
        heading="Upload CSV"
        description="Upload a CSV file exported from your bank. Negative amounts will be imported as expenses, positive amounts as income."
        icon="heroicon-o-document-arrow-up"
    >
        <form wire:submit="import" class="space-y-6">
            {{ $this->form }}

            <div class="flex justify-end pt-2">
                <x-filament::button
                    type="submit"
                    icon="heroicon-o-arrow-up-tray"
                    wire:loading.attr="disabled"
                >
                    <span wire:loading.remove>Import Transactions</span>
                    <span wire:loading>Importing…</span>
                </x-filament::button>
            </div>
        </form>
    </x-filament::section>

    <x-filament::section
        heading="CSV Format Guide"
        icon="heroicon-o-information-circle"
        collapsible
        collapsed
    >
        <div class="text-sm text-gray-600 dark:text-gray-400 space-y-3">
            <p>Your CSV file must have a <strong>header row</strong> as the first line. The following column names are recognised (case-insensitive):</p>
            <div class="overflow-x-auto">
                <table class="w-full text-left border border-gray-200 dark:border-gray-700 rounded-lg text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <th class="px-4 py-2 font-medium">Column</th>
                            <th class="px-4 py-2 font-medium">Required</th>
                            <th class="px-4 py-2 font-medium">Example</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        <tr>
                            <td class="px-4 py-2 font-mono">date</td>
                            <td class="px-4 py-2">✓ Yes</td>
                            <td class="px-4 py-2 text-gray-500">2025-03-01 &nbsp;or&nbsp; 01/03/2025</td>
                        </tr>
                        <tr>
                            <td class="px-4 py-2 font-mono">description</td>
                            <td class="px-4 py-2">✓ Yes</td>
                            <td class="px-4 py-2 text-gray-500">Supermarket purchase</td>
                        </tr>
                        <tr>
                            <td class="px-4 py-2 font-mono">amount</td>
                            <td class="px-4 py-2">✓ Yes</td>
                            <td class="px-4 py-2 text-gray-500">-24.99 &nbsp;or&nbsp; 1.500,00</td>
                        </tr>
                        <tr>
                            <td class="px-4 py-2 font-mono">type</td>
                            <td class="px-4 py-2">✗ Optional</td>
                            <td class="px-4 py-2 text-gray-500">Ignored — auto-detected from amount sign</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p>Rows missing a required field are skipped and logged as warnings. All imported transactions will have <strong>is_reviewed = false</strong> until you confirm them.</p>
        </div>
    </x-filament::section>

    <x-filament-actions::modals />
</x-filament-panels::page>
