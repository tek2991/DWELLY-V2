<?php

namespace App\Http\Controllers;

use App\Domain\Audit\Models\Audit;
use App\Domain\Audit\Services\AuditPdfService;
use Illuminate\Http\Response;

class AuditReportController extends Controller
{
    public function download(Audit $audit, AuditPdfService $pdfService)
    {
        $pdf = $pdfService->generatePdf($audit);
        $filename = 'Inspection-Report-' . ($audit->audit_number ?: $audit->id) . '.pdf';
        
        return $pdf->download($filename);
    }

    public function stream(Audit $audit, AuditPdfService $pdfService)
    {
        $pdf = $pdfService->generatePdf($audit);
        $filename = 'Inspection-Report-' . ($audit->audit_number ?: $audit->id) . '.pdf';

        return $pdf->stream($filename);
    }
}
