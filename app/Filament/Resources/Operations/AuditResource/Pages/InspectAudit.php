<?php

namespace App\Filament\Resources\Operations\AuditResource\Pages;

use App\Filament\Resources\Operations\AuditResource;
use App\Domain\Audit\Models\Audit;
use Filament\Resources\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Filament\Actions\Action;

class InspectAudit extends Page
{
    protected static string $resource = AuditResource::class;

    protected string $view = 'filament.resources.operations.audit-resource.pages.inspect-audit';

    public Audit $record;

    public function mount(Audit $record): void
    {
        if (empty($record->inspector_id)) {
            \Filament\Notifications\Notification::make()
                ->title('Inspector Required')
                ->body('An inspector must be assigned before performing or inspecting this audit.')
                ->warning()
                ->send();

            $this->redirect(AuditResource::getUrl('edit', ['record' => $record]));
            return;
        }

        $this->record = $record->load('categories.items.source', 'property');
    }

    public function getTitle(): string | Htmlable
    {
        $code = $this->record->property->code ?? $this->record->audit_number ?? $this->record->id;
        return 'Audit Inspection: ' . $code;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('pdfReport')
                ->label('PDF Report')
                ->icon('heroicon-o-document-arrow-down')
                ->color('gray')
                ->modalHeading(fn () => "Inspection Report - {$this->record->audit_number}")
                ->modalWidth(\Filament\Support\Enums\Width::SevenExtraLarge)
                ->modalContent(fn () => view('components.audit-report-modal', ['audit' => $this->record]))
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Close'),

            Action::make('editDetails')
                ->label('Edit Audit Details')
                ->color('gray')
                ->icon('heroicon-o-pencil-square')
                ->url(fn () => AuditResource::getUrl('edit', ['record' => $this->record])),
        ];
    }
}
