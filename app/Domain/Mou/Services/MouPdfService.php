<?php

namespace App\Domain\Mou\Services;

use App\Domain\Mou\Models\Mou;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class MouPdfService
{
    /**
     * Generate the MOU PDF without attachments.
     *
     * @param Mou $mou
     * @return string The binary content of the PDF.
     */
    public function generateMouPdf(Mou $mou)
    {
        $mou->load(['property', 'party', 'signatoryParty']);

        // Generate PDF using dompdf without attachments
        $pdf = Pdf::loadView('pdf.mou', [
            'mou' => $mou,
            'property' => $mou->property,
            'party' => $mou->party,
            'signatoryParty' => $mou->signatoryParty,
        ]);

        return $pdf->output();
    }

    /**
     * Generate the KYC Documents PDF including attachments.
     *
     * @param Mou $mou
     * @return string|null The binary content of the KYC PDF or null if no attachments.
     */
    public function generateKycPdf(Mou $mou)
    {
        $mou->load(['media', 'party']);

        $attachments = [];

        $processMedia = function($collectionName, $ownerType, $ownerName) use ($mou, &$attachments) {
            $mediaItems = $mou->getMedia($collectionName);
            foreach ($mediaItems as $media) {
                $path = $media->getPath();
                if (!file_exists($path)) continue;
                
                $mimeType = $media->mime_type;
                
                if (str_starts_with($mimeType, 'image/')) {
                    $type = pathinfo($path, PATHINFO_EXTENSION);
                    $data = file_get_contents($path);

                    // Image compression
                    $img = null;
                    if (in_array(strtolower($type), ['jpeg', 'jpg'])) {
                        $img = @imagecreatefromjpeg($path);
                    } elseif (strtolower($type) === 'png') {
                        $img = @imagecreatefrompng($path);
                    }

                    if ($img !== false && $img !== null) {
                        $width = imagesx($img);
                        $height = imagesy($img);
                        $maxWidth = 800;
                        $maxHeight = 1000;

                        if ($width > $maxWidth || $height > $maxHeight) {
                            $ratio = min($maxWidth / $width, $maxHeight / $height);
                            $newWidth = $width * $ratio;
                            $newHeight = $height * $ratio;

                            $newImg = imagecreatetruecolor($newWidth, $newHeight);
                            if (strtolower($type) === 'png') {
                                imagealphablending($newImg, false);
                                imagesavealpha($newImg, true);
                            }
                            imagecopyresampled($newImg, $img, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

                            ob_start();
                            if (strtolower($type) === 'png') {
                                imagepng($newImg, null, 6);
                            } else {
                                imagejpeg($newImg, null, 70);
                            }
                            $data = ob_get_clean();
                            imagedestroy($newImg);
                        }
                        imagedestroy($img);
                    }

                    $base64 = 'data:image/' . $type . ';base64,' . base64_encode($data);
                    
                    $attachments[] = [
                        'name' => $media->file_name,
                        'data' => $base64,
                        'ownerType' => $ownerType,
                        'ownerName' => $ownerName,
                    ];
                } elseif ($mimeType === 'application/pdf') {
                    // Convert PDF to images using Ghostscript
                    $tempDir = sys_get_temp_dir() . '/' . uniqid('pdf_pages_');
                    mkdir($tempDir);
                    
                    $cmd = "gs -q -dNOPAUSE -dBATCH -sDEVICE=jpeg -r150 -dJPEGQ=85 -sOutputFile=" . escapeshellarg($tempDir . "/page_%03d.jpg") . " " . escapeshellarg($path);
                    exec($cmd, $output, $returnVar);
                    
                    if ($returnVar === 0) {
                        $files = glob($tempDir . "/page_*.jpg");
                        sort($files);
                        
                        $pageIndex = 1;
                        foreach ($files as $file) {
                            $data = file_get_contents($file);
                            // We use -r150 which makes A4 around 1240x1754, so we resize it here just like regular images
                            $img = @imagecreatefromjpeg($file);
                            if ($img !== false && $img !== null) {
                                $width = imagesx($img);
                                $height = imagesy($img);
                                $maxWidth = 800;
                                $maxHeight = 1000;
        
                                if ($width > $maxWidth || $height > $maxHeight) {
                                    $ratio = min($maxWidth / $width, $maxHeight / $height);
                                    $newWidth = $width * $ratio;
                                    $newHeight = $height * $ratio;
        
                                    $newImg = imagecreatetruecolor($newWidth, $newHeight);
                                    imagecopyresampled($newImg, $img, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
                                    
                                    ob_start();
                                    imagejpeg($newImg, null, 70);
                                    $data = ob_get_clean();
                                    imagedestroy($newImg);
                                }
                                imagedestroy($img);
                            }
                            
                            $base64 = 'data:image/jpeg;base64,' . base64_encode($data);
                            $attachments[] = [
                                'name' => $media->file_name . ' (Page ' . $pageIndex . ')',
                                'data' => $base64,
                                'ownerType' => $ownerType,
                                'ownerName' => $ownerName,
                            ];
                            
                            $pageIndex++;
                            unlink($file); // clean up
                        }
                    }
                    rmdir($tempDir); // clean up
                }
            }
        };

        // Process Owner documents
        $ownerName = $mou->party->display_name ?? 'Property Owner';
        $processMedia('mou_attachments', 'Property Owner', $ownerName);
        
        // Process Signatory documents
        if ($mou->is_signatory_different) {
            $signatoryName = $mou->signatory_details['name'] ?? 'Signatory Authority';
            $processMedia('signatory_documents', 'Signatory Authority', $signatoryName);
        }

        if (empty($attachments)) {
            return null;
        }

        // Generate KYC PDF using dompdf
        $pdf = Pdf::loadView('pdf.kyc', [
            'mou' => $mou,
            'attachments' => $attachments,
        ]);

        return $pdf->output();
    }

    /**
     * Save the generated PDFs.
     */
    public function saveMouPdf(Mou $mou)
    {
        $pdfContent = $this->generateMouPdf($mou);
        $filename = 'MOU_' . ($mou->number ?? $mou->id) . '.pdf';
        
        $tempPath = sys_get_temp_dir() . '/' . $filename;
        file_put_contents($tempPath, $pdfContent);

        // Also generate KYC documents and append to MOU
        $kycContent = $this->generateKycPdf($mou);
        if ($kycContent) {
            $kycTempPath = sys_get_temp_dir() . '/kyc_' . uniqid() . '.pdf';
            file_put_contents($kycTempPath, $kycContent);
            
            $mergedTempPath = sys_get_temp_dir() . '/merged_' . uniqid() . '.pdf';
            $cmd = "gs -q -dNOPAUSE -dBATCH -sDEVICE=pdfwrite -sOutputFile=" . escapeshellarg($mergedTempPath) . " " . escapeshellarg($tempPath) . " " . escapeshellarg($kycTempPath);
            exec($cmd, $output, $returnVar);
            
            if ($returnVar === 0 && file_exists($mergedTempPath)) {
                copy($mergedTempPath, $tempPath);
                unlink($mergedTempPath);
            } else {
                \Illuminate\Support\Facades\Log::error('Ghostscript failed to merge KYC into MOU. Return var: ' . $returnVar);
            }
            
            unlink($kycTempPath);
        }

        $mou->addMedia($tempPath)
            ->toMediaCollection('signed_pdf'); // or draft_pdf
            
        // Clean up any stray KYC collections
        $mou->clearMediaCollection('kyc_documents');
    }
}
