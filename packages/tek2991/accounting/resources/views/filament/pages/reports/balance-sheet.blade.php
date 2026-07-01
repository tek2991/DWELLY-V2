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
            <h1 class="text-2xl font-bold tracking-tight text-gray-950 dark:text-white">Balance Sheet</h1>
            <p class="text-gray-500 dark:text-gray-400 mt-2">As of {{ $data['endDate'] }}</p>
        </div>

        <!-- Assets -->
        <div class="mb-8">
            <h2 class="text-xl font-semibold text-gray-900 dark:text-white border-b pb-2 mb-4">Assets</h2>
            
            @forelse($data['assets'] as $class => $items)
                <div class="mb-4">
                    <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2 pl-2">{{ $class }}</h3>
                    <div class="space-y-2">
                        @foreach($items as $item)
                            <div class="flex justify-between items-center py-1 pl-6 pr-2 hover:bg-gray-50 dark:hover:bg-white/5 rounded">
                                <span class="text-gray-700 dark:text-gray-300">{{ $item['account']->name }} ({{ $item['account']->code }})</span>
                                <span class="text-gray-900 dark:text-white">{{ $item['balance']->format() }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @empty
                <p class="text-gray-500 dark:text-gray-400 italic pl-6">No assets found.</p>
            @endforelse

            <div class="flex justify-between items-center py-3 mt-4 border-t-2 border-gray-200 dark:border-gray-800 font-semibold pl-2 pr-2">
                <span class="text-gray-900 dark:text-white">Total Assets</span>
                <span class="text-gray-900 dark:text-white">{{ $data['totalAssets']->format() }}</span>
            </div>
        </div>

        <!-- Liabilities -->
        <div class="mb-8">
            <h2 class="text-xl font-semibold text-gray-900 dark:text-white border-b pb-2 mb-4">Liabilities</h2>
            
            @forelse($data['liabilities'] as $class => $items)
                <div class="mb-4">
                    <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2 pl-2">{{ $class }}</h3>
                    <div class="space-y-2">
                        @foreach($items as $item)
                            <div class="flex justify-between items-center py-1 pl-6 pr-2 hover:bg-gray-50 dark:hover:bg-white/5 rounded">
                                <span class="text-gray-700 dark:text-gray-300">{{ $item['account']->name }} ({{ $item['account']->code }})</span>
                                <span class="text-gray-900 dark:text-white">{{ $item['balance']->format() }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @empty
                <p class="text-gray-500 dark:text-gray-400 italic pl-6">No liabilities found.</p>
            @endforelse

            <div class="flex justify-between items-center py-3 mt-4 border-t-2 border-gray-200 dark:border-gray-800 font-semibold pl-2 pr-2">
                <span class="text-gray-900 dark:text-white">Total Liabilities</span>
                <span class="text-gray-900 dark:text-white">{{ $data['totalLiabilities']->format() }}</span>
            </div>
        </div>

        <!-- Equity -->
        <div class="mb-8">
            <h2 class="text-xl font-semibold text-gray-900 dark:text-white border-b pb-2 mb-4">Equity</h2>
            
            @forelse($data['equity'] as $class => $items)
                <div class="mb-4">
                    <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2 pl-2">{{ $class }}</h3>
                    <div class="space-y-2">
                        @foreach($items as $item)
                            <div class="flex justify-between items-center py-1 pl-6 pr-2 hover:bg-gray-50 dark:hover:bg-white/5 rounded">
                                <span class="text-gray-700 dark:text-gray-300">{{ $item['account']->name }} ({{ $item['account']->code }})</span>
                                <span class="text-gray-900 dark:text-white">{{ $item['balance']->format() }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @empty
                <p class="text-gray-500 dark:text-gray-400 italic pl-6">No equity found.</p>
            @endforelse

            <div class="mb-4">
                <div class="space-y-2">
                    <div class="flex justify-between items-center py-1 pl-6 pr-2 hover:bg-gray-50 dark:hover:bg-white/5 rounded">
                        <span class="text-gray-700 dark:text-gray-300">Historical Retained Earnings (Closed Periods)</span>
                        <span class="text-gray-900 dark:text-white">{{ $data['historicalRetained']->format() }}</span>
                    </div>
                    <div class="flex justify-between items-center py-1 pl-6 pr-2 hover:bg-gray-50 dark:hover:bg-white/5 rounded">
                        <span class="text-gray-700 dark:text-gray-300">Current Year Profit/Loss</span>
                        <span class="text-gray-900 dark:text-white">{{ $data['currentYearProfit']->format() }}</span>
                    </div>
                </div>
            </div>

            <div class="flex justify-between items-center py-3 mt-4 border-t-2 border-gray-200 dark:border-gray-800 font-semibold pl-2 pr-2">
                <span class="text-gray-900 dark:text-white">Total Equity</span>
                <span class="text-gray-900 dark:text-white">{{ $data['totalEquity']->format() }}</span>
            </div>
        </div>

        <!-- Total Liabilities & Equity -->
        <div class="flex justify-between items-center py-4 mt-8 border-t-[3px] border-double border-gray-900 dark:border-white font-bold text-lg pl-2 pr-2">
            <span class="text-gray-900 dark:text-white">Total Liabilities & Equity</span>
            <span class="text-gray-900 dark:text-white">{{ $data['totalLiabilitiesAndEquity']->format() }}</span>
        </div>
        
        @if($data['totalAssets']->getAmount() !== $data['totalLiabilitiesAndEquity']->getAmount())
            <div class="mt-4 p-4 text-sm text-red-800 rounded-lg bg-red-50 dark:bg-gray-800 dark:text-red-400" role="alert">
                <span class="font-medium">Warning!</span> The balance sheet is out of balance by {{ (new \Tek2991\Accounting\ValueObjects\Money(abs($data['totalAssets']->getAmount() - $data['totalLiabilitiesAndEquity']->getAmount()), $data['totalAssets']->getCurrencyCode()))->format() }}.
            </div>
        @endif

    </div>
</x-filament-panels::page>
