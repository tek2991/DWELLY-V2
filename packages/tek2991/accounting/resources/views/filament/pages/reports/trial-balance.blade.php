<x-filament-panels::page>
    <div class="mb-6 bg-white dark:bg-gray-900 shadow-sm ring-1 ring-gray-950/5 rounded-xl dark:ring-white/10 p-6">
        <form wire:submit.prevent="submit">
            {{ $this->form }}
        </form>
    </div>

    @php
        $data = $this->reportData;
    @endphp

    <div class="bg-white dark:bg-gray-900 shadow-sm ring-1 ring-gray-950/5 rounded-xl dark:ring-white/10 p-8 max-w-4xl mx-auto w-full">
        <div class="text-center mb-8">
            <h1 class="text-2xl font-bold tracking-tight text-gray-950 dark:text-white">Trial Balance</h1>
            <p class="text-gray-500 dark:text-gray-400 mt-2">As of {{ $data['endDate'] }}</p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b-2 border-gray-200 dark:border-gray-800">
                        <th class="py-3 px-4 font-semibold text-sm text-gray-900 dark:text-white">Account Code</th>
                        <th class="py-3 px-4 font-semibold text-sm text-gray-900 dark:text-white">Account Name</th>
                        <th class="py-3 px-4 font-semibold text-sm text-gray-900 dark:text-white" style="text-align: right;">Debit</th>
                        <th class="py-3 px-4 font-semibold text-sm text-gray-900 dark:text-white" style="text-align: right;">Credit</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                    @forelse($data['rows'] as $row)
                        <tr class="hover:bg-gray-50 dark:hover:bg-white/5">
                            <td class="py-2 px-4 text-sm text-gray-600 dark:text-gray-400">{{ $row['account']->code }}</td>
                            <td class="py-2 px-4 text-sm text-gray-900 dark:text-white font-medium">{{ $row['account']->name }}</td>
                            <td class="py-2 px-4 text-sm text-gray-900 dark:text-white" style="text-align: right;">
                                {{ $row['debit']->getAmount() > 0 ? $row['debit']->format() : '-' }}
                            </td>
                            <td class="py-2 px-4 text-sm text-gray-900 dark:text-white" style="text-align: right;">
                                {{ $row['credit']->getAmount() > 0 ? $row['credit']->format() : '-' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="py-4 text-center text-gray-500 dark:text-gray-400">No active accounts found with a balance.</td>
                        </tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr class="border-t-[3px] border-double border-gray-900 dark:border-white font-bold">
                        <td colspan="2" class="py-4 px-4 text-gray-900 dark:text-white" style="text-align: right;">Totals</td>
                        <td class="py-4 px-4 text-gray-900 dark:text-white" style="text-align: right;">{{ $data['totalDebit']->format() }}</td>
                        <td class="py-4 px-4 text-gray-900 dark:text-white" style="text-align: right;">{{ $data['totalCredit']->format() }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
        
        @if($data['totalDebit']->getAmount() !== $data['totalCredit']->getAmount())
            <div class="mt-4 p-4 text-sm text-red-800 rounded-lg bg-red-50 dark:bg-gray-800 dark:text-red-400" role="alert">
                <span class="font-medium">Warning!</span> The trial balance is out of balance by {{ (new \Tek2991\Accounting\ValueObjects\Money(abs($data['totalDebit']->getAmount() - $data['totalCredit']->getAmount()), $data['totalDebit']->getCurrencyCode()))->format() }}.
            </div>
        @else
            <div class="mt-4 p-4 text-sm text-green-800 rounded-lg bg-green-50 dark:bg-gray-800 dark:text-green-400 flex items-center" role="alert">
                <svg class="w-5 h-5 mr-2 flex-shrink-0" style="width: 1.25rem; height: 1.25rem;" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>
                <span class="font-medium">Balanced!</span> Debits equal credits.
            </div>
        @endif

    </div>
</x-filament-panels::page>
