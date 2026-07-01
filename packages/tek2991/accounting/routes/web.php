<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/accounting/reports/print/{report}', function (Request $request, $report) {
        $state = json_decode(base64_decode($request->get('state')), true);
        
        // We need to resolve the page class to re-compute the report data
        $pageClass = 'Tek2991\\Accounting\\Filament\\Pages\\Reports\\' . $report;
        if (!class_exists($pageClass)) {
            abort(404);
        }

        $page = app($pageClass);
        // We simulate the form state so getReportDataProperty works
        $page->data = $state;
        $data = $page->getReportDataProperty();

        $viewName = 'accounting::pdf.reports.' . Str::kebab($report);
        if (in_array($report, ['AccountLedger', 'VendorLedger', 'CustomerLedger'])) {
            $viewName = 'accounting::pdf.reports.ledger';
        }

        return view($viewName, [
            'data' => $data,
            '__pdf_driver' => 'print', // So we don't show the dompdf footer
        ])->render() . '<script>window.onload = function() { window.print(); }</script>';

    })->name('accounting.reports.print');
});
