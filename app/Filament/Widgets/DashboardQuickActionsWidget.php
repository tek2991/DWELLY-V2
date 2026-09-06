<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Operations\AuditResource;
use App\Filament\Resources\Operations\MaintenanceRequestResource;
use App\Filament\Resources\Properties\PropertyResource;
use App\Filament\Resources\TenancyAgreements\TenancyAgreementResource;
use Filament\Widgets\Widget;

class DashboardQuickActionsWidget extends Widget
{
    protected string $view = 'filament.widgets.dashboard-quick-actions-widget';

    protected int | string | array $columnSpan = [
        'default' => 'full',
        'sm' => 'full',
        'md' => 'full',
        'lg' => 'full',
        'xl' => 'full',
    ];

    protected static ?int $sort = 0;

    public function getQuickActions(): array
    {
        return [
            [
                'label' => 'Add Property',
                'description' => 'Onboard new unit',
                'icon' => 'heroicon-m-building-office-2',
                'color' => '#0284c7', // Sky
                'bg_light' => '#f0f9ff',
                'border_color' => '#bae6fd',
                'url' => PropertyResource::getUrl('create'),
            ],
            [
                'label' => 'New Tenancy',
                'description' => 'Draft lease agreement',
                'icon' => 'heroicon-m-document-text',
                'color' => '#059669', // Emerald
                'bg_light' => '#ecfdf5',
                'border_color' => '#a7f3d0',
                'url' => TenancyAgreementResource::getUrl('create'),
            ],
            [
                'label' => 'Log Repair Ticket',
                'description' => 'Maintenance issue',
                'icon' => 'heroicon-m-wrench-screwdriver',
                'color' => '#dc2626', // Red
                'bg_light' => '#fef2f2',
                'border_color' => '#fecaca',
                'url' => MaintenanceRequestResource::getUrl('create'),
            ],
            [
                'label' => 'Schedule Audit',
                'description' => 'Property inspection',
                'icon' => 'heroicon-m-clipboard-document-check',
                'color' => '#d97706', // Amber
                'bg_light' => '#fffbeb',
                'border_color' => '#fde68a',
                'url' => AuditResource::getUrl('create'),
            ],
            [
                'label' => 'Generate Rent',
                'description' => 'Monthly billing cycle',
                'icon' => 'heroicon-m-banknotes',
                'color' => '#10b981', // Emerald
                'bg_light' => '#f0fdf4',
                'border_color' => '#bbf7d0',
                'url' => url('/operations/bulk-generate-monthly-rent'),
            ],
            [
                'label' => 'Disburse Payouts',
                'description' => 'Batch owner transfer',
                'icon' => 'heroicon-m-arrow-up-tray',
                'color' => '#2563eb', // Blue
                'bg_light' => '#eff6ff',
                'border_color' => '#bfdbfe',
                'url' => url('/operations/bulk-generate-owner-payouts'),
            ],
            [
                'label' => 'Renewals Console',
                'description' => 'Expiring lease pipeline',
                'icon' => 'heroicon-m-arrow-path',
                'color' => '#7c3aed', // Purple
                'bg_light' => '#f5f3ff',
                'border_color' => '#ddd6fe',
                'url' => url('/operations/operations-dashboard?tab=renewals'),
            ],
        ];
    }
}
