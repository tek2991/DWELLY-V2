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

use Filament\Actions\Action;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class ManageAgreementDocuments extends EditRecord
{
    use HasTenancyWorkflowHeader {
        getHeaderActions as getWorkflowHeaderActions;
    }

    protected static string $resource = TenancyAgreementResource::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $navigationLabel = '4. Drafts & Signed Docs';

    protected static ?string $title = 'Tenancy Agreement – Drafts & Signed Agreement';

    public function form(Schema $schema): Schema
    {
        return TenancyAgreementForm::configureDocumentsForm($schema);
    }

    protected function getHeaderActions(): array
    {
        return array_merge(
            $this->getWorkflowHeaderActions(),
            [
                Action::make('viewHistoryPdf')
                    ->extraAttributes(['style' => 'display: none !important;'])
                    ->modalHeading(fn (array $arguments) => $arguments['title'] ?? 'View Document')
                    ->modalWidth('7xl')
                    ->modalSubmitActionLabel('Download Document')
                    ->modalCancelActionLabel('Close')
                    ->modalContent(function (array $arguments) {
                        $mediaId = $arguments['mediaId'] ?? null;
                        if (! $mediaId) {
                            return null;
                        }

                        $media = Media::find($mediaId);
                        if (! $media) {
                            return null;
                        }

                        return view('components.pdf-viewer-raw', [
                            'path' => $media->getPath(),
                        ]);
                    })
                    ->action(function (array $arguments) {
                        $mediaId = $arguments['mediaId'] ?? null;
                        if (! $mediaId) {
                            return;
                        }

                        $media = Media::find($mediaId);
                        if (! $media || ! file_exists($media->getPath())) {
                            return;
                        }

                        return response()->download($media->getPath(), $media->file_name);
                    }),

                TenancyAgreementResource::getDraftAgreementAction('draftAgreement')
                    ->extraAttributes(['style' => 'display: none !important;']),
            ]
        );
    }

    public function generateDraftDocuments(): void
    {
        $record = $this->getRecord();
        $user = auth()->user();

        if ($user && ! $user->can('generateDraft', $record)) {
            Notification::make()
                ->title('Unauthorized')
                ->body('You do not have permission to compile agreement draft documents.')
                ->danger()
                ->send();

            return;
        }

        try {
            if ($record->hasMedia('draft_pdf') || $record->hasMedia('draft_word')) {
                $record->increment('version');
                $record->refresh();
            }

            app(TenancyAgreementPdfService::class)->saveDraftPdf($record);
            app(TenancyAgreementDocxService::class)->saveDraftDocx($record);
            $record->refresh();

            Notification::make()
                ->title('Draft Documents Generated')
                ->body("Leave & License Agreement PDF and Word (.docx) drafts (version v{$record->version}) have been compiled and updated.")
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

    public function previewDraftPdf()
    {
        $record = $this->getRecord();
        $latestPdf = $record->getLatestDraftPdf();

        if (! $latestPdf || ! file_exists($latestPdf->getPath())) {
            $this->generateDraftDocuments();
            $record->refresh();
            $latestPdf = $record->getLatestDraftPdf();
        }

        if ($latestPdf && file_exists($latestPdf->getPath())) {
            return $this->mountAction('viewHistoryPdf', [
                'mediaId' => $latestPdf->id,
                'title' => "Leave & License Agreement Draft — #{$record->code} (v{$record->version})",
            ]);
        }

        Notification::make()
            ->title('Draft PDF Unavailable')
            ->body('Could not locate or generate the draft PDF.')
            ->danger()
            ->send();
    }

    public function downloadDraftPdf()
    {
        $record = $this->getRecord();
        $latestPdf = $record->getLatestDraftPdf();

        if (! $latestPdf || ! file_exists($latestPdf->getPath())) {
            $pdfService = app(TenancyAgreementPdfService::class);
            $latestPdf = $pdfService->saveDraftPdf($record);
            $record->refresh();
        }

        if ($latestPdf && file_exists($latestPdf->getPath())) {
            $filename = ($record->code ?? 'Draft') . '-draft-v' . ($record->version ?? 1) . '.pdf';
            return response()->download($latestPdf->getPath(), $filename);
        }

        $pdfService = app(TenancyAgreementPdfService::class);
        $binary = $pdfService->generatePdf($record);
        $filename = 'Tenancy_Agreement_Draft_'.($record->code ?? $record->id).'.pdf';

        return response()->streamDownload(fn () => print ($binary), $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    public function downloadDraftWord()
    {
        $record = $this->getRecord();
        $latestWord = $record->getLatestDraftWord();

        if (! $latestWord || ! file_exists($latestWord->getPath())) {
            $docxService = app(TenancyAgreementDocxService::class);
            $latestWord = $docxService->saveDraftDocx($record);
            $record->refresh();
        }

        if ($latestWord && file_exists($latestWord->getPath())) {
            $filename = ($record->code ?? 'Draft') . '-draft-v' . ($record->version ?? 1) . '.docx';
            return response()->download($latestWord->getPath(), $filename);
        }

        $docxService = app(TenancyAgreementDocxService::class);
        $binary = $docxService->generateDocx($record);
        $filename = 'Tenancy_Agreement_Draft_'.($record->code ?? $record->id).'.docx';

        return response()->streamDownload(fn () => print ($binary), $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ]);
    }

    public function downloadMedia(int $mediaId)
    {
        $media = Media::find($mediaId);
        if (! $media || ! file_exists($media->getPath())) {
            Notification::make()
                ->title('File Not Found')
                ->body('The requested file is not available on disk.')
                ->danger()
                ->send();

            return;
        }

        return response()->download($media->getPath(), $media->file_name);
    }

    protected function getFormActions(): array
    {
        $record = $this->getRecord();
        $user = auth()->user();

        if (in_array($record?->status, ['active', 'vacated', 'terminated', 'deboarded'])) {
            return [];
        }

        if ($user && ! $user->can('update', $record)) {
            return [];
        }

        return parent::getFormActions();
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $record = $this->getRecord();
        $individual = $record->primaryTenant?->party?->individual;

        if ($individual) {
            $data['new_tenant'] = [
                'aadhaar_number' => $individual->aadhaar_number,
                'pan_number' => $individual->pan_number,
                'voter_id' => $individual->voter_id,
            ];
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $record = $this->getRecord();
        $primaryParty = $record->primaryTenant?->party;

        if ($primaryParty && ! empty($data['new_tenant'])) {
            $tenantData = $data['new_tenant'];
            $updateData = array_filter([
                'aadhaar_number' => $tenantData['aadhaar_number'] ?? null,
                'pan_number' => $tenantData['pan_number'] ?? null,
                'voter_id' => $tenantData['voter_id'] ?? null,
            ]);
            if (! empty($updateData)) {
                $primaryParty->individual()->updateOrCreate([], $updateData);
            }
        }

        unset($data['new_tenant'], $data['kyc_documents_upload']);

        return $data;
    }

    protected function getRedirectUrl(): ?string
    {
        return null;
    }
}
