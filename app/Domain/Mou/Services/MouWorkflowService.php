<?php

namespace App\Domain\Mou\Services;

use App\Domain\Mou\Models\Mou;
use App\Domain\Opportunity\Enums\MouStatus;
use Barryvdh\DomPDF\Facade\Pdf;

class MouWorkflowService
{
    public function __construct(
        protected MouReadinessService $readinessService
    ) {}

    public function generatePdf(Mou $mou): void
    {
        $readiness = $this->readinessService->canGeneratePdf($mou);
        if (!$readiness['is_ready']) {
            throw new \Exception("Cannot generate PDF: " . implode(' ', $readiness['errors']));
        }

        // Render the real PDF using DomPDF
        $pdf = Pdf::loadView('pdf.mou', ['mou' => $mou]);
        
        $tempPath = sys_get_temp_dir() . '/' . $mou->number . '-draft.pdf';
        $pdf->save($tempPath);

        $mou->addMedia($tempPath)->toMediaCollection('draft_pdf');

        $mou->update([
            'status' => MouStatus::PDF_GENERATED,
            'generated_by' => auth()->id(),
        ]);
    }

    public function markAsDownloaded(Mou $mou): void
    {
        if ($mou->status === MouStatus::PDF_GENERATED) {
            $mou->update(['status' => MouStatus::DOWNLOADED]);
        }
    }

    public function uploadSignedCopy(Mou $mou, string $filePath): void
    {
        $fullPath = \Illuminate\Support\Facades\Storage::disk(config('filament.default_filesystem_disk'))->path($filePath);
        
        if (!file_exists($fullPath)) {
            // Fallback just in case
            $fullPath = \Illuminate\Support\Facades\Storage::disk('public')->path($filePath);
        }
        
        $mou->addMedia($fullPath)->toMediaCollection('signed_pdf');
        $mou->update(['status' => MouStatus::SIGNED_COPY_UPLOADED]);
    }

    public function verify(Mou $mou): void
    {
        if ($mou->status !== MouStatus::SIGNED_COPY_UPLOADED) {
            throw new \Exception("Cannot verify. Signed copy must be uploaded first.");
        }

        $mou->update([
            'status' => MouStatus::VERIFIED,
            'verified_at' => now(),
            'verified_by' => auth()->id(),
        ]);
    }

    public function convert(Mou $mou): void
    {
        if ($mou->status !== MouStatus::VERIFIED) {
            throw new \Exception("MOU must be verified before conversion.");
        }

        $mou->update(['status' => MouStatus::CONVERTED]);
    }
}
