<?php

namespace App\Filament\Pages\Billing;

use App\Domain\Agreement\Enums\DeboardingStatus;
use App\Domain\Agreement\Models\TenancyAgreement;
use App\Domain\Agreement\Models\TenantDeboarding;
use App\Domain\Finance\Models\OwnerPayout;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Tek2991\Accounting\Enums\InvoiceStatus;
use Tek2991\Accounting\Models\Invoice;
use Tek2991\Accounting\Services\AccountService;
use UnitEnum;

class FinancialDashboard extends Page
{
    protected string $view = 'filament.pages.billing.financial-dashboard';

    protected static BackedEnum|string|null $navigationIcon = Heroicon::OutlinedChartBarSquare;

    protected static UnitEnum|string|null $navigationGroup = 'Billing & Finance';

    protected static ?string $navigationLabel = 'Financial Dashboard';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'Financial & Accounting Intelligence';

    public string $activeTab = 'overview';

    public static function canAccess(): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        if ($user->roles->isEmpty()) {
            return true;
        }

        // Allowed: Business Owner, City Manager, Accountant
        // Forbidden: Operations Manager, Operations Executive, Demand Manager, Supply Manager
        return $user->can('accounting.reports.view')
            || $user->hasAnyRole(['Business Owner', 'City Manager', 'Accountant']);
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('bulk_rent')
                ->label('Generate Monthly Rent')
                ->icon('heroicon-m-document-plus')
                ->color('success')
                ->url(url('/operations/bulk-generate-monthly-rent')),

            Action::make('bulk_payouts')
                ->label('Disburse Owner Payouts')
                ->icon('heroicon-m-arrow-up-tray')
                ->color('primary')
                ->url(url('/operations/bulk-generate-owner-payouts')),

