<?php

namespace App\Domain\Maintenance\Services;

use App\Domain\Maintenance\Models\MaintenanceClientQuote;
use Barryvdh\DomPDF\Facade\Pdf;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class MaintenanceQuotationPdfService
{
    public function generatePdf(MaintenanceClientQuote $quote): Media
    {
        $quote->refresh();
        $quote->loadMissing(['maintenanceRequest.property', 'maintenanceRequest.owner', 'maintenanceRequest.tenant', 'items']);

        if ($quote->items()->count() === 0) {
            throw new \Exception("Cannot generate quotation PDF: At least one quotation line item is required.");
        }

        // Recalculate financial totals (subtotal, margin, tax, grand total)
        $quote->recalculateTotals();
        $quote->refresh();

        // If a PDF already exists, increment version for revision history
        if ($quote->hasMedia('generated_quote_pdf')) {
            $quote->increment('version');
            $quote->refresh();
        }

        $pdf = Pdf::loadView('pdf.maintenance_quotation', [
            'quote' => $quote,
            'ticket' => $quote->maintenanceRequest,
        ]);

        $tempFileName = $quote->quote_number . '-v' . $quote->version . '.pdf';
        $tempPath = sys_get_temp_dir() . '/' . $tempFileName;
        $pdf->save($tempPath);

        $media = $quote->addMedia($tempPath)
            ->withCustomProperties([
                'version' => $quote->version,
                'generated_at' => now()->toDateTimeString(),
                'generated_by' => auth()->id(),
                'generated_by_name' => auth()->user()?->name ?? 'System',
            ])
            ->toMediaCollection('generated_quote_pdf');

        $quote->update([
            'generated_at' => now(),
            'status' => $quote->status === 'draft' ? 'pending_approval' : $quote->status,
        ]);

        if ($quote->maintenanceRequest) {
            $quote->maintenanceRequest->update([
                'quotation_amount' => $quote->total_amount,
            ]);
            $quote->maintenanceRequest->syncQuotationTotals();
        }

        return $media;
    }
}
