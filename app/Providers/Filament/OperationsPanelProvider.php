<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class OperationsPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('operations')
            ->path('operations')
            ->login()
            ->colors([
                'primary' => Color::Blue,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverClusters(in: app_path('Filament/Clusters'), for: 'App\Filament\Clusters')
            ->navigationGroups([
                'Portfolio & Operations',
                'Billing & Finance',
                'Sales & CRM',
                'Settings',
            ])
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                \App\Filament\Pages\Dashboard::class,
            ])
            ->widgets([
                \App\Filament\Widgets\DashboardQuickActionsWidget::class,
                \App\Filament\Widgets\PropertyStagesOverviewWidget::class,
                \App\Filament\Widgets\DashboardActionAlertsWidget::class,
                \App\Filament\Widgets\DashboardCadenceOverviewWidget::class,
                \App\Filament\Widgets\MonthlyRevenueExpenseChartWidget::class,
                \App\Filament\Widgets\PropertyGrowthChartWidget::class,
                \App\Filament\Widgets\TenantTurnoverChartWidget::class,
                \App\Filament\Widgets\UrgentMaintenanceWidget::class,
                \App\Filament\Widgets\PendingAuditsWidget::class,
                \App\Filament\Widgets\PendingTasksWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->renderHook(
                \Filament\View\PanelsRenderHook::USER_MENU_BEFORE,
                fn (): string => \Illuminate\Support\Facades\Blade::render('@livewire(\'branch-selector\')')
            )
            ->renderHook(
                \Filament\View\PanelsRenderHook::BODY_END,
                fn (): string => \Illuminate\Support\Facades\Blade::render('
                    @vite([\'resources/js/annotation-editor.js\'])
                    <script src="' . asset('js/fslightbox.js') . '"></script>
                ')
            );
    }
}