            Action::make('operations_hub')
                ->label('Financial Operations Hub')
                ->icon('heroicon-m-scale')
                ->color('gray')
                ->url(url('/operations/financial-operations-hub')),
        ];
    }

    public function getTabCounts(): array
    {
        $unpaidRentCount = Invoice::whereIn('status', [InvoiceStatus::Sent, InvoiceStatus::PartiallyPaid])
            ->where('balance_due', '>', 0)
            ->count();

        $pendingPayoutsCount = OwnerPayout::whereIn('status', ['draft', 'pending'])->count();

        $activeDepositsCount = TenancyAgreement::where('status', 'active')
            ->where('security_deposit', '>', 0)
            ->count();

        $openMaintenanceBillsCount = Invoice::where('notes', 'like', '%maintenance%')
            ->where('balance_due', '>', 0)
            ->count();

        return [
            'rent_aging' => $unpaidRentCount,
            'payouts' => $pendingPayoutsCount,
            'deposits' => $activeDepositsCount,
            'maintenance' => $openMaintenanceBillsCount,
        ];
    }

    public function getOverviewData(): array
    {
        $currentMonth = now()->month;
        $currentYear = now()->year;

        // Current Month Invoicing
        $monthInvoices = Invoice::whereMonth('issue_date', $currentMonth)
            ->whereYear('issue_date', $currentYear);

        $grossBilled = (float) ((clone $monthInvoices)
            ->whereIn('status', [InvoiceStatus::Sent, InvoiceStatus::PartiallyPaid, InvoiceStatus::Paid])
            ->sum('grand_total') / 100);

        $rentCollected = (float) ((clone $monthInvoices)
            ->sum('amount_paid') / 100);

        $collectionRate = $grossBilled > 0 ? round(($rentCollected / $grossBilled) * 100, 1) : 100.0;

        // Current Month Owner Disbursals & Commission
        $payoutsThisMonth = OwnerPayout::where('status', 'completed')
            ->where(function ($q) use ($currentMonth, $currentYear) {
                $q->whereMonth('processed_at', $currentMonth)
                    ->whereYear('processed_at', $currentYear);
            });

        $disbursedTotal = (float) (clone $payoutsThisMonth)->sum('amount');
        $commissionEarned = (float) (clone $payoutsThisMonth)->sum('management_fee');

        // YTD Totals
        $ytdRevenue = (float) (Invoice::whereYear('issue_date', $currentYear)
            ->whereIn('status', [InvoiceStatus::Sent, InvoiceStatus::PartiallyPaid, InvoiceStatus::Paid])
            ->sum('amount_paid') / 100);

        $ytdDisbursed = (float) OwnerPayout::where('status', 'completed')
            ->whereYear('processed_at', $currentYear)
            ->sum('amount');

        $ytdCommission = (float) OwnerPayout::where('status', 'completed')
            ->whereYear('processed_at', $currentYear)
            ->sum('management_fee');

        // Trailing 6 months cash flow
        $trailingMonths = [];
        $accountService = app(AccountService::class);
        for ($i = 5; $i >= 0; $i--) {
            $start = now()->subMonths($i)->startOfMonth();
            $end = now()->subMonths($i)->endOfMonth();

            $rev = (float) (Invoice::whereBetween('issue_date', [$start->toDateString(), $end->toDateString()])
                ->sum('amount_paid') / 100);
            $payouts = (float) OwnerPayout::where('status', 'completed')
                ->whereBetween('processed_at', [$start, $end])
                ->sum('amount');
            $fees = (float) OwnerPayout::where('status', 'completed')
                ->whereBetween('processed_at', [$start, $end])
                ->sum('management_fee');

            $trailingMonths[] = [
                'month' => $start->format('M Y'),
                'revenue' => $rev,
                'payouts' => $payouts,
                'fees' => $fees,
                'net_margin' => $rev - $payouts,
            ];
        }

        // Expiring Leases Churn Exposure (< 60 days)
        $expiringQuery = TenancyAgreement::where('status', 'active')
            ->whereNotNull('end_date')
            ->whereBetween('end_date', [now()->subDays(7), now()->addDays(60)]);
        $rentAtRisk60d = (float) (clone $expiringQuery)->sum('rent_amount');
        $expiringCount60d = (clone $expiringQuery)->count();

        return [
            'gross_billed' => $grossBilled,
            'rent_collected' => $rentCollected,
            'collection_rate' => $collectionRate,
            'disbursed_total' => $disbursedTotal,
            'commission_earned' => $commissionEarned,
            'ytd_revenue' => $ytdRevenue,
            'ytd_disbursed' => $ytdDisbursed,
            'ytd_commission' => $ytdCommission,
            'rent_at_risk_60d' => $rentAtRisk60d,
            'expiring_count_60d' => $expiringCount60d,
            'trailing_months' => $trailingMonths,
        ];
    }

    public function getRentAgingData(): array
    {
        $unpaidInvoices = Invoice::whereIn('status', [InvoiceStatus::Sent, InvoiceStatus::PartiallyPaid])
            ->where('balance_due', '>', 0);

        $totalReceivable = (float) ((clone $unpaidInvoices)->sum('balance_due') / 100);

        $now = now();
        $bucketCurrent = (float) ((clone $unpaidInvoices)
            ->where('due_date', '>=', $now->copy()->subDays(30)->toDateString())
            ->sum('balance_due') / 100);

        $bucket3160 = (float) ((clone $unpaidInvoices)
            ->whereBetween('due_date', [
                $now->copy()->subDays(60)->toDateString(),
                $now->copy()->subDays(31)->toDateString(),
            ])
            ->sum('balance_due') / 100);

        $bucket6190 = (float) ((clone $unpaidInvoices)
            ->whereBetween('due_date', [
                $now->copy()->subDays(90)->toDateString(),
                $now->copy()->subDays(61)->toDateString(),
            ])
            ->sum('balance_due') / 100);

        $bucket90Plus = (float) ((clone $unpaidInvoices)
            ->where('due_date', '<', $now->copy()->subDays(90)->toDateString())
            ->sum('balance_due') / 100);

        // Delinquent Accounts List
        $delinquentList = (clone $unpaidInvoices)
            ->with(['contact'])
            ->orderBy('due_date', 'asc')
            ->limit(12)
            ->get();

        return [
            'total_receivable' => $totalReceivable,
            'bucket_current' => $bucketCurrent,
            'bucket_31_60' => $bucket3160,
            'bucket_61_90' => $bucket6190,
            'bucket_90_plus' => $bucket90Plus,
            'delinquent_invoices' => $delinquentList,
        ];
    }

    public function getPayoutsData(): array
    {
        $currentMonth = now()->month;
        $currentYear = now()->year;

        $completedPayouts = OwnerPayout::where('status', 'completed')
            ->whereMonth('period_start', $currentMonth)
            ->whereYear('period_start', $currentYear);

        $draftPayouts = OwnerPayout::whereIn('status', ['draft', 'pending'])
            ->whereMonth('period_start', $currentMonth)
            ->whereYear('period_start', $currentYear);

        $grossRent = (float) (clone $completedPayouts)->sum('rent_collected');
        $managementFees = (float) (clone $completedPayouts)->sum('management_fee');
        $advanceOffsets = (float) (clone $completedPayouts)->sum('advance_offset');
        $netDisbursed = (float) (clone $completedPayouts)->sum('amount');

        $pendingAmount = (float) (clone $draftPayouts)->sum('amount');
        $pendingCount = (clone $draftPayouts)->count();

        // Recent Disbursals
        $recentPayouts = OwnerPayout::with(['owner', 'property'])
            ->latest('updated_at')
            ->limit(10)
            ->get();

        return [
            'gross_rent' => $grossRent,
            'management_fees' => $managementFees,
            'advance_offsets' => $advanceOffsets,
            'net_disbursed' => $netDisbursed,
            'pending_amount' => $pendingAmount,
            'pending_count' => $pendingCount,
            'recent_payouts' => $recentPayouts,
        ];
    }

    public function getDepositsData(): array
    {
        $activeDepositsTotal = (float) TenancyAgreement::where('status', 'active')->sum('security_deposit');
        $activeDepositsCount = TenancyAgreement::where('status', 'active')->where('security_deposit', '>', 0)->count();

        $deboardingsTotal = (float) TenantDeboarding::whereNotIn('status', [
            DeboardingStatus::COMPLETED,
            DeboardingStatus::CANCELLED,
        ])->sum('security_deposit_held');

        $refundedYtd = (float) TenantDeboarding::where('status', DeboardingStatus::COMPLETED)
            ->whereYear('refunded_at', now()->year)
            ->sum('net_deposit_refund');

        $deductionsYtd = (float) TenantDeboarding::where('status', DeboardingStatus::COMPLETED)
            ->whereYear('completed_at', now()->year)
            ->sum('total_deductions');

        $agreements = TenancyAgreement::where('status', 'active')
            ->where('security_deposit', '>', 0)
            ->with(['property', 'tenants'])
            ->latest('updated_at')
            ->limit(10)
            ->get();

        return [
            'active_deposits_total' => $activeDepositsTotal,
            'active_deposits_count' => $activeDepositsCount,
            'pending_settlements' => $deboardingsTotal,
            'refunded_ytd' => $refundedYtd,
            'deductions_ytd' => $deductionsYtd,
            'agreements' => $agreements,
        ];
    }

    public function getMaintenanceFinancialData(): array
    {
        $allMaintenanceInvoices = Invoice::where('notes', 'like', '%maintenance%');

        $totalInvoiced = (float) ((clone $allMaintenanceInvoices)->sum('grand_total') / 100);
        $totalCollected = (float) ((clone $allMaintenanceInvoices)->sum('amount_paid') / 100);
        $totalPending = (float) ((clone $allMaintenanceInvoices)->where('balance_due', '>', 0)->sum('balance_due') / 100);

        $recentMaintenanceInvoices = (clone $allMaintenanceInvoices)
            ->latest('issue_date')
            ->limit(10)
            ->get();

        return [
            'total_invoiced' => $totalInvoiced,
            'total_collected' => $totalCollected,
            'total_pending' => $totalPending,
            'invoices' => $recentMaintenanceInvoices,
        ];
    }
}
