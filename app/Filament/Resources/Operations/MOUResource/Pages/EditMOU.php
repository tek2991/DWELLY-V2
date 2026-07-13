<?php

namespace App\Filament\Resources\Operations\MOUResource\Pages;

use App\Filament\Resources\Operations\MOUResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use App\Domain\Mou\Models\Mou;
use App\Domain\Opportunity\Enums\MouStatus;
use App\Domain\Mou\Services\MouWorkflowService;

class EditMOU extends EditRecord
{
    protected static string $resource = MOUResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('viewHistoryPdf')
                ->extraAttributes(['style' => 'display: none !important;']) // Hide from header visually, but keep mountable
                ->modalHeading(fn (array $arguments) => $arguments['title'] ?? 'View Document')
                ->modalWidth('7xl')
                ->modalSubmitActionLabel('Download Document')
                ->modalCancelActionLabel('Close')
                ->modalContent(function (array $arguments) {
                    $mediaId = $arguments['mediaId'] ?? null;
                    if (!$mediaId) return null;
                    
                    $media = \Spatie\MediaLibrary\MediaCollections\Models\Media::find($mediaId);
                    if (!$media) return null;
                    
                    return view('components.pdf-viewer-raw', [
                        'path' => $media->getPath()
                    ]);
                })
                ->action(function (array $arguments, Mou $record) {
                    $mediaId = $arguments['mediaId'] ?? null;
                    if (!$mediaId) return;
                    
                    $media = \Spatie\MediaLibrary\MediaCollections\Models\Media::find($mediaId);
                    if (!$media) return;
                    
                    if ($media->collection_name === 'draft_pdf' && $record->status === MouStatus::PDF_GENERATED) {
                        app(MouWorkflowService::class)->markAsDownloaded($record);
                        $record->refresh();
                    }
                    
                    // Use a clean filename for the download
                    $filename = $record->number . '-' . $media->file_name;
                    return response()->download($media->getPath(), $filename);
                }),
                
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
            Actions\ForceDeleteAction::make(),
            Actions\RestoreAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
