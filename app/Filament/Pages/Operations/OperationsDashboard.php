<?php

namespace App\Filament\Pages\Operations;

use App\Domain\Agreement\Enums\DeboardingStatus;
use App\Domain\Agreement\Models\TenancyAgreement;
use App\Domain\Agreement\Models\TenantDeboarding;
use App\Domain\Audit\Enums\AuditStatus;
use App\Domain\Audit\Models\Audit;
use App\Domain\Maintenance\Enums\MaintenancePriority;
use App\Domain\Maintenance\Enums\MaintenanceStatus;
use App\Domain\Maintenance\Models\MaintenanceRequest;
use App\Domain\Property\Models\Property;
use App\Domain\Task\Enums\TaskStatus;
use App\Domain\Task\Models\Task;
use App\Filament\Resources\Operations\AuditResource;
use App\Filament\Resources\Operations\MaintenanceRequestResource;
use App\Filament\Resources\Operations\TaskResource;
use App\Filament\Resources\Operations\TenantDeboardingResource;
use App\Filament\Resources\Properties\PropertyResource;
use App\Filament\Resources\TenancyAgreements\TenancyAgreementResource;
use BackedEnum;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class OperationsDashboard extends Page
{
    protected string $view = 'filament.pages.operations.operations-dashboard';

    protected static BackedEnum|string|null $navigationIcon = Heroicon::OutlinedPresentationChartLine;

    protected static \UnitEnum|string|null $navigationGroup = 'Properties & Leasing';

    protected static ?string $navigationLabel = 'Operations Dashboard';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'Operations Command Center';

    public string $activeTab = 'pipeline';

    public string $search = '';

    public ?string $propertyFilter = null;

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('new_tenancy')
                ->label('New Tenancy / Lease')
                ->icon('heroicon-m-document-text')
                ->color('success')
                ->url(fn (): string => TenancyAgreementResource::getUrl('create')),

            Action::make('new_maintenance')
                ->label('New Maintenance Ticket')
                ->icon('heroicon-m-wrench-screwdriver')
                ->color('danger')
                ->url(fn (): string => MaintenanceRequestResource::getUrl('create')),

            Action::make('schedule_audit')
                ->label('Schedule Audit')
                ->icon('heroicon-m-clipboard-document-check')
                ->color('warning')
                ->url(fn (): string => AuditResource::getUrl('create')),

            Action::make('new_task')
                ->label('Create Work Order / Task')
                ->icon('heroicon-m-plus')
                ->color('primary')
                ->url(fn (): string => TaskResource::getUrl('create')),
        ];
    }

    public function getTabCounts(): array
    {
        $pipelineCount = Property::whereIn('status', ['Onboarding', 'onboarding', 'Vacant', 'vacant'])->count();

        $maintenanceCount = MaintenanceRequest::whereNotIn('status', [
            MaintenanceStatus::RESOLVED,
            MaintenanceStatus::CLOSED,
            MaintenanceStatus::CANCELLED,
        ])->count();

        $auditsCount = Audit::whereIn('status', [
            AuditStatus::PENDING_REVIEW,
            AuditStatus::IN_REVIEW,
            AuditStatus::PARTIALLY_APPROVED,
        ])->count();

        $moveInsCount = TenancyAgreement::where('status', 'pending_move_in')
            ->orWhereBetween('start_date', [now()->subDays(7), now()->addDays(30)])
            ->count();

        $deboardingsCount = TenantDeboarding::whereNotIn('status', [
            DeboardingStatus::COMPLETED,
            DeboardingStatus::CANCELLED,
        ])->count();

        $renewalsCount = TenancyAgreement::where('status', 'active')
            ->whereNotNull('end_date')
            ->whereBetween('end_date', [now()->subDays(7), now()->addDays(90)])
            ->count();

        return [
            'pipeline' => $pipelineCount,
            'maintenance' => $maintenanceCount,
            'audits' => $auditsCount,
            'moveins_moveouts' => $moveInsCount + $deboardingsCount,
            'renewals' => $renewalsCount,
        ];
    }

    public function getRenewalsData(): array
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

        $rentAtRiskTotal = (float) (clone $activeExpiring)->sum('rent_amount');

        $expiringAgreements = (clone $activeExpiring)
            ->with(['property.localityRef.city', 'tenants', 'deboarding'])
            ->orderBy('end_date', 'asc')
            ->limit(15)
            ->get();

        return [
            'total_expiring' => $activeExpiring->count(),
            'critical_count' => $criticalCount,
            'upcoming_count' => $upcomingCount,
            'pipeline_count' => $pipelineCount,
            'rent_at_risk' => $rentAtRiskTotal,
            'agreements' => $expiringAgreements,
        ];
    }

    public function getPipelineData(): array
    {
        $total = Property::count();
        $occupied = Property::whereIn('status', ['Occupied', 'occupied'])->count();
        $vacant = Property::whereIn('status', ['Vacant', 'vacant'])->count();
        $onboarding = Property::whereIn('status', ['Onboarding', 'onboarding'])->count();
        $maintenance = Property::whereIn('status', ['Maintenance', 'maintenance', 'under_maintenance'])->count();
        $archived = Property::whereIn('status', ['Archived', 'archived'])->count();

        $activeUnits = $occupied + $vacant + $maintenance;
        $occupancyRate = $activeUnits > 0 ? round(($occupied / $activeUnits) * 100, 1) : 0.0;

        // Onboarding properties needing action
        $onboardingList = Property::whereIn('status', ['Onboarding', 'onboarding'])
            ->with(['localityRef.city', 'owner'])
            ->latest('updated_at')
            ->limit(8)
            ->get();

        // Vacant properties ready for showings / lease
        $vacantList = Property::whereIn('status', ['Vacant', 'vacant'])
            ->with(['localityRef.city', 'financialTerms'])
            ->latest('updated_at')
            ->limit(8)
            ->get();

        return [
            'total' => $total,
            'occupied' => $occupied,
            'vacant' => $vacant,
            'onboarding' => $onboarding,
            'maintenance' => $maintenance,
            'archived' => $archived,
            'occupancy_rate' => $occupancyRate,
            'onboarding_properties' => $onboardingList,
            'vacant_properties' => $vacantList,
        ];
    }

    public function getMaintenanceData(): array
    {
        $openQuery = MaintenanceRequest::whereNotIn('status', [
            MaintenanceStatus::RESOLVED,
            MaintenanceStatus::CLOSED,
            MaintenanceStatus::CANCELLED,
        ]);

        $p1Count = (clone $openQuery)->where('priority', MaintenancePriority::EMERGENCY)->count();
        $p2Count = (clone $openQuery)->where('priority', MaintenancePriority::HIGH)->count();
        $p3Count = (clone $openQuery)->where('priority', MaintenancePriority::MEDIUM)->count();
        $p4Count = (clone $openQuery)->where('priority', MaintenancePriority::LOW)->count();

        // SLA breached (> 48 hours without resolution)
        $slaBreachedCount = (clone $openQuery)->where('created_at', '<', now()->subHours(48))->count();

        // Active tickets list
        $tickets = (clone $openQuery)
            ->with(['property', 'vendor'])
            ->orderByRaw("CASE 
                WHEN priority = 'emergency' THEN 1 
                WHEN priority = 'high' THEN 2 
                WHEN priority = 'medium' THEN 3 
                ELSE 4 
            END")
            ->latest('created_at')
            ->limit(10)
            ->get();

        // Active Tasks / Field work orders
        $now = now()->toDateTimeString();
        $tasks = Task::whereNotIn('status', [TaskStatus::COMPLETED, TaskStatus::CANCELLED])
            ->with(['property', 'assignedTo'])
            ->orderByRaw("CASE 
                WHEN due_date IS NOT NULL AND due_date < '{$now}' THEN 1 
                ELSE 2 
            END")
            ->latest('created_at')
            ->limit(10)
            ->get();

        return [
            'total_open' => $openQuery->count(),
            'p1_emergency' => $p1Count,
            'p2_high' => $p2Count,
            'p3_medium' => $p3Count,
            'p4_low' => $p4Count,
            'sla_breached' => $slaBreachedCount,
            'tickets' => $tickets,
            'tasks' => $tasks,
        ];
    }

    public function getAuditsData(): array
    {
        $pendingReviews = Audit::whereIn('status', [
            AuditStatus::PENDING_REVIEW,
            AuditStatus::IN_REVIEW,
            AuditStatus::PARTIALLY_APPROVED,
        ])
            ->with(['property', 'inspector', 'reviewer'])
            ->latest('updated_at')
            ->limit(10)
            ->get();

        $thisMonthCount = Audit::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        $completedThisMonth = Audit::whereIn('status', [AuditStatus::APPROVED, AuditStatus::COMPLETED])
            ->whereMonth('updated_at', now()->month)
            ->whereYear('updated_at', now()->year)
            ->count();

        return [
            'pending_review_count' => $pendingReviews->count(),
            'total_this_month' => $thisMonthCount,
            'completed_this_month' => $completedThisMonth,
            'pending_audits' => $pendingReviews,
        ];
    }

    public function getMoveInsMoveOutsData(): array
    {
        // Upcoming Move-Ins (next 30 days)
        $upcomingMoveIns = TenancyAgreement::whereBetween('start_date', [now()->subDays(7), now()->addDays(30)])
            ->with(['property', 'tenants'])
            ->orderBy('start_date', 'asc')
            ->limit(10)
            ->get();

        // Active Deboardings
        $activeDeboardings = TenantDeboarding::whereNotIn('status', [
            DeboardingStatus::COMPLETED,
            DeboardingStatus::CANCELLED,
        ])
            ->with(['property', 'tenancyAgreement', 'tenant'])
            ->latest('updated_at')
            ->limit(10)
            ->get();

        return [
            'upcoming_move_ins' => $upcomingMoveIns,
            'active_deboardings' => $activeDeboardings,
            'move_ins_count' => $upcomingMoveIns->count(),
            'deboardings_count' => $activeDeboardings->count(),
        ];
    }
}
