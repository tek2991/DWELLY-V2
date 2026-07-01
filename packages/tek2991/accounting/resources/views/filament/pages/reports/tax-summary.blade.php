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
            <h1 class="text-2xl font-bold tracking-tight text-gray-950 dark:text-white">Tax Summary</h1>
            <p class="text-gray-500 dark:text-gray-400 mt-2">For the period {{ $data['startDate'] }} to {{ $data['endDate'] }}</p>
        </div>

        <div class="overflow-x-auto mb-8">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b-2 border-gray-200 dark:border-gray-800">
                        <th class="py-3 px-4 font-semibold text-sm text-gray-900 dark:text-white">Tax Group / Component</th>
                        <th class="py-3 px-4 font-semibold text-sm text-gray-900 dark:text-white" style="text-align: right;">Output Tax (Collected)</th>
                        <th class="py-3 px-4 font-semibold text-sm text-gray-900 dark:text-white" style="text-align: right;">Input Tax (Paid)</th>
                        <th class="py-3 px-4 font-semibold text-sm text-gray-900 dark:text-white" style="text-align: right;">Net Payable</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                    @forelse($data['rows'] as $row)
                        <!-- Tax Group Header -->
                        <tr class="bg-gray-50 dark:bg-white/5">
                            <td class="py-3 px-4 text-sm font-semibold text-gray-900 dark:text-white">
                                {{ $row['tax']->name }}
                            </td>
                            <td class="py-3 px-4 text-sm font-semibold text-gray-900 dark:text-white" style="text-align: right;">{{ $row['output']->format() }}</td>
                            <td class="py-3 px-4 text-sm font-semibold text-gray-900 dark:text-white" style="text-align: right;">{{ $row['input']->format() }}</td>
                            <td class="py-3 px-4 text-sm font-semibold {{ $row['payable']->getAmount() > 0 ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400' }}" style="text-align: right;">
                                {{ $row['payable']->format() }}
                            </td>
                        </tr>
                        
                        <!-- Tax Components -->
                        @foreach($row['components'] as $component)
                            <tr class="hover:bg-gray-50 dark:hover:bg-white/5">
                                <td class="py-2 px-8 text-sm text-gray-600 dark:text-gray-400">
                                    <span class="inline-block w-4 h-px bg-gray-300 dark:bg-gray-600 mr-2 align-middle"></span>
                                    {{ $component['name'] }}
                                </td>
                                <td class="py-2 px-4 text-sm text-gray-700 dark:text-gray-300" style="text-align: right;">{{ $component['output']->format() }}</td>
                                <td class="py-2 px-4 text-sm text-gray-700 dark:text-gray-300" style="text-align: right;">{{ $component['input']->format() }}</td>
                                <td class="py-2 px-4 text-sm text-gray-700 dark:text-gray-300" style="text-align: right;">{{ $component['payable']->format() }}</td>
                            </tr>
                        @endforeach
                    @empty
                        <tr>
                            <td colspan="4" class="py-4 text-center text-gray-500 dark:text-gray-400">No tax records found for the selected period.</td>
                        </tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr class="border-t-[3px] border-double border-gray-900 dark:border-white font-bold bg-gray-50 dark:bg-gray-800">
                        <td class="py-4 px-4 text-gray-900 dark:text-white" style="text-align: right;">Total Tax Payable</td>
                        <td class="py-4 px-4 text-gray-900 dark:text-white" style="text-align: right;">{{ $data['totalOutput']->format() }}</td>
                        <td class="py-4 px-4 text-gray-900 dark:text-white" style="text-align: right;">{{ $data['totalInput']->format() }}</td>
                        <td class="py-4 px-4 text-gray-900 dark:text-white {{ $data['totalPayable']->getAmount() > 0 ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400' }}" style="text-align: right;">
                            {{ $data['totalPayable']->format() }}
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>

        @if($data['totalPayable']->getAmount() > 0)
            <div class="mt-4 p-4 text-sm text-yellow-800 rounded-lg bg-yellow-50 dark:bg-gray-800 dark:text-yellow-400" role="alert">
                <span class="font-medium">Liability!</span> You have a net tax liability of {{ $data['totalPayable']->format() }} for this period.
            </div>
        @elseif($data['totalPayable']->getAmount() < 0)
            <div class="mt-4 p-4 text-sm text-green-800 rounded-lg bg-green-50 dark:bg-gray-800 dark:text-green-400" role="alert">
                <span class="font-medium">Receivable!</span> You have a net tax receivable of {{ (new \Tek2991\Accounting\ValueObjects\Money(abs($data['totalPayable']->getAmount()), $data['totalPayable']->getCurrencyCode()))->format() }} for this period.
            </div>
        @endif

    </div>
</x-filament-panels::page>
