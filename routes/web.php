<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BillingDocumentController;
use App\Http\Controllers\AuditReportController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/login', function () {
    return redirect('/admin/login');
})->name('login');

Route::middleware(['auth'])->group(function () {
    Route::get('/billing/invoices/{invoice}/pdf', [BillingDocumentController::class, 'downloadInvoice'])
        ->name('billing.invoice.pdf');
    Route::get('/billing/invoices/{invoice}/payments/{payment}/receipt', [BillingDocumentController::class, 'downloadReceipt'])
        ->name('billing.receipt.pdf');
    Route::get('/billing/bills/{bill}/pdf', [BillingDocumentController::class, 'downloadBill'])
        ->name('billing.bill.pdf');

    Route::get('/operations/audits/{audit}/pdf', [AuditReportController::class, 'stream'])
        ->name('operations.audits.pdf');
    Route::get('/operations/audits/{audit}/pdf/download', [AuditReportController::class, 'download'])
        ->name('operations.audits.pdf.download');
});
