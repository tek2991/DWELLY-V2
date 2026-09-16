@php
    $summary = $summary ?? $record?->getFinancialSummary() ?? [
        'total_invoiced' => 0,
        'total_collected' => 0,
        'receivables_due' => 0,
        'invoices_count' => 0,
        'total_bills' => 0,
        'bills_paid' => 0,
        'payables_due' => 0,
        'bills_count' => 0,
    ];
@endphp

<div class="mb-4">
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 1rem;">
        <!-- Card 1: Total Invoiced -->
        <div class="fi-ta-header-cell rounded-xl border border-gray-200 bg-white p-4 shadow-sm transition-all duration-200 hover:shadow-md dark:border-white/10 dark:bg-gray-900">
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Total Invoiced</span>
                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600 dark:bg-indigo-950/60 dark:text-indigo-400">
                    <x-filament::icon icon="heroicon-o-document-currency-rupee" class="h-5 w-5" />
                </div>
            </div>
            <div class="mt-2 flex items-baseline gap-1">
                <span class="text-xl font-bold tracking-tight text-gray-950 dark:text-white">₹{{ number_format($summary['total_invoiced'], 2) }}</span>
            </div>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                {{ $summary['invoices_count'] }} {{ \Illuminate\Support\Str::plural('invoice', $summary['invoices_count']) }} & rent demands
            </p>
        </div>

        <!-- Card 2: Receipts Collected -->
        <div class="fi-ta-header-cell rounded-xl border border-gray-200 bg-white p-4 shadow-sm transition-all duration-200 hover:shadow-md dark:border-white/10 dark:bg-gray-900">
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Receipts Collected</span>
                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600 dark:bg-emerald-950/60 dark:text-emerald-400">
                    <x-filament::icon icon="heroicon-o-check-badge" class="h-5 w-5" />
                </div>
            </div>
            <div class="mt-2 flex items-baseline gap-1">
                <span class="text-xl font-bold tracking-tight text-emerald-600 dark:text-emerald-400">₹{{ number_format($summary['total_collected'], 2) }}</span>
            </div>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                Total client & tenant receipts
            </p>
        </div>

        <!-- Card 3: Outstanding Receivables -->
        <div class="fi-ta-header-cell rounded-xl border border-gray-200 bg-white p-4 shadow-sm transition-all duration-200 hover:shadow-md dark:border-white/10 dark:bg-gray-900">
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Receivables Due</span>
                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-amber-50 text-amber-600 dark:bg-amber-950/60 dark:text-amber-400">
                    <x-filament::icon icon="heroicon-o-clock" class="h-5 w-5" />
                </div>
            </div>
            <div class="mt-2 flex items-baseline gap-1">
                <span class="text-xl font-bold tracking-tight text-amber-600 dark:text-amber-400">₹{{ number_format($summary['receivables_due'], 2) }}</span>
            </div>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                Uncollected tenant dues
            </p>
        </div>

        <!-- Card 4: Vendor & Utility Bills -->
        <div class="fi-ta-header-cell rounded-xl border border-gray-200 bg-white p-4 shadow-sm transition-all duration-200 hover:shadow-md dark:border-white/10 dark:bg-gray-900">
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Total Bills</span>
                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-sky-50 text-sky-600 dark:bg-sky-950/60 dark:text-sky-400">
                    <x-filament::icon icon="heroicon-o-receipt-percent" class="h-5 w-5" />
                </div>
            </div>
            <div class="mt-2 flex items-baseline gap-1">
                <span class="text-xl font-bold tracking-tight text-gray-950 dark:text-white">₹{{ number_format($summary['total_bills'], 2) }}</span>
            </div>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                {{ $summary['bills_count'] }} {{ \Illuminate\Support\Str::plural('bill', $summary['bills_count']) }} (Utilities & Society)
            </p>
        </div>

        <!-- Card 5: Outstanding Payables -->
        <div class="fi-ta-header-cell rounded-xl border border-gray-200 bg-white p-4 shadow-sm transition-all duration-200 hover:shadow-md dark:border-white/10 dark:bg-gray-900">
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Payables Due</span>
                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-rose-50 text-rose-600 dark:bg-rose-950/60 dark:text-rose-400">
                    <x-filament::icon icon="heroicon-o-exclamation-triangle" class="h-5 w-5" />
                </div>
            </div>
            <div class="mt-2 flex items-baseline gap-1">
                <span class="text-xl font-bold tracking-tight text-rose-600 dark:text-rose-400">₹{{ number_format($summary['payables_due'], 2) }}</span>
            </div>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                Pending vendor payout
            </p>
        </div>
    </div>
</div>
