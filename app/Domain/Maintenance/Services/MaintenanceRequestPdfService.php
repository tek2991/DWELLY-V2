<?php

namespace App\Domain\Maintenance\Services;

use App\Domain\Maintenance\Models\MaintenanceRequest;
use App\Domain\Property\Models\PropertyInventory;
use App\Domain\Property\Models\PropertyRoom;
use App\Domain\Property\Models\PropertyUtility;
use Barryvdh\DomPDF\Facade\Pdf;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class MaintenanceRequestPdfService
{
    public function generatePdf(MaintenanceRequest $ticket): Media
    {
        $ticket->refresh();
        $ticket->loadMissing([
            'property',
            'owner',
            'tenant',
            'vendorParty',
            'assignedInspector',
            'createdBy',
            'currentClientQuote.items',
            'vendorQuotes',
            'items.itemable',
            'triggeredAudit.inspector',
        ]);

        $pdf = $this->buildPdfInstance($ticket);

        $tempFileName = $ticket->ticket_number . '-report.pdf';
        $tempPath = sys_get_temp_dir() . '/' . $tempFileName;
        $pdf->save($tempPath);

        $media = $ticket->addMedia($tempPath)
            ->withCustomProperties([
                'generated_at' => now()->toDateTimeString(),
                'generated_by' => auth()->id(),
                'generated_by_name' => auth()->user()?->name ?? 'System',
            ])
            ->toMediaCollection('generated_maintenance_pdf');

        return $media;
    }

    public function downloadPdfResponse(MaintenanceRequest $ticket)
    {
        $ticket->refresh();
        $ticket->loadMissing([
            'property',
            'owner',
            'tenant',
            'vendorParty',
            'assignedInspector',
            'createdBy',
            'currentClientQuote.items',
            'vendorQuotes',
            'items.itemable',
            'triggeredAudit.inspector',
        ]);

        $pdf = $this->buildPdfInstance($ticket);

        return response()->streamDownload(
            fn () => print($pdf->output()),
            $ticket->ticket_number . '-dossier.pdf',
            ['Content-Type' => 'application/pdf']
        );
    }

    public function buildPdfInstance(MaintenanceRequest $ticket): \Barryvdh\DomPDF\PDF
    {
        $itemsData = [];
        foreach ($ticket->items as $index => $item) {
            $targetName = 'General Property Area';
            if ($item->itemable instanceof PropertyRoom) {
                $targetName = 'Room: ' . ($item->itemable->custom_name ?: ($item->itemable->roomDefinition?->name ?? "Room #{$item->itemable->id}"));
            } elseif ($item->itemable instanceof PropertyInventory) {
                $targetName = 'Inventory: ' . ($item->itemable->inventoryType?->name ?? "Item #{$item->itemable->id}");
            } elseif ($item->itemable instanceof PropertyUtility) {
                $targetName = 'Utility: ' . ($item->itemable->utilityType?->name ?? "Utility #{$item->itemable->id}");
            }

            $beforePhotos = $this->extractMediaBase64($item->getMedia('issue_photos'));
            $afterPhotos = $this->extractMediaBase64($item->getMedia('repaired_photos'));

            $itemsData[] = [
                'index' => $index + 1,
                'target_name' => $targetName,
                'issue_description' => $item->issue_description,
                'repair_action' => $item->repair_action,
                'status' => $item->status ?? 'pending',
                'before_photos' => $beforePhotos,
                'after_photos' => $afterPhotos,
            ];
        }

        $acceptanceProofs = $this->extractMediaBase64($ticket->getMedia('client_acceptance_proofs'));

        $pdf = Pdf::loadView('pdf.maintenance_request_report', [
            'ticket' => $ticket,
            'property' => $ticket->property,
            'owner' => $ticket->owner,
            'tenant' => $ticket->tenant,
            'vendor' => $ticket->vendorParty,
            'inspector' => $ticket->assignedInspector,
            'quote' => $ticket->currentClientQuote,
            'itemsData' => $itemsData,
            'acceptanceProofs' => $acceptanceProofs,
            'generatedAt' => now()->timezone(config('app.timezone', 'Asia/Kolkata'))->format('d M Y, h:i A'),
        ]);

        $pdf->setPaper('a4', 'portrait');
        $pdf->setOption('isRemoteEnabled', true);
        $pdf->setOption('isHtml5ParserEnabled', true);
        $pdf->setOption('isPhpEnabled', true);

        return $pdf;
    }

    protected function extractMediaBase64($mediaCollection): array
    {
        $photos = [];
        foreach ($mediaCollection as $media) {
            $path = $media->getPath();
            if (file_exists($path)) {
                $mime = $media->mime_type ?: 'image/jpeg';
                $base64 = base64_encode(file_get_contents($path));
                $photos[] = [
                    'src' => "data:{$mime};base64,{$base64}",
                    'name' => $media->file_name,
                ];
            }
        }
        return $photos;
    }
}
