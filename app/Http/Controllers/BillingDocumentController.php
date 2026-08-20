<?php

namespace App\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf;
use Tek2991\Accounting\Models\Bill;
use Tek2991\Accounting\Models\Invoice;
use Tek2991\Accounting\Models\Payment;
use Tek2991\Accounting\Services\InvoiceService;

class BillingDocumentController extends Controller
{
    public function downloadInvoice(Invoice $invoice, InvoiceService $invoiceService)
    {
        $invoice->loadMissing(['items.tax', 'items.item', 'contact']);
        $pdf = Pdf::loadView('accounting::pdf.invoice', ['invoice' => $invoice]);
        return $pdf->stream("Invoice-{$invoice->invoice_number}.pdf");
    }

    public function downloadReceipt(Invoice $invoice, Payment $payment)
    {
        $invoice->loadMissing(['contact']);
        $pdf = Pdf::loadView('pdf.invoice_receipt', [
            'invoice' => $invoice,
            'payment' => $payment,
        ]);
        return $pdf->stream("Receipt-{$payment->id}.pdf");
    }

    public function downloadBill(Bill $bill)
    {
        $bill->loadMissing(['items', 'contact']);
        $pdf = Pdf::loadView('accounting::pdf.bill', ['bill' => $bill]);
        return $pdf->stream("Bill-{$bill->bill_number}.pdf");
    }

    public function streamQuotation(\App\Domain\Maintenance\Models\MaintenanceClientQuote $quote)
    {
        $mediaId = request()->query('media_id');
        $media = $mediaId
            ? $quote->media()->find($mediaId)
            : ($quote->getMedia('generated_quote_pdf')->last() ?? $quote->getFirstMedia('quote_pdf'));

        if ($media && file_exists($media->getPath())) {
            return response()->file($media->getPath(), [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="' . $media->file_name . '"',
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => '0',
            ]);
        }

        $quote->loadMissing(['maintenanceRequest.property', 'maintenanceRequest.owner', 'maintenanceRequest.tenant', 'items']);
        $pdf = Pdf::loadView('pdf.maintenance_quotation', [
            'quote' => $quote,
            'ticket' => $quote->maintenanceRequest,
        ]);
        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "inline; filename=\"{$quote->quote_number}.pdf\"",
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    public function downloadQuotation(\App\Domain\Maintenance\Models\MaintenanceClientQuote $quote)
    {
        $mediaId = request()->query('media_id');
        $media = $mediaId
            ? $quote->media()->find($mediaId)
            : ($quote->getMedia('generated_quote_pdf')->last() ?? $quote->getFirstMedia('quote_pdf'));

        if ($media && file_exists($media->getPath())) {
            return response()->download($media->getPath(), $media->file_name, [
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => '0',
            ]);
        }

        $quote->loadMissing(['maintenanceRequest.property', 'maintenanceRequest.owner', 'maintenanceRequest.tenant', 'items']);
        $pdf = Pdf::loadView('pdf.maintenance_quotation', [
            'quote' => $quote,
            'ticket' => $quote->maintenanceRequest,
        ]);
        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$quote->quote_number}.pdf\"",
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    public function streamWorkOrder(\App\Domain\Maintenance\Models\MaintenanceVendorQuote $vendorQuote)
    {
        $media = $vendorQuote->getFirstMedia('work_order_letter_pdf') ?? $vendorQuote->getFirstMedia('work_order_pdf');

        if (! $media) {
            $media = app(\App\Domain\Maintenance\Services\MaintenanceWorkOrderPdfService::class)->generatePdf($vendorQuote);
        }

        if ($media && file_exists($media->getPath())) {
            return response()->file($media->getPath(), [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="' . $media->file_name . '"',
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => '0',
            ]);
        }

        abort(404, 'Work Order PDF not found.');
    }

    public function downloadWorkOrder(\App\Domain\Maintenance\Models\MaintenanceVendorQuote $vendorQuote)
    {
        $media = $vendorQuote->getFirstMedia('work_order_letter_pdf') ?? $vendorQuote->getFirstMedia('work_order_pdf');

        if (! $media) {
            $media = app(\App\Domain\Maintenance\Services\MaintenanceWorkOrderPdfService::class)->generatePdf($vendorQuote);
        }

        if ($media && file_exists($media->getPath())) {
            return response()->download($media->getPath(), $media->file_name, [
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => '0',
            ]);
        }

        abort(404, 'Work Order PDF not found.');
    }
}
