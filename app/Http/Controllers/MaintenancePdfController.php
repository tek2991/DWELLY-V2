<?php

namespace App\Http\Controllers;

use App\Domain\Maintenance\Models\MaintenanceRequest;
use App\Domain\Maintenance\Services\MaintenanceRequestPdfService;
use Illuminate\Http\Request;

class MaintenancePdfController extends Controller
{
    public function __construct(
        protected MaintenanceRequestPdfService $pdfService
    ) {}

    public function stream(MaintenanceRequest $record)
    {
        $pdf = $this->pdfService->buildPdfInstance($record);

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $record->ticket_number . '-dossier.pdf"',
        ]);
    }

    public function download(MaintenanceRequest $record)
    {
        return $this->pdfService->downloadPdfResponse($record);
    }
}
