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
        $this->record = $record->load('categories.items.evidence', 'reviewer');
        
        // Let's use the service to make sure if we need to set in review, we do it properly
        app(\App\Domain\Audit\Services\AuditReviewService::class)->evaluateWorkflowState($this->record);
    }

    public function getTitle(): string | Htmlable
    {
        return 'Review Audit: ' . ($this->record->property->code ?? $this->record->id);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('requestChanges')
                ->label('Request Changes')
                ->color('danger')
                ->icon('heroicon-o-arrow-uturn-left')
                ->visible(fn () => $this->record->canRequestChanges() && $this->record->items()->where('status', \App\Domain\Audit\Enums\ItemStatus::REJECTED)->exists())
                ->action(function () {
                    app(\App\Domain\Audit\Services\AuditReviewService::class)->requestChanges($this->record);
                    
                    \Filament\Notifications\Notification::make()
                        ->title('Changes requested')
                        ->success()
                        ->send();
                        
                    return redirect(route('filament.operations.pages.review-queue'));
                }),
        ];
    }
}
