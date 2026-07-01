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
            <h1 class="text-2xl font-bold tracking-tight text-gray-950 dark:text-white">Profit & Loss</h1>
            <p class="text-gray-500 dark:text-gray-400 mt-2">For the period {{ $data['startDate'] }} to {{ $data['endDate'] }}</p>
        </div>

        <!-- Revenue -->
        <div class="mb-8">
            <h2 class="text-xl font-semibold text-gray-900 dark:text-white border-b pb-2 mb-4">Revenue</h2>
            
            @forelse($data['revenue'] as $class => $items)
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
                <p class="text-gray-500 dark:text-gray-400 italic pl-6">No revenue found.</p>
            @endforelse

            <div class="flex justify-between items-center py-3 mt-4 border-t-2 border-gray-200 dark:border-gray-800 font-semibold pl-2 pr-2">
                <span class="text-gray-900 dark:text-white">Total Revenue</span>
                <span class="text-gray-900 dark:text-white">{{ $data['totalRevenue']->format() }}</span>
            </div>
        </div>

        <!-- Expenses -->
        <div class="mb-8">
            <h2 class="text-xl font-semibold text-gray-900 dark:text-white border-b pb-2 mb-4">Expenses</h2>
            
            @forelse($data['expenses'] as $class => $items)
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
                <p class="text-gray-500 dark:text-gray-400 italic pl-6">No expenses found.</p>
            @endforelse

            <div class="flex justify-between items-center py-3 mt-4 border-t-2 border-gray-200 dark:border-gray-800 font-semibold pl-2 pr-2">
                <span class="text-gray-900 dark:text-white">Total Expenses</span>
                <span class="text-gray-900 dark:text-white">{{ $data['totalExpenses']->format() }}</span>
            </div>
        </div>

        <!-- Net Income -->
        <div class="flex justify-between items-center py-4 mt-8 border-t-[3px] border-double border-gray-900 dark:border-white font-bold text-lg pl-2 pr-2">
            <span class="text-gray-900 dark:text-white">Net Income</span>
            <span class="{{ $data['netIncome']->getAmount() >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                {{ $data['netIncome']->format() }}
            </span>
        </div>
        
    </div>
</x-filament-panels::page>
