<?php

namespace Tek2991\Accounting\Services;

use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\LaravelPdf\Facades\Pdf;

class PdfService
{
    /**
     * Generate a PDF from HTML or a view and save it to the configured disk.
     *
     * @param string $view The view name
     * @param array $data Data for the view
     * @param string|null $filename Optional custom filename
     * @return string The storage path of the generated PDF
     */
    public function generateAndSave(string $view, array $data = [], ?string $filename = null): string
    {
        $filename = $filename ?? 'document_' . Str::random(10) . '.pdf';
        
        $primaryDriver = config('accounting.pdf.driver', 'browsershot');
        $fallbackDriver = config('accounting.pdf.fallback_driver', 'dompdf');
        $disk = config('accounting.pdf.disk', 'public');
        
        $paperSize = config('accounting.pdf.paper_size', 'A4');
        $orientation = config('accounting.pdf.orientation', 'portrait');

        $showWatermark = config('accounting.pdf.show_watermark', false) && app()->environment(['local', 'development', 'testing']);

        $pdfContent = null;

        try {
            // Attempt with primary driver
            config(['laravel-pdf.driver' => $primaryDriver]);
            
            if ($showWatermark) {
                $data['__pdf_driver'] = $primaryDriver;
            }
            
            $builder = Pdf::view($view, $data)
                ->format($paperSize)
                ->{$orientation}();

            if ($primaryDriver === 'browsershot') {
                $builder->withBrowsershot(function (\Spatie\Browsershot\Browsershot $browsershot) {
                    $browsershot->noSandbox();
                    $nodeBinary = config('accounting.pdf.node_binary') ?: env('BROWSERSHOT_NODE_BINARY');
                    $npmBinary = config('accounting.pdf.npm_binary') ?: env('BROWSERSHOT_NPM_BINARY');
                    $chromeBinary = config('accounting.pdf.chrome_binary') ?: env('BROWSERSHOT_CHROME_BINARY');
                    
                    if ($nodeBinary) {
                        $browsershot->setNodeBinary($nodeBinary);
                    }
                    if ($npmBinary) {
                        $browsershot->setNpmBinary($npmBinary);
                    }
                    if ($chromeBinary) {
                        $browsershot->setChromePath($chromeBinary);
                    }
                });
                $builder->footerView('accounting::pdf.footer', $data)
                        ->margins(5, 8, 15, 8);
            }
            
            $pdfContent = $builder->base64();
                
        } catch (Exception $e) {
            Log::warning("Primary PDF driver ({$primaryDriver}) failed. Falling back to {$fallbackDriver}. Error: " . $e->getMessage());
            
            // Fallback to secondary driver
            config(['laravel-pdf.driver' => $fallbackDriver]);
            
            if ($showWatermark) {
                $data['__pdf_driver'] = $fallbackDriver;
            }
            
            $builder = Pdf::view($view, $data)
                ->format($paperSize)
                ->{$orientation}();

            if ($fallbackDriver === 'browsershot') {
                $builder->withBrowsershot(function (\Spatie\Browsershot\Browsershot $browsershot) {
                    $browsershot->noSandbox();
                    $nodeBinary = config('accounting.pdf.node_binary') ?: env('BROWSERSHOT_NODE_BINARY');
                    $npmBinary = config('accounting.pdf.npm_binary') ?: env('BROWSERSHOT_NPM_BINARY');
                    $chromeBinary = config('accounting.pdf.chrome_binary') ?: env('BROWSERSHOT_CHROME_BINARY');
                    
                    if ($nodeBinary) {
                        $browsershot->setNodeBinary($nodeBinary);
                    }
                    if ($npmBinary) {
                        $browsershot->setNpmBinary($npmBinary);
                    }
                    if ($chromeBinary) {
                        $browsershot->setChromePath($chromeBinary);
                    }
                });
                $builder->footerView('accounting::pdf.footer', $data)
                        ->margins(5, 8, 15, 8);
            }
            
            $pdfContent = $builder->base64();
        }
        
        $path = 'accounting/pdfs/' . $filename;
        Storage::disk($disk)->put($path, base64_decode($pdfContent));
        
        return $path;
    }
}
