<?php

namespace App\Filament\Resources\TenancyAgreements\Pages;

use App\Domain\Agreement\Services\TenancyAgreementDocxService;
use App\Domain\Agreement\Services\TenancyAgreementPdfService;
use App\Filament\Resources\TenancyAgreements\Pages\Concerns\HasTenancyWorkflowHeader;
use App\Filament\Resources\TenancyAgreements\Schemas\TenancyAgreementForm;
use App\Filament\Resources\TenancyAgreements\TenancyAgreementResource;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ManageAgreementDocuments extends EditRecord
{
    use HasTenancyWorkflowHeader;

    protected static string $resource = TenancyAgreementResource::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $navigationLabel = '4. Drafts & Signed Docs';

    protected static ?string $title = 'Tenancy Agreement – Drafts & Signed Agreement';

    public function form(Schema $schema): Schema
    {
        return TenancyAgreementForm::configureDocumentsForm($schema);
    }

    public function generateDraftDocuments(): void
    {
        $record = $this->getRecord();
        try {
            app(TenancyAgreementPdfService::class)->saveDraftPdf($record);
            app(TenancyAgreementDocxService::class)->saveDraftDocx($record);

            Notification::make()
                ->title('Draft Documents Generated')
                ->body('Leave & License Agreement PDF and Word (.docx) drafts have been compiled and updated.')
                ->success()
                ->send();

            $this->refreshFormData(['draft_pdf', 'draft_word']);
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Draft Generation Failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function downloadDraftPdf()
    {
        $record = $this->getRecord();
        $pdfService = app(TenancyAgreementPdfService::class);
        $pdfService->saveDraftPdf($record);
        $record->refresh();

        $media = $record->getFirstMedia('draft_pdf');
        if ($media && file_exists($media->getPath())) {
            return response()->download($media->getPath(), $media->file_name);
        }

        $binary = $pdfService->generatePdf($record);
        $filename = 'Tenancy_Agreement_Draft_'.($record->code ?? $record->id).'.pdf';

        return response()->streamDownload(fn () => print ($binary), $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    public function downloadDraftWord()
    {
        $record = $this->getRecord();
        $docxService = app(TenancyAgreementDocxService::class);
        $docxService->saveDraftDocx($record);
        $record->refresh();

        $media = $record->getFirstMedia('draft_word');
        if ($media && file_exists($media->getPath())) {
            return response()->download($media->getPath(), $media->file_name);
        }

        $binary = $docxService->generateDocx($record);
        $filename = 'Tenancy_Agreement_Draft_'.($record->code ?? $record->id).'.docx';

        return response()->streamDownload(fn () => print ($binary), $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ]);
    }

    protected function getFormActions(): array
    {
        if (in_array($this->getRecord()?->status, ['active', 'vacated'])) {
            return [];
        }

        return parent::getFormActions();
    }

    protected function getRedirectUrl(): ?string
    {
        return null;
    }
}
