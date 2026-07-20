<?php

namespace App\Domain\Mou\Services;

use App\Domain\Mou\Models\Mou;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class MouPdfService
{
    /**
     * Generate the MOU PDF including terms and attachments.
     *
     * @param Mou $mou
     * @return string The file path or binary content of the PDF.
     */
    public function generateMouPdf(Mou $mou)
    {
        $mou->load(['property', 'party', 'signatoryParty', 'media']);

        // Collect attachments to embed (converting to base64 for dompdf compatibility)
        $attachments = [];
        $mediaItems = $mou->getMedia('mou_attachments');
        
        foreach ($mediaItems as $media) {
            // We only embed images directly in dompdf.
            // If it's a PDF, we might need a different approach (FPDI), but for now we focus on images.
            if (str_starts_with($media->mime_type, 'image/')) {
                $path = $media->getPath();
                if (file_exists($path)) {
                    $type = pathinfo($path, PATHINFO_EXTENSION);
                    $data = file_get_contents($path);
                    $base64 = 'data:image/' . $type . ';base64,' . base64_encode($data);
                    
                    $attachments[] = [
                        'name' => $media->file_name,
                        'data' => $base64,
                    ];
                }
            }
        }

        // Generate PDF using dompdf
        $pdf = Pdf::loadView('pdf.mou', [
            'mou' => $mou,
            'property' => $mou->property,
            'party' => $mou->party,
            'signatoryParty' => $mou->signatoryParty,
            'attachments' => $attachments,
        ]);

        // Return the generated PDF content
        return $pdf->output();
    }

    /**
     * Save the generated PDF to the 'generated_pdf' media collection.
     */
    public function saveMouPdf(Mou $mou)
    {
        $pdfContent = $this->generateMouPdf($mou);
        $filename = 'MOU_' . ($mou->number ?? $mou->id) . '.pdf';
        
        $tempPath = sys_get_temp_dir() . '/' . $filename;
        file_put_contents($tempPath, $pdfContent);

        $mou->addMedia($tempPath)
            ->toMediaCollection('signed_pdf'); // or draft_pdf
    }
}
