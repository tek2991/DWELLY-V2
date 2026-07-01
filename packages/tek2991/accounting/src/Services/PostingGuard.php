<?php

namespace Tek2991\Accounting\Services;

use Tek2991\Accounting\Exceptions\AlreadyPostedException;
use Tek2991\Accounting\Exceptions\InvalidOperationException;
use Tek2991\Accounting\Models\Invoice;
use Tek2991\Accounting\Models\Bill;
use Tek2991\Accounting\Enums\InvoiceStatus;
use Tek2991\Accounting\Enums\BillStatus;

class PostingGuard
{
    /**
     * Assert an invoice is in a state that can be posted.
     * Throws InvalidOperationException if not.
     */
    public function assertInvoicePostable(Invoice $invoice): void
    {
        if ($invoice->transaction_id !== null) {
            throw new AlreadyPostedException("Invoice #{$invoice->invoice_number} is already posted.");
        }
        if ($invoice->status !== InvoiceStatus::Draft) {
            throw new InvalidOperationException("Only Draft invoices can be posted.");
        }
    }

    /**
     * Assert a bill is in a state that can be posted.
     */
    public function assertBillPostable(Bill $bill): void
    {
        if ($bill->transaction_id !== null) {
            throw new AlreadyPostedException("Bill #{$bill->bill_number} is already posted.");
        }
        if ($bill->status !== BillStatus::Draft) {
            throw new InvalidOperationException("Only Draft bills can be posted.");
        }
    }

    /**
     * Assert a payment can be recorded against an invoice.
     */
    public function assertPaymentAllowed(Invoice|Bill $document): void
    {
        if ($document instanceof Invoice) {
            if (!in_array($document->status, [InvoiceStatus::Sent, InvoiceStatus::PartiallyPaid])) {
                throw new InvalidOperationException("Payment can only be recorded on Sent or Partially Paid invoices.");
            }
        } elseif ($document instanceof Bill) {
            if (!in_array($document->status, [BillStatus::Received, BillStatus::PartiallyPaid])) {
                throw new InvalidOperationException("Payment can only be recorded on Received or Partially Paid bills.");
            }
        }
    }

    /**
     * Assert a cancellation is legal.
     */
    public function assertCancellable(Invoice|Bill $document): void
    {
        if ($document instanceof Invoice) {
            if ($document->status === InvoiceStatus::Cancelled) {
                throw new InvalidOperationException("Document is already cancelled.");
            }
            if ($document->status === InvoiceStatus::Paid) {
                throw new InvalidOperationException("A fully paid document cannot be cancelled. Issue a Credit Note instead.");
            }
        } elseif ($document instanceof Bill) {
            if ($document->status === BillStatus::Cancelled) {
                throw new InvalidOperationException("Document is already cancelled.");
            }
            if ($document->status === BillStatus::Paid) {
                throw new InvalidOperationException("A fully paid document cannot be cancelled. Issue a Debit Note instead.");
            }
        }
    }
}
