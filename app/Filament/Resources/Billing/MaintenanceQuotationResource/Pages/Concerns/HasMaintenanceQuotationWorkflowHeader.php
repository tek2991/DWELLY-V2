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
            Action::make('viewQuotationPdf')
                ->label('📄 View Quotation PDF')
                ->icon('heroicon-o-document-text')
                ->color('primary')
                ->button()
                ->size('sm')
                ->visible(fn () => $record && ($record->hasMedia('generated_quote_pdf') || $record->hasMedia('quote_pdf')))
                ->url(fn () => $record ? route('billing.quotation.pdf', ['quote' => $record->id]) : '#')
                ->openUrlInNewTab(),

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

            Action::make('viewWorkOrderPdf')
                ->extraAttributes(['style' => 'display: none !important;'])
                ->modalHeading(fn (?array $arguments = null) => $arguments['title'] ?? 'Contractor Work Order Document')
                ->modalWidth('7xl')
                ->modalSubmitActionLabel('Download Work Order PDF')
                ->modalCancelActionLabel('Close')
                ->modalContent(function (?array $arguments = null) {
                    $vendorQuoteId = $arguments['vendorQuoteId'] ?? null;
                    if (! $vendorQuoteId) {
                        return null;
                    }

                    $vendorQuote = \App\Domain\Maintenance\Models\MaintenanceVendorQuote::find($vendorQuoteId);
                    if (! $vendorQuote) {
                        return null;
                    }

                    $service = app(\App\Domain\Maintenance\Services\MaintenanceWorkOrderPdfService::class);
                    $media = $service->generatePdf($vendorQuote, $this->getRecord());

                    if (! $media || ! file_exists($media->getPath())) {
                        return null;
                    }

                    return view('components.pdf-viewer-raw', [
                        'path' => $media->getPath(),
                    ]);
                })
                ->action(function (?array $arguments = null) {
                    $vendorQuoteId = $arguments['vendorQuoteId'] ?? null;
                    if (! $vendorQuoteId) {
                        return null;
                    }

                    $vendorQuote = \App\Domain\Maintenance\Models\MaintenanceVendorQuote::find($vendorQuoteId);
                    if (! $vendorQuote) {
                        return null;
                    }

                    $service = app(\App\Domain\Maintenance\Services\MaintenanceWorkOrderPdfService::class);
                    $media = $service->generatePdf($vendorQuote, $this->getRecord());

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

        if (blank($data['tax_id'] ?? null)) {
            $data['tax_id'] = \Tek2991\Accounting\Models\Tax::where('name', 'like', '%18%')->value('id') ?? \Tek2991\Accounting\Models\Tax::first()?->id;
        }

        if (blank($data['valid_until'] ?? null)) {
            $data['valid_until'] = now()->addDays((int) \App\Domain\Shared\Services\SettingService::get('financials.default_quotation_validity_days', 14))->format('Y-m-d');
        }

        if (empty($data['awarded_vendor_quote_ids'])) {
            $record = $this->getRecord();
            if ($record) {
                $data['awarded_vendor_quote_ids'] = ! empty($record->awarded_vendor_quote_ids)
                    ? (array) $record->awarded_vendor_quote_ids
                    : (array) $record->getIncludedVendorQuoteIds();
            }
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $record = $this->getRecord();
        if ($record && $record->status === 'archived') {
            throw new \RuntimeException('Cannot save modifications to an archived quotation.');
        }

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
            } elseif ($payer === 'split' || $payer === 'dwelly_invoice_split') {
                $ownerAmt = (float) ($data['owner_amount'] ?? $record?->owner_amount ?? 0);
                $tenantAmt = (float) ($data['tenant_amount'] ?? $record?->tenant_amount ?? 0);
                if ($ownerAmt + $tenantAmt > 0 && abs(($ownerAmt + $tenantAmt) - $total) < 0.01) {
                    $data['owner_amount'] = $ownerAmt;
                    $data['tenant_amount'] = $tenantAmt;
                } else {
                    $half = round($total / 2, 2);
                    $data['owner_amount'] = $half;
                    $data['tenant_amount'] = round($total - $half, 2);
                }
                $data['dwelly_amount'] = 0.00;
            }
        }

        return $data;
    }

    protected function getFormActions(): array
    {
        $record = $this->getRecord();
        if ($record && in_array($record->status, ['approved', 'archived', 'settled'])) {
            return [];
        }

        return [
            $this->getSaveFormAction(),
        ];
    }

    protected function afterSave(): void
    {
        $record = $this->getRecord();
        if ($record) {
            $record->refresh();
            $record->recalculateTotals();
            if ($record->maintenanceRequest) {
                $record->maintenanceRequest->update([
                    'current_client_quote_id' => $record->id,
                    'quotation_amount' => $record->total_amount,
                ]);
                $record->maintenanceRequest->syncQuotationTotals();
            }
        }
    }

    protected function getRedirectUrl(): ?string
    {
        return null;
    }
}
