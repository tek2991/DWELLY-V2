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
use Tek2991\Accounting\AccountingPlugin;

class AccountingPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('accounting')
            ->path('accounting')
            ->login()
            ->colors([
                'primary' => Color::Amber,
            ])
            ->plugin(AccountingPlugin::make())
            ->databaseNotifications()
            ->resources([
                \App\Filament\Resources\OwnerPayouts\OwnerPayoutResource::class,
            ])
            ->discoverResources(in: app_path('Filament/Accounting/Resources'), for: 'App\Filament\Accounting\Resources')
            ->discoverPages(in: app_path('Filament/Accounting/Pages'), for: 'App\Filament\Accounting\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Accounting/Widgets'), for: 'App\Filament\Accounting\Widgets')
            ->widgets([
                AccountWidget::class,
                FilamentInfoWidget::class,
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
                \Filament\View\PanelsRenderHook::HEAD_END,
                fn (): string => \Illuminate\Support\Facades\Blade::render('
                    <style>
                        .fi-topbar {
                            background: linear-gradient(90deg, #e0f2fe 0%, #ebf8ff 50%, #f0f9ff 100%) !important;
                            border-bottom: 1px solid #bae6fd !important;
                            box-shadow: 0 1px 3px 0 rgba(14, 165, 233, 0.08) !important;
                        }
                        .dark .fi-topbar {
                            background: linear-gradient(90deg, #0b2239 0%, #0d2a46 50%, #0f172a 100%) !important;
                            border-bottom: 1px solid #1e3a5f !important;
                            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.3) !important;
                        }
                        .fi-topbar .fi-input-wrapper {
                            background-color: rgba(255, 255, 255, 0.9) !important;
                            border-color: #bae6fd !important;
                        }
                        .dark .fi-topbar .fi-input-wrapper {
                            background-color: rgba(15, 23, 42, 0.8) !important;
                            border-color: #1e3a5f !important;
                        }
                        .fi-topbar .fi-icon-btn {
                            color: #0369a1 !important;
                        }
                        .dark .fi-topbar .fi-icon-btn {
                            color: #7dd3fc !important;
                        }
                        .fi-topbar-accounting-badge {
                            display: inline-flex;
                            align-items: center;
                            gap: 0.375rem;
                            padding: 0.25rem 0.75rem;
                            font-size: 0.75rem;
                            font-weight: 700;
                            letter-spacing: 0.05em;
                            text-transform: uppercase;
                            border-radius: 9999px;
                            background-color: #bae6fd;
                            color: #0369a1;
                            border: 1px solid #7dd3fc;
                            box-shadow: 0 1px 2px 0 rgba(14, 165, 233, 0.15);
                        }
                        .dark .fi-topbar-accounting-badge {
                            background-color: #0c2a4d;
                            color: #7dd3fc;
                            border-color: #1e4b7a;
                            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.3);
                        }
                    </style>
                ')
            )
            ->renderHook(
                \Filament\View\PanelsRenderHook::USER_MENU_BEFORE,
                fn (): string => \Illuminate\Support\Facades\Blade::render('
                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                        <span class="fi-topbar-accounting-badge">
                            <svg style="width: 0.875rem; height: 0.875rem; flex-shrink: 0;" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                                <path fill-rule="evenodd" d="M3 6a3 3 0 0 1 3-3h12a3 3 0 0 1 3 3v12a3 3 0 0 1-3 3H6a3 3 0 0 1-3-3V6Zm4.5 9a.75.75 0 0 0 0 1.5h9a.75.75 0 0 0 0-1.5h-9Zm0-3.5a.75.75 0 0 0 0 1.5h9a.75.75 0 0 0 0-1.5h-9Zm0-3.5a.75.75 0 0 0 0 1.5h9a.75.75 0 0 0 0-1.5h-9Z" clip-rule="evenodd" />
                            </svg>
                            Accounting
                        </span>
                        @livewire(\'branch-selector\')
                    </div>
                ')
            );
    }
}
