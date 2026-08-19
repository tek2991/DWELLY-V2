<?php

namespace App\Filament\Resources\Billing\MaintenanceQuotationResource\Pages\Concerns;

use App\Domain\Maintenance\Models\MaintenanceClientQuote;
use App\Domain\Maintenance\Services\MaintenanceQuotationPdfService;
use App\Filament\Resources\Billing\MaintenanceQuotationResource;
use App\Filament\Resources\Billing\MaintenanceQuotationResource\Schemas\MaintenanceQuotationForm;
use App\Filament\Resources\Operations\MaintenanceRequestResource;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;

trait HasMaintenanceQuotationWorkflowHeader
{
    public function getHeader(): ?View
    {
        /** @var MaintenanceClientQuote|null $record */
        $record = $this->getRecord();
        if (! $record) {
            return null;
        }

        return view('filament.resources.maintenance-quotations.header', [
            'breadcrumbs' => filament()->hasBreadcrumbs() ? $this->getBreadcrumbs() : [],
            'heading' => $this->getHeading(),
            'actions' => $this->getCachedHeaderActions(),
            'actionsAlignment' => $this->getHeaderActionsAlignment(),
            'record' => $record,
            'headerHtml' => MaintenanceQuotationForm::getWorkflowHeaderHtml($record),
        ]);
    }

    protected function getHeaderActions(): array
    {
        /** @var MaintenanceClientQuote|null $record */
        $record = $this->getRecord();

        return [
            Action::make('viewTicket')
                ->label('View Operational Ticket')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('gray')
                ->button()
                ->size('sm')
                ->visible(fn () => filled($record?->maintenance_request_id))
                ->url(fn () => $record?->maintenanceRequest ? MaintenanceRequestResource::getUrl('edit', ['record' => $record->maintenanceRequest]) : '#')
                ->openUrlInNewTab(),

            Action::make('viewHistoryPdf')
                ->extraAttributes(['style' => 'display: none !important;'])
                ->modalHeading(fn (?array $arguments = null) => $arguments['title'] ?? 'View Quotation Document')
                ->modalWidth('7xl')
                ->modalSubmitActionLabel('Download PDF')
                ->modalCancelActionLabel('Close')
                ->modalContent(function (?array $arguments = null) {
                    $mediaId = $arguments['mediaId'] ?? null;
                    if (! $mediaId) {
                        return null;
                    }

                    $media = \Spatie\MediaLibrary\MediaCollections\Models\Media::find($mediaId);
                    if (! $media || ! file_exists($media->getPath())) {
                        return null;
                    }

                    return view('components.pdf-viewer-raw', [
                        'path' => $media->getPath(),
                    ]);
                })
                ->action(function (?array $arguments = null) {
                    $mediaId = $arguments['mediaId'] ?? null;
                    if (! $mediaId) {
                        return null;
                    }

                    $media = \Spatie\MediaLibrary\MediaCollections\Models\Media::find($mediaId);
                    if (! $media || ! file_exists($media->getPath())) {
                        return null;
                    }

                    return response()->download($media->getPath(), $media->file_name);
                }),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        if (blank($data['margin_percentage'] ?? null)) {
            $data['margin_percentage'] = (float) \App\Domain\Shared\Services\SettingService::get('financials.default_margin_percentage', 10.00);
        }

        if (blank($data['gst_percentage'] ?? null)) {
            $data['gst_percentage'] = (float) \App\Domain\Shared\Services\SettingService::get('financials.default_gst_percentage', 18.00);
        }

        if (blank($data['valid_until'] ?? null)) {
            $data['valid_until'] = now()->addDays((int) \App\Domain\Shared\Services\SettingService::get('financials.default_quotation_validity_days', 14))->format('Y-m-d');
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $record = $this->getRecord();
        $request = $record?->maintenanceRequest;

        if ($request) {
            $payer = $request->payer_type?->value ?? (string) $request->payer_type;
            $total = (float) ($data['total_amount'] ?? $record?->total_amount ?? 0);

            if ($payer === 'tenant' || $payer === 'dwelly_invoice_tenant') {
                $data['tenant_amount'] = $total;
                $data['owner_amount'] = 0.00;
                $data['dwelly_amount'] = 0.00;
            } elseif ($payer === 'owner' || $payer === 'dwelly_invoice_owner') {
                $data['owner_amount'] = $total;
                $data['tenant_amount'] = 0.00;
                $data['dwelly_amount'] = 0.00;
            } elseif ($payer === 'dwelly' || $payer === 'dwelly_absorbs') {
                $data['dwelly_amount'] = $total;
                $data['owner_amount'] = 0.00;
                $data['tenant_amount'] = 0.00;
            }
        }

        return $data;
    }

    protected function afterSave(): void
    {
        $record = $this->getRecord();
        if ($record && $record->maintenanceRequest) {
            $record->maintenanceRequest->update([
                'current_client_quote_id' => $record->id,
                'quotation_amount' => $record->total_amount,
            ]);
            $record->maintenanceRequest->syncQuotationTotals();
        }
    }

    protected function getRedirectUrl(): ?string
    {
        return null;
    }
}
