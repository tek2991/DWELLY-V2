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
    
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (!empty($data['legal_terms']['financial_model_id'])) {
            $model = \App\Domain\Opportunity\Models\FinancialModel::find($data['legal_terms']['financial_model_id']);
            if ($model) {
                $data['legal_terms']['financial_model_name'] = $model->name;
                $data['legal_terms']['financial_model_description'] = $model->description;
                $data['legal_terms']['financial_model_fee_collection'] = $model->fee_collection;
            }
        }
        
        if (!empty($data['legal_terms']['electricity_provider_id'])) {
            $provider = \App\Domain\Property\Models\UtilityProvider::find($data['legal_terms']['electricity_provider_id']);
            if ($provider) {
                $data['legal_terms']['electricity_provider_name'] = $provider->name;
            }
        }

        unset($data['legal_terms']['pricing_model'], $data['legal_terms']['fee_percentage']);
        
        if (empty($data['is_signatory_different'])) {
            $mou = $this->getRecord();
            $party = $mou->party ?? (!empty($data['party_id']) ? \App\Domain\Party\Models\Party::find($data['party_id']) : null);
            $opportunity = $mou->opportunity ?? (!empty($data['opportunity_id']) ? \App\Domain\Opportunity\Models\Opportunity::find($data['opportunity_id']) : null);
            $data['signatory_details'] = app(\App\Domain\Mou\Services\MouService::class)->getSignatoryDetailsForOwner($party, $opportunity);
        }

        return $data;
    }
}
