<?php

namespace App\Filament\Widgets;

use App\Domain\Agreement\Models\TenancyAgreement;
use App\Domain\Finance\Models\OwnerPayout;
use Carbon\Carbon;
use Filament\Widgets\Widget;
use Tek2991\Accounting\Models\Invoice;

class DashboardCadenceOverviewWidget extends Widget
{
    protected string $view = 'filament.widgets.dashboard-cadence-overview-widget';

    protected int | string | array $columnSpan = [
        'default' => 'full',
        'sm' => 'full',
        'md' => 'full',
        'lg' => 'full',
        'xl' => 'full',
    ];

    protected static ?int $sort = 3;

    public function getLeaseExpirationsData(): array
    {
        $activeExpiring = TenancyAgreement::where('status', 'active')
            ->whereNotNull('end_date')
            ->whereBetween('end_date', [now()->subDays(7), now()->addDays(90)]);

        $criticalCount = (clone $activeExpiring)
            ->whereBetween('end_date', [now()->subDays(7), now()->addDays(30)])
            ->count();

        $upcomingCount = (clone $activeExpiring)
            ->whereBetween('end_date', [now()->addDays(31), now()->addDays(60)])
            ->count();

        $pipelineCount = (clone $activeExpiring)
            ->whereBetween('end_date', [now()->addDays(61), now()->addDays(90)])
            ->count();

        $rentAtRisk = (float) (clone $activeExpiring)->sum('rent_amount');

        $imminentLeases = (clone $activeExpiring)
            ->with(['property', 'tenants'])
            ->orderBy('end_date', 'asc')
            ->limit(3)
            ->get();

        return [
            'total_expiring' => $activeExpiring->count(),
            'critical_count' => $criticalCount,
            'upcoming_count' => $upcomingCount,
            'pipeline_count' => $pipelineCount,
            'rent_at_risk' => $rentAtRisk,
            'imminent_leases' => $imminentLeases,
        ];
    }

    public function getCadenceData(): array
    {
        $now = now();
        $currentMonth = $now->month;
        $currentYear = $now->year;

        // 1. Rent Demands (Invoices for Tenancy Agreements)
        $lastRentInvoice = Invoice::where('reference_type', TenancyAgreement::class)
            ->latest('created_at')
            ->first();

        $thisMonthInvoicesCount = Invoice::where('reference_type', TenancyAgreement::class)
            ->whereMonth('created_at', $currentMonth)
            ->whereYear('created_at', $currentYear)
            ->count();

        $hasBilledCurrentMonth = $thisMonthInvoicesCount > 0;
        
        $nextRentGenDate = $hasBilledCurrentMonth 
            ? $now->copy()->addMonth()->startOfMonth() 
            : $now->copy()->startOfMonth();

        $daysUntilNextRent = (int) $now->diffInDays($nextRentGenDate, false);

        // 2. Owner Payouts
        $lastPayout = OwnerPayout::latest('created_at')->first();
        $lastCompletedPayout = OwnerPayout::where('status', 'completed')
            ->latest('processed_at')
            ->first() ?? $lastPayout;

        $pendingPayoutsQuery = OwnerPayout::whereIn('status', ['draft', 'pending']);
        $pendingPayoutsCount = (clone $pendingPayoutsQuery)->count();
        $pendingPayoutsTotal = (float) (clone $pendingPayoutsQuery)->sum('amount');

        // Recommended Payout window: 5th - 10th of the month
        $payoutWindowStart = Carbon::create($currentYear, $currentMonth, 5);
        $payoutWindowEnd = Carbon::create($currentYear, $currentMonth, 10);

        if ($now->day < 5) {
            $payoutStatus = 'Upcoming';
            $payoutStatusText = 'Window opens ' . $payoutWindowStart->format('d M');
            $payoutBadgeColor = 'info';
            $recommendedPayoutDate = $payoutWindowStart->format('d M Y');
        } elseif ($now->day <= 10) {
            $payoutStatus = 'Active Window';
            $payoutStatusText = 'Window open (5th–10th ' . $now->format('M') . ')';
            $payoutBadgeColor = 'success';
            $recommendedPayoutDate = 'Now (5th–10th ' . $now->format('M') . ')';
        } else {
            $nextWindowStart = Carbon::create($currentYear, $currentMonth, 5)->addMonth();
            $payoutStatus = 'Next Cycle';
            $payoutStatusText = 'Next window: ' . $nextWindowStart->format('d M Y');
            $payoutBadgeColor = 'gray';
            $recommendedPayoutDate = $nextWindowStart->format('05 M Y') . ' – ' . $nextWindowStart->copy()->addDays(5)->format('10 M Y');
        }

        return [
            'rent' => [
                'last_generated_at' => $lastRentInvoice?->created_at,
                'last_invoice_number' => $lastRentInvoice?->invoice_number,
                'last_amount' => $lastRentInvoice ? (float) ($lastRentInvoice->grand_total / 100) : null,
                'this_month_count' => $thisMonthInvoicesCount,
                'is_current_month_billed' => $hasBilledCurrentMonth,
                'recommended_date' => $nextRentGenDate->format('d M Y'),
                'days_remaining' => $daysUntilNextRent,
                'status_label' => $hasBilledCurrentMonth ? 'Billed for ' . $now->format('M Y') : 'Cycle Due Now',
                'status_color' => $hasBilledCurrentMonth ? '#059669' : '#d97706',
                'status_bg' => $hasBilledCurrentMonth ? '#ecfdf5' : '#fffbeb',
            ],
            'payouts' => [
                'last_disbursed_at' => $lastCompletedPayout?->processed_at ?? $lastCompletedPayout?->created_at,
                'last_amount' => $lastCompletedPayout?->amount,
                'pending_count' => $pendingPayoutsCount,
                'pending_total' => $pendingPayoutsTotal,
                'status' => $payoutStatus,
                'status_text' => $payoutStatusText,
                'recommended_window' => $recommendedPayoutDate,
                'badge_color' => $payoutBadgeColor,
            ],
        ];
    }
}
