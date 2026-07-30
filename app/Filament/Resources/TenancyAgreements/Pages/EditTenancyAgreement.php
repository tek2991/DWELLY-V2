<?php

namespace App\Filament\Resources\TenancyAgreements\Pages;

use App\Filament\Resources\TenancyAgreements\TenancyAgreementResource;
use App\Domain\Agreement\Services\TenancyAgreementPdfService;
use App\Domain\Agreement\Services\TenancyAgreementDocxService;
use App\Domain\Agreement\Actions\ActivateTenancyAction;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditTenancyAgreement extends EditRecord
{
    protected static string $resource = TenancyAgreementResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $primaryRole = $this->getRecord()->roles()->where('is_primary', true)->first();
        if ($primaryRole) {
            $data['primary_tenant_id'] = $primaryRole->party_id;
        }
        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (isset($data['primary_tenant_id'])) {
            $primaryTenantId = $data['primary_tenant_id'];
            unset($data['primary_tenant_id']);

            $this->getRecord()->roles()->updateOrCreate(
                ['is_primary' => true],
                ['party_id' => $primaryTenantId, 'role_type' => 'Primary Tenant']
            );
        }
        return $data;
    }

    public function generateDraftDocuments(): void
    {
        $record = $this->getRecord();
        try {
            app(TenancyAgreementPdfService::class)->saveDraftPdf($record);
            app(TenancyAgreementDocxService::class)->saveDraftDocx($record);

            Notification::make()
                ->title('Draft Documents Generated')
                ->body('Leave & License Agreement PDF and Word (.docx) drafts have been generated and updated.')
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
        $media = $record->getFirstMedia('draft_pdf');

        if ($media && file_exists($media->getPath())) {
            return response()->download($media->getPath(), $media->file_name);
        }

        $pdfService = app(TenancyAgreementPdfService::class);
        $binary = $pdfService->generatePdf($record);
        $filename = 'Tenancy_Agreement_Draft_' . ($record->code ?? $record->id) . '.pdf';
        return response()->streamDownload(fn() => print($binary), $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    public function downloadDraftWord()
    {
        $record = $this->getRecord();
        $media = $record->getFirstMedia('draft_word');

        if ($media && file_exists($media->getPath())) {
            return response()->download($media->getPath(), $media->file_name);
        }

        $docxService = app(TenancyAgreementDocxService::class);
        $binary = $docxService->generateDocx($record);
        $filename = 'Tenancy_Agreement_Draft_' . ($record->code ?? $record->id) . '.docx';
        return response()->streamDownload(fn() => print($binary), $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ]);
    }

    public function activateTenancy(): void
    {
        $record = $this->getRecord();
        try {
            app(ActivateTenancyAction::class)->execute($record, auth()->user());

            Notification::make()
                ->title('Tenancy Activated Successfully')
                ->body('Tenancy agreement is now active and property status has been set to occupied.')
                ->success()
                ->send();

            $this->refreshFormData([]);
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Activation Failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): ?string
    {
        return null;
    }

    public function getSubheading(): string | \Illuminate\Support\HtmlString | null
    {
        $record = $this->getRecord();
        if (!$record || !$record->property) {
            return null;
        }

        $property = $record->property;
        $code = $property->code;
        $name = $property->building_name ?? $property->address_line_1 ?? 'Property #' . $property->id;
        $propertyUrl = \App\Filament\Resources\Properties\PropertyResource::getUrl('edit', ['record' => $property]);

        $codeBadge = $code
            ? '<span style="display: inline-flex; align-items: center; font-family: monospace; font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 4px; background-color: rgba(37, 99, 235, 0.15); color: #2563eb;">' . e($code) . '</span>'
            : '';

        return new \Illuminate\Support\HtmlString(
            '<div style="display: flex; align-items: center; gap: 8px; font-size: 14px; font-weight: 500; margin-top: 4px;">' .
                $codeBadge .
                '<span style="font-weight: 700; font-size: 15px; color: inherit;">' . e($name) . '</span>' .
                '<a href="' . e($propertyUrl) . '" target="_blank" style="display: inline-flex; align-items: center; gap: 4px; padding: 2px 8px; border-radius: 4px; background-color: rgba(37, 99, 235, 0.1); color: #2563eb; font-size: 12px; font-weight: 600; text-decoration: none;" title="View Property Profile">' .
                    'View Property Profile &rarr;' .
                '</a>' .
            '</div>'
        );
    }
}
