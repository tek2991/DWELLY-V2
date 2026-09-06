<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\DashboardActionAlertsWidget;
use App\Filament\Widgets\DashboardCadenceOverviewWidget;
use App\Filament\Widgets\DashboardQuickActionsWidget;
use App\Filament\Widgets\MonthlyRevenueExpenseChartWidget;
use App\Filament\Widgets\PendingAuditsWidget;
use App\Filament\Widgets\PendingTasksWidget;
use App\Filament\Widgets\PropertyGrowthChartWidget;
use App\Filament\Widgets\PropertyStagesOverviewWidget;
use App\Filament\Widgets\TenantTurnoverChartWidget;
use App\Filament\Widgets\UrgentMaintenanceWidget;
use BackedEnum;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Support\Icons\Heroicon;

class Dashboard extends BaseDashboard
{
    protected static BackedEnum|string|null $navigationIcon = Heroicon::OutlinedHome;

    protected static ?string $navigationLabel = 'Main Dashboard';

    protected static ?int $navigationSort = -1;

    protected static ?string $title = 'Main Executive Dashboard';

    public function getColumns(): int|array
    {
        return [
            'default' => 1,
            'md' => 2,
            'xl' => 3,
        ];
    }

    public function getWidgets(): array
    {
        return [
            DashboardQuickActionsWidget::class,
            PropertyStagesOverviewWidget::class,
            DashboardActionAlertsWidget::class,
            DashboardCadenceOverviewWidget::class,
            MonthlyRevenueExpenseChartWidget::class,
            PropertyGrowthChartWidget::class,
            TenantTurnoverChartWidget::class,
            UrgentMaintenanceWidget::class,
            PendingAuditsWidget::class,
            PendingTasksWidget::class,
        ];
    }
}
