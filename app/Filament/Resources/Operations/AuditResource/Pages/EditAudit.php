<?php

namespace App\Filament\Resources\Operations\AuditResource\Pages;

use App\Filament\Resources\Operations\AuditResource;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;

class EditAudit extends EditRecord
{
    protected static string $resource = AuditResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('inspect')
                ->label('Perform Inspection')
                ->icon('heroicon-o-clipboard-document-check')
                ->color('primary')
                ->disabled(fn () => blank($this->getRecord()->inspector_id))
                ->tooltip(fn () => blank($this->getRecord()->inspector_id) ? 'Please assign an inspector before performing inspection.' : null)
                ->url(fn () => blank($this->getRecord()->inspector_id) ? null : AuditResource::getUrl('inspect', ['record' => $this->getRecord()])),

            Action::make('review')
                ->label('Open Review Page')
                ->icon('heroicon-o-clipboard-document-check')
                ->color('warning')
                ->visible(fn () => $this->getRecord()->canReview())
                ->url(fn () => AuditResource::getUrl('review', ['record' => $this->getRecord()])),

            Action::make('pdfReport')
                ->label('PDF Report')
                ->icon('heroicon-o-document-arrow-down')
                ->color('gray')
                ->modalHeading(fn () => "Inspection Report - {$this->getRecord()->audit_number}")
                ->modalWidth(\Filament\Support\Enums\Width::SevenExtraLarge)
                ->modalContent(fn () => view('components.audit-report-modal', ['audit' => $this->getRecord()]))
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Close'),

            Actions\DeleteAction::make(),
        ];
    }

    public function getSubheading(): string | \Illuminate\Support\HtmlString | null
    {
        $record = $this->getRecord();
        if (!$record) {
            return null;
        }

        return new \Illuminate\Support\HtmlString(
            view('components.audit-header', ['audit' => $record->loadMissing(['property', 'inspector'])])->render()
        );
    }
}
