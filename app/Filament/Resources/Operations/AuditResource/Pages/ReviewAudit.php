<?php

namespace App\Filament\Resources\Operations\AuditResource\Pages;

use App\Filament\Resources\Operations\AuditResource;
use App\Domain\Audit\Models\Audit;
use Filament\Resources\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Filament\Actions\Action;

class ReviewAudit extends Page
{
    protected static string $resource = AuditResource::class;

    protected string $view = 'filament.resources.operations.audit-resource.pages.review-audit';

    public Audit $record;

    public function mount(Audit $record): void
    {
        $this->record = $record->load('categories.items.evidence', 'reviewer', 'inspector', 'completedBy', 'property');
        
        // Let's use the service to make sure if we need to set in review, we do it properly
        app(\App\Domain\Audit\Services\AuditReviewService::class)->evaluateWorkflowState($this->record);
    }

    public function getTitle(): string | Htmlable
    {
        return 'Review Audit: ' . ($this->record->property->code ?? $this->record->id);
    }

    public function getSubheading(): string | \Illuminate\Contracts\Support\Htmlable | null
    {
        $inspectorName = $this->record->inspector?->name ?? $this->record->completedBy?->name ?? 'Unassigned';
        $property = $this->record->property;
        $propertyName = $property?->building_name ?? $property?->address_line_1 ?? 'Property #' . $property?->id;

        return new \Illuminate\Support\HtmlString(
            '<div style="font-size: 0.875rem; color: rgba(107, 114, 128, 1); display: flex; align-items: center; gap: 1rem; flex-wrap: wrap; margin-top: 0.25rem;">' .
                '<span>🏢 Property: <strong>' . e($propertyName) . '</strong></span>' .
                '<span>👤 Inspector: <strong>' . e($inspectorName) . '</strong></span>' .
                '<span>📋 Audit #: <strong>' . e($this->record->audit_number) . '</strong></span>' .
            '</div>'
        );
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('backToInspection')
                ->label('Inspection Page')
                ->color('gray')
                ->icon('heroicon-o-clipboard-document-list')
                ->url(fn () => AuditResource::getUrl('inspect', ['record' => $this->record])),
        ];
    }
}
