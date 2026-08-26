<?php

namespace App\Filament\Resources\Operations\TenantDeboardingResource\Pages\Concerns;

use App\Domain\Agreement\Enums\DeboardingStatus;
use App\Domain\Agreement\Models\TenantDeboarding;
use Illuminate\Contracts\View\View;
use Illuminate\Support\HtmlString;

trait HasDeboardingWorkflowHeader
{
    public function getHeader(): ?View
    {
        $record = $this->getRecord();
        if (! $record) {
            return null;
        }

        return view('filament.resources.operations.tenant-deboardings.header', [
            'breadcrumbs' => filament()->hasBreadcrumbs() ? $this->getBreadcrumbs() : [],
            'heading' => $this->getHeading(),
            'actions' => $this->getCachedHeaderActions(),
            'actionsAlignment' => $this->getHeaderActionsAlignment(),
            'record' => $record,
            'headerHtml' => $this->getWorkflowHeaderHtml($record),
        ]);
    }

    public function getWorkflowHeaderHtml(TenantDeboarding $record): string
    {
        $status = $record->status instanceof DeboardingStatus ? $record->status : DeboardingStatus::tryFrom((string) $record->status);
        $statusLabel = $status?->getLabel() ?? ucfirst((string) $record->status);
        $statusColor = $status?->getColor() ?? 'gray';

        $prop = $record->property;
        $tenant = $record->tenant;
        $agreement = $record->tenancyAgreement;

        $badgeColors = match ($statusColor) {
            'success' => 'background: #dcfce7; color: #15803d; border-color: #bbf7d0;',
            'warning', 'amber' => 'background: #fef3c7; color: #b45309; border-color: #fde68a;',
            'danger' => 'background: #fee2e2; color: #b91c1c; border-color: #fecaca;',
            'info', 'sky' => 'background: #e0f2fe; color: #0369a1; border-color: #bae6fd;',
            'purple' => 'background: #f3e8ff; color: #6b21a8; border-color: #e9d5ff;',
            default => 'background: #f1f5f9; color: #334155; border-color: #cbd5e1;',
        };

        return '<div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 16px; padding: 14px 18px; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">' .
            '<div>' .
                '<div style="display: flex; align-items: center; gap: 10px;">' .
                    '<span style="font-size: 1.15rem; font-weight: 800; color: #0f172a; letter-spacing: -0.01em;">Deboarding #' . e($record->code) . '</span>' .
                    '<span style="display: inline-flex; align-items: center; padding: 3px 10px; font-size: 0.75rem; font-weight: 700; border-radius: 9999px; border: 1px solid; ' . $badgeColors . '">' . e($statusLabel) . '</span>' .
                '</div>' .
                '<div style="display: flex; flex-wrap: wrap; gap: 16px; font-size: 0.82rem; color: #64748b; margin-top: 6px;">' .
                    '<span>🏢 <strong>Property:</strong> ' . e($prop?->building_name ?? $prop?->code ?? 'N/A') . '</span>' .
                    '<span>👤 <strong>Tenant:</strong> ' . e($tenant?->display_name ?? 'N/A') . '</span>' .
                    '<span>📄 <strong>Agreement:</strong> ' . e($agreement?->code ?? 'N/A') . '</span>' .
                    '<span>📅 <strong>Notice:</strong> ' . e($record->notice_date?->format('d M Y') ?? 'N/A') . '</span>' .
                    '<span>🎯 <strong>Target Vacate:</strong> ' . e($record->target_vacating_date?->format('d M Y') ?? 'N/A') . '</span>' .
                '</div>' .
            '</div>' .
        '</div>';
    }
}
