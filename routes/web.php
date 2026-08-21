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

    Route::get('/billing/quotations/{quote}/pdf', [BillingDocumentController::class, 'streamQuotation'])
        ->name('billing.quotation.pdf');
    Route::get('/billing/quotations/{quote}/pdf/download', [BillingDocumentController::class, 'downloadQuotation'])
        ->name('billing.quotation.pdf.download');

    Route::get('/billing/work-orders/{vendorQuote}/pdf', [BillingDocumentController::class, 'streamWorkOrder'])
        ->name('billing.work_order.pdf');
    Route::get('/billing/work-orders/{vendorQuote}/pdf/download', [BillingDocumentController::class, 'downloadWorkOrder'])
        ->name('billing.work_order.pdf.download');

    Route::get('/operations/audits/{audit}/pdf', [AuditReportController::class, 'stream'])
        ->name('operations.audits.pdf');
    Route::get('/operations/audits/{audit}/pdf/download', [AuditReportController::class, 'download'])
        ->name('operations.audits.pdf.download');

    Route::get('/operations/maintenance-requests/{record}/pdf', [\App\Http\Controllers\MaintenancePdfController::class, 'stream'])
        ->name('operations.maintenance_requests.pdf');
    Route::get('/operations/maintenance-requests/{record}/pdf/download', [\App\Http\Controllers\MaintenancePdfController::class, 'download'])
        ->name('operations.maintenance_requests.pdf.download');
});
