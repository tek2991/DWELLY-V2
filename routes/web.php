<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BillingDocumentController;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware(['auth'])->group(function () {
    Route::get('/billing/invoices/{invoice}/pdf', [BillingDocumentController::class, 'downloadInvoice'])
        ->name('billing.invoice.pdf');
    Route::get('/billing/invoices/{invoice}/payments/{payment}/receipt', [BillingDocumentController::class, 'downloadReceipt'])
        ->name('billing.receipt.pdf');
    Route::get('/billing/bills/{bill}/pdf', [BillingDocumentController::class, 'downloadBill'])
        ->name('billing.bill.pdf');
});
