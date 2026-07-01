<?php

namespace Tek2991\Accounting\Filament\Pages\Reports\Concerns;

use Filament\Actions\Action;
use Illuminate\Support\Facades\Storage;
use Tek2991\Accounting\Services\PdfService;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

trait HasReportExports
{
    protected function getHeaderActions(): array
    {
        return [
            Action::make('print')
                ->label('Print')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->url(fn () => route('accounting.reports.print', [
                    'report' => class_basename(static::class),
                    'state' => base64_encode(json_encode($this->form->getState()))
                ]))
                ->openUrlInNewTab()
                ->visible(fn () => ($this->reportData['showReport'] ?? true)),

            Action::make('exportCsv')
                ->label('Export CSV')
                ->icon('heroicon-o-document-text')
                ->color('gray')
                ->action(fn () => $this->downloadCsv())
                ->visible(fn () => ($this->reportData['showReport'] ?? true)),

            Action::make('exportPdf')
                ->label('Export PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->color('primary')
                ->action(fn () => $this->downloadPdf())
                ->visible(fn () => ($this->reportData['showReport'] ?? true)),
        ];
    }

    public function downloadPdf()
    {
        $data = $this->reportData;
        $reportType = class_basename(static::class);
        $viewName = 'accounting::pdf.reports.' . Str::kebab($reportType);
        
        // Fallback to generic ledger view for the 3 ledger types
        if (in_array($reportType, ['AccountLedger', 'VendorLedger', 'CustomerLedger'])) {
            $viewName = 'accounting::pdf.reports.ledger';
        }

        $pdfService = app(PdfService::class);
        $filename = Str::slug($data['title']) . '_' . now()->format('Ymd_His') . '.pdf';

        $path = $pdfService->generateAndSave(
            $viewName,
            ['data' => $data],
            $filename
        );

        $disk = config('accounting.pdf.disk', 'public');
        
        return response()->download(Storage::disk($disk)->path($path));
    }

    public function downloadCsv()
    {
        $data = $this->reportData;
        $reportType = class_basename(static::class);
        $filename = Str::slug($data['title']) . '_' . now()->format('Ymd_His') . '.csv';

        return new StreamedResponse(function () use ($data, $reportType) {
            $handle = fopen('php://output', 'w');
            
            // Write Header info
            fputcsv($handle, [$data['title']]);
            if (isset($data['subtitle'])) {
                fputcsv($handle, [$data['subtitle']]);
            }
            fputcsv($handle, ["Period: {$data['startDate']} to {$data['endDate']}"]);
            fputcsv($handle, []); // blank line

            // Delegate to the specific report for rows formatting
            if (method_exists($this, 'generateCsvRows')) {
                $this->generateCsvRows($handle, $data);
            }

            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}
