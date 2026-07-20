<?php

namespace App\Filament\Resources\Properties\Widgets;

use App\Domain\Property\Models\Property;
use App\Domain\Audit\Models\Audit;
use App\Domain\Audit\Enums\AuditStatus;
use App\Filament\Resources\Operations\AuditResource;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Model;
use Filament\Actions\Action;

class PropertyAuditWidget extends Widget
{
    protected string $view = 'filament.resources.properties.widgets.property-audit-widget';

    public ?Model $record = null;

    protected int | string | array $columnSpan = 'full';

    public function getLatestAudit(): ?Audit
    {
        return Audit::where('property_id', $this->record->id)
            ->orderBy('created_at', 'desc')
            ->first();
    }

    public function getLatestApprovedAudit(): ?Audit
    {
        return Audit::where('property_id', $this->record->id)
            ->where('status', AuditStatus::APPROVED)
            ->orderBy('created_at', 'desc')
            ->first();
    }

    public function getAuditStats(): array
    {
        $audits = Audit::where('property_id', $this->record->id)->get();
        return [
            'total' => $audits->count(),
            'approved' => $audits->where('status', AuditStatus::APPROVED)->count(),
            'in_progress' => $audits->whereIn('status', [AuditStatus::IN_PROGRESS, AuditStatus::PENDING_REVIEW, AuditStatus::IN_REVIEW, AuditStatus::PARTIALLY_APPROVED])->count(),
            'draft' => $audits->where('status', AuditStatus::DRAFT)->count(),
        ];
    }

    public function startAuditAction(): Action
    {
        $isDisabled = empty($this->record->code) || $this->record->onboardingProject?->status !== 'Activated';
        return Action::make('startAudit')
            ->label('Start Audit')
            ->button()
            ->disabled($isDisabled)
            ->tooltip($isDisabled ? 'Complete onboarding and generate property code first.' : null)
            ->url(AuditResource::getUrl('create', ['property_id' => $this->record->id]));
    }

    public function continueAuditAction(): Action
    {
        return Action::make('continueAudit')
            ->label('Continue Audit')
            ->button()
            ->color('info')
            ->url(fn (): string => AuditResource::getUrl('edit', ['record' => $this->getLatestAudit()->id]));
    }

    public function viewReportAction(): Action
    {
        return Action::make('viewReport')
            ->label('View Report')
            ->button()
            ->color('success')
            ->url(fn (): string => AuditResource::getUrl('edit', ['record' => $this->getLatestApprovedAudit()->id]));
    }
}
