<?php

namespace App\Domain\Maintenance\Services;

use App\Domain\Maintenance\Models\MaintenanceClientQuote;
use App\Domain\Maintenance\Models\MaintenanceVendorQuote;
use Barryvdh\DomPDF\Facade\Pdf;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class MaintenanceWorkOrderPdfService
{
    public function generatePdf(MaintenanceVendorQuote $vendorQuote, ?MaintenanceClientQuote $clientQuote = null): Media
    {
        $vendorQuote->refresh();
        $vendorQuote->loadMissing([
            'vendor.vendorProfile.trade',
            'vendorTrade',
            'maintenanceRequest.property.localityRef',
            'maintenanceRequest.owner',
            'maintenanceRequest.tenant',
        ]);

        $ticket = $vendorQuote->maintenanceRequest;

        if (!$clientQuote && $ticket) {
            $clientQuote = $ticket->currentClientQuote ?? $ticket->clientQuotes()->latest()->first();
        }

        // Ensure work order number exists
        if (blank($vendorQuote->work_order_number)) {
            $woSuffix = strtoupper(substr($clientQuote?->quote_number ?: uniqid(), -5)) . '-' . substr($vendorQuote->id, -4);
            $vendorQuote->update([
                'work_order_number' => "WO-{$woSuffix}",
                'work_order_issued_at' => $vendorQuote->work_order_issued_at ?: now(),
                'is_awarded' => true,
                'status' => 'awarded',
            ]);
        }

        $pdf = Pdf::loadView('pdf.maintenance_work_order', [
            'vendorQuote' => $vendorQuote,
            'ticket' => $ticket,
            'clientQuote' => $clientQuote,
        ]);

        $tempFileName = ($vendorQuote->work_order_number ?: ('WO-' . substr($vendorQuote->id, -6))) . '.pdf';
        $tempPath = sys_get_temp_dir() . '/' . $tempFileName;
        $pdf->save($tempPath);

        // Clear previous work order PDF if exists
        $vendorQuote->clearMediaCollection('work_order_letter_pdf');

        $media = $vendorQuote->addMedia($tempPath)
            ->withCustomProperties([
                'work_order_number' => $vendorQuote->work_order_number,
                'issued_at' => $vendorQuote->work_order_issued_at?->toDateTimeString() ?? now()->toDateTimeString(),
                'generated_by' => auth()->id(),
                'generated_by_name' => auth()->user()?->name ?? 'System',
            ])
            ->toMediaCollection('work_order_letter_pdf');

        return $media;
    }
}
