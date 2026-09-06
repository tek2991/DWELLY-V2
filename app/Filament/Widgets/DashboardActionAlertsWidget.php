<?php

namespace App\Filament\Widgets;

use App\Domain\Agreement\Enums\DeboardingStatus;
use App\Domain\Agreement\Models\TenancyAgreement;
use App\Domain\Agreement\Models\TenantDeboarding;
use App\Domain\Audit\Enums\AuditStatus;
use App\Domain\Audit\Models\Audit;
use App\Domain\Finance\Models\OwnerPayout;
use App\Domain\Maintenance\Enums\MaintenancePriority;
use App\Domain\Maintenance\Enums\MaintenanceStatus;
use App\Domain\Maintenance\Models\MaintenanceRequest;
use App\Domain\Task\Enums\TaskStatus;
use App\Domain\Task\Models\Task;
use App\Filament\Resources\Operations\AuditResource;
use App\Filament\Resources\Operations\MaintenanceRequestResource;
use App\Filament\Resources\Operations\TaskResource;
use App\Filament\Resources\Operations\TenantDeboardingResource;
use Filament\Widgets\Widget;
use Tek2991\Accounting\Enums\InvoiceStatus;
use Tek2991\Accounting\Models\Invoice;

class DashboardActionAlertsWidget extends Widget
{
    protected string $view = 'filament.widgets.dashboard-action-alerts-widget';

    protected int | string | array $columnSpan = [
        'default' => 'full',
        'sm' => 'full',
        'md' => 'full',
        'lg' => 'full',
        'xl' => 'full',
    ];

    protected static ?int $sort = 2;

    public function getAlertsData(): array
    {
        // 1. Maintenance
        $openMaintenance = MaintenanceRequest::whereNotIn('status', [
            MaintenanceStatus::RESOLVED,
            MaintenanceStatus::CLOSED,
            MaintenanceStatus::CANCELLED,
        ]);
        $maintenanceCount = (clone $openMaintenance)->count();
        $urgentMaintenanceCount = (clone $openMaintenance)
            ->whereIn('priority', [MaintenancePriority::EMERGENCY, MaintenancePriority::HIGH])
            ->count();

        // 2. Tasks / Works
        $openTasks = Task::whereNotIn('status', [
            TaskStatus::COMPLETED,
            TaskStatus::CANCELLED,
        ]);
        $tasksCount = (clone $openTasks)->count();
        $overdueTasksCount = (clone $openTasks)
            ->whereNotNull('due_date')
            ->where('due_date', '<', now())
            ->count();

        // 3. Audits pending review
        $pendingAuditsCount = Audit::whereIn('status', [
            AuditStatus::PENDING_REVIEW,
            AuditStatus::IN_REVIEW,
            AuditStatus::PARTIALLY_APPROVED,
        ])->count();

        // 4. Rent Collections (unpaid or partially paid)
        $unpaidInvoices = Invoice::whereIn('status', [
            InvoiceStatus::Sent,
            InvoiceStatus::PartiallyPaid,
        ])->where('balance_due', '>', 0);
        $pendingRentCount = (clone $unpaidInvoices)->count();
        $pendingRentTotal = (float) ((clone $unpaidInvoices)->sum('balance_due') / 100);

        // 5. Owner Payouts (pending or draft)
        $pendingPayoutsQuery = OwnerPayout::whereIn('status', ['draft', 'pending']);
        $pendingPayoutsCount = (clone $pendingPayoutsQuery)->count();
        $pendingPayoutsTotal = (float) (clone $pendingPayoutsQuery)->sum('amount');

        // 6. Active Deboardings
        $activeDeboardingsCount = TenantDeboarding::whereNotIn('status', [
            DeboardingStatus::COMPLETED,
            DeboardingStatus::CANCELLED,
        ])->count();

        // 7. Expiring Leases (< 60 days)
        $expiringLeasesQuery = TenancyAgreement::where('status', 'active')
            ->whereNotNull('end_date')
            ->whereBetween('end_date', [now()->subDays(7), now()->addDays(60)]);
        $expiringLeasesCount = (clone $expiringLeasesQuery)->count();
        $expiringRentTotal = (float) (clone $expiringLeasesQuery)->sum('rent_amount');

        return [
            'maintenance' => [
                'count' => $maintenanceCount,
                'urgent_count' => $urgentMaintenanceCount,
                'url' => MaintenanceRequestResource::getUrl(),
            ],
            'tasks' => [
                'count' => $tasksCount,
                'overdue_count' => $overdueTasksCount,
                'url' => TaskResource::getUrl(),
            ],
            'audits' => [
                'count' => $pendingAuditsCount,
                'url' => AuditResource::getUrl(),
            ],
            'rent' => [
                'count' => $pendingRentCount,
                'total' => $pendingRentTotal,
                'url' => url('/operations/bulk-generate-monthly-rent'),
            ],
            'payouts' => [
                'count' => $pendingPayoutsCount,
                'total' => $pendingPayoutsTotal,
                'url' => url('/operations/bulk-generate-owner-payouts'),
            ],
            'deboardings' => [
                'count' => $activeDeboardingsCount,
                'url' => TenantDeboardingResource::getUrl(),
            ],
            'expiring_leases' => [
                'count' => $expiringLeasesCount,
                'total' => $expiringRentTotal,
                'url' => url('/operations/operations-dashboard?tab=renewals'),
            ],
        ];
    }
}
