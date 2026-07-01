<x-filament-panels::page>
    <div class="mb-6 bg-white dark:bg-gray-900 shadow-sm ring-1 ring-gray-950/5 rounded-xl dark:ring-white/10 p-6">
        <form wire:submit.prevent="submit">
            {{ $this->form }}
        </form>
    </div>

    @php
        $data = $this->reportData;
    @endphp

    @if($data['showReport'] ?? false)
        <div class="bg-white dark:bg-gray-900 shadow-sm ring-1 ring-gray-950/5 rounded-xl dark:ring-white/10 p-8 max-w-5xl mx-auto w-full">
            <div class="text-center mb-8">
                <h1 class="text-2xl font-bold tracking-tight text-gray-950 dark:text-white">{{ $data['title'] }}</h1>
                @if(isset($data['subtitle']))
                    <p class="text-lg font-medium text-gray-700 dark:text-gray-300 mt-1">{{ $data['subtitle'] }}</p>
                @endif
                <p class="text-gray-500 dark:text-gray-400 mt-2">For the period {{ $data['startDate'] }} to {{ $data['endDate'] }}</p>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b-2 border-gray-200 dark:border-gray-800">
                            <th class="py-3 px-4 font-semibold text-sm text-gray-900 dark:text-white">Date</th>
                            <th class="py-3 px-4 font-semibold text-sm text-gray-900 dark:text-white" style="text-align: center;">Ref</th>
                            <th class="py-3 px-4 font-semibold text-sm text-gray-900 dark:text-white" style="text-align: center;">Description</th>
                            <th class="py-3 px-4 font-semibold text-sm text-gray-900 dark:text-white" style="text-align: right;">Debit</th>
                            <th class="py-3 px-4 font-semibold text-sm text-gray-900 dark:text-white" style="text-align: right;">Credit</th>
                            <th class="py-3 px-4 font-semibold text-sm text-gray-900 dark:text-white" style="text-align: right;">Balance</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                        @forelse($data['rows'] as $row)
                            @if($row['is_summary'] ?? false)
                                <!-- Summary Row (Opening/Closing) -->
                                <tr class="bg-gray-50 dark:bg-gray-800 font-semibold">
                                    <td colspan="3" class="py-3 px-4 text-sm text-gray-900 dark:text-white" style="text-align: right;">{{ $row['description'] }}</td>
                                    <td class="py-3 px-4 text-sm text-gray-900 dark:text-white" style="text-align: right;">{{ isset($row['debit']) ? $row['debit']->format() : '' }}</td>
                                    <td class="py-3 px-4 text-sm text-gray-900 dark:text-white" style="text-align: right;">{{ isset($row['credit']) ? $row['credit']->format() : '' }}</td>
                                    <td class="py-3 px-4 text-sm text-gray-900 dark:text-white" style="text-align: right;">{{ $row['balance']->format() }}</td>
                                </tr>
                            @else
                                <!-- Transaction Row -->
                                <tr class="hover:bg-gray-50 dark:hover:bg-white/5">
                                    <td class="py-2 px-4 text-sm text-gray-600 dark:text-gray-400 whitespace-nowrap">{{ $row['date'] }}</td>
                                    <td class="py-2 px-4 text-sm text-gray-900 dark:text-white font-medium" style="text-align: center;">{{ $row['ref'] }}</td>
                                    <td class="py-2 px-4 text-sm text-gray-600 dark:text-gray-400" style="text-align: center;">{{ $row['description'] }}</td>
                                    <td class="py-2 px-4 text-sm text-gray-900 dark:text-white" style="text-align: right;">
                                        {{ isset($row['debit']) && $row['debit']->getAmount() > 0 ? $row['debit']->format() : '' }}
                                    </td>
                                    <td class="py-2 px-4 text-sm text-gray-900 dark:text-white" style="text-align: right;">
                                        {{ isset($row['credit']) && $row['credit']->getAmount() > 0 ? $row['credit']->format() : '' }}
                                    </td>
                                    <td class="py-2 px-4 text-sm text-gray-900 dark:text-white font-medium" style="text-align: right;">
                                        <span class="{{ ($row['balance']->getAmount() < 0) ? 'text-red-600 dark:text-red-400' : '' }}">
                                            {{ $row['balance']->format() }}
                                        </span>
                                    </td>
                                </tr>
                            @endif
                        @empty
                            <tr>
                                <td colspan="6" class="py-4 text-center text-gray-500 dark:text-gray-400">No transactions found for this period.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

        </div>
    @else
        <div class="bg-white dark:bg-gray-900 shadow-sm ring-1 ring-gray-950/5 rounded-xl dark:ring-white/10 p-8 text-center">
            <x-filament::icon icon="heroicon-o-document-magnifying-glass" class="w-12 h-12 mx-auto text-gray-400 mb-4" />
            <h3 class="text-lg font-medium text-gray-900 dark:text-white">Select parameters to view ledger</h3>
            <p class="text-gray-500 dark:text-gray-400 mt-1">Please select an entity and date range from the form above.</p>
        </div>
    @endif
</x-filament-panels::page>
