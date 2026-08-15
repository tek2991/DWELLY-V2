<?php

namespace App\Filament\Resources\Billing\MaintenanceQuotationResource\Pages;

use App\Domain\Maintenance\Enums\MaintenanceStatus;
use App\Domain\Maintenance\Services\MaintenanceBillingService;
use App\Filament\Resources\Billing\MaintenanceQuotationResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditMaintenanceQuotation extends EditRecord
{
    protected static string $resource = MaintenanceQuotationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('viewHistoryPdf')
                ->extraAttributes(['style' => 'display: none !important;']) // Hide from header visually, but keep mountable
                ->modalHeading(fn (?array $arguments = null) => $arguments['title'] ?? 'View Quotation Document')
                ->modalWidth('7xl')
                ->modalSubmitActionLabel('Download PDF')
                ->modalCancelActionLabel('Close')
                ->modalContent(function (?array $arguments = null) {
                    $mediaId = $arguments['mediaId'] ?? null;
                    if (!$mediaId) return null;

                    $media = \Spatie\MediaLibrary\MediaCollections\Models\Media::find($mediaId);
                    if (!$media || !file_exists($media->getPath())) return null;

                    return view('components.pdf-viewer-raw', [
                        'path' => $media->getPath(),
                    ]);
                })
                ->action(function (?array $arguments = null) {
                    $mediaId = $arguments['mediaId'] ?? null;
                    if (!$mediaId) return null;

                    $media = \Spatie\MediaLibrary\MediaCollections\Models\Media::find($mediaId);
                    if (!$media || !file_exists($media->getPath())) return null;

                    return response()->download($media->getPath(), $media->file_name);
                }),

            Action::make('viewTicket')
                ->label('View Operational Ticket')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('gray')
                ->button()
                ->size('sm')
                ->visible(fn () => filled($this->record->maintenance_request_id))
                ->url(fn () => \App\Filament\Resources\Operations\MaintenanceRequestResource::getUrl('edit', ['record' => $this->record->maintenanceRequest]))
                ->openUrlInNewTab(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $request = $this->record->maintenanceRequest;
        if ($request) {
            $payer = $request->payer_type?->value ?? (string)$request->payer_type;
            $total = (float)($data['total_amount'] ?? $this->record->total_amount);

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
        if ($this->record->maintenanceRequest) {
            $this->record->maintenanceRequest->update([
                'current_client_quote_id' => $this->record->id,
                'quotation_amount' => $this->record->total_amount,
            ]);
            $this->record->maintenanceRequest->syncQuotationTotals();
        }
    }

    protected function getRedirectUrl(): ?string
    {
        return null;
    }
}
