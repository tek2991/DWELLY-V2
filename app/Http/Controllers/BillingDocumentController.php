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
}
