<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Recent Transactions</x-slot>
        <x-slot name="headerEnd">
            <a href="{{ $listUrl }}" class="text-sm text-primary-600 dark:text-primary-400 hover:underline">
                View all →
            </a>
        </x-slot>

        @if ($transactions->isEmpty())
            <p class="text-sm text-gray-500 dark:text-gray-400">No transactions yet.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-700">
                            <th class="pb-2 font-medium text-gray-500 dark:text-gray-400">Date</th>
                            <th class="pb-2 font-medium text-gray-500 dark:text-gray-400">Description</th>
                            <th class="pb-2 font-medium text-gray-500 dark:text-gray-400">Category</th>
                            <th class="pb-2 font-medium text-gray-500 dark:text-gray-400 text-right">Amount</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($transactions as $transaction)
                            @php
                                $category = $transaction->category;
                                $categoryLabel = $category
                                    ? ($category->parent ? $category->parent->name . ' › ' . $category->name : $category->name)
                                    : '—';
                                $isIncome = $transaction->type->value === 'income';
                            @endphp
                            <tr>
                                <td class="py-2 pr-4 text-gray-600 dark:text-gray-400 whitespace-nowrap">
                                    {{ $transaction->date->format('d M Y') }}
                                </td>
                                <td class="py-2 pr-4 text-gray-800 dark:text-gray-200 max-w-[200px] truncate">
                                    {{ $transaction->description }}
                                </td>
                                <td class="py-2 pr-4 text-gray-500 dark:text-gray-400">
                                    {{ $categoryLabel }}
                                </td>
                                <td class="py-2 text-right font-medium whitespace-nowrap {{ $isIncome ? 'text-success-600 dark:text-success-400' : 'text-danger-600 dark:text-danger-400' }}">
                                    {{ $isIncome ? '+' : '-' }}€{{ number_format($transaction->amount / 100, 2) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
