<?php

namespace App\Filament\Resources\Operations\MOUResource\Pages;

use App\Filament\Resources\Operations\MOUResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Forms;
use App\Domain\Mou\Models\Mou;
use App\Domain\Opportunity\Enums\MouStatus;
use App\Domain\Mou\Services\MouWorkflowService;

class ViewMOU extends ViewRecord
{
    protected static string $resource = MOUResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('viewHistoryPdf')
                ->extraAttributes(['style' => 'display: none !important;']) // Hide from header visually, but keep mountable
                ->modalHeading(fn (?array $arguments = null) => $arguments['title'] ?? 'View Document')
                ->modalWidth('7xl')
                ->modalSubmitActionLabel('Download Document')
                ->modalCancelActionLabel('Close')
                ->modalContent(function (?array $arguments = null) {
                    $mediaId = $arguments['mediaId'] ?? null;
                    if (!$mediaId) return null;
                    
                    $media = \Spatie\MediaLibrary\MediaCollections\Models\Media::find($mediaId);
                    if (!$media) return null;
                    
                    return view('components.pdf-viewer-raw', [
                        'path' => $media->getPath()
                    ]);
                })
                ->action(function (?array $arguments = null, ?Mou $record = null) {
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
                
            Actions\ActionGroup::make([
                Actions\EditAction::make()
                    ->visible(fn ($record) => MOUResource::canEdit($record)),
                
                Actions\Action::make('resolveParty')
                    ->label('Resolve Party')
                    ->icon('heroicon-o-users')
                    ->color('primary')
                    ->visible(fn (?Mou $record) => $record && !$record->party_id && MOUResource::canEdit($record))
                    ->form(MOUResource::getResolvePartyFormSchema())
                    ->action(function (Mou $record, array $data) {
                        app(\App\Domain\Mou\Services\MouService::class)->resolveParty($record, $data);
                        $record->refresh();
                        \Filament\Notifications\Notification::make()->title('Party Resolved')->success()->send();
                    }),

                MOUResource::getUpdatePartyAction(),

                Actions\Action::make('provisionAccounting')
                    ->label('Provision Accounting')
                    ->icon('heroicon-o-banknotes')
                    ->color('primary')
                    ->visible(fn (?Mou $record) => $record && $record->party_id && empty($record->bank_details) && $record->status === MouStatus::DRAFT)
                    ->form([
                        Forms\Components\TextInput::make('bank_name')->required(),
                        Forms\Components\TextInput::make('account_holder_name')->required(),
                        Forms\Components\TextInput::make('account_number')->required(),
                        Forms\Components\TextInput::make('ifsc_code')->required(),
                        Forms\Components\Textarea::make('branch_address')->label('Address of the Bank')->required()->columnSpanFull(),
                    ])
                    ->action(function (Mou $record, array $data) {
                        app(\App\Domain\Mou\Services\MouService::class)->provisionAccounting($record, $data);
                        $record->refresh();
                        \Filament\Notifications\Notification::make()->title('Accounting Provisioned')->success()->send();
                    }),

                Actions\Action::make('generatePdf')
                    ->label(fn (?Mou $record) => $record?->hasMedia('draft_pdf') ? 'Regenerate PDF' : 'Generate PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('warning')
                    ->visible(fn (?Mou $record) => $record && in_array($record->status, [
                        MouStatus::DRAFT, 
                        MouStatus::PARTY_PENDING, 
                        MouStatus::READY_TO_GENERATE, 
                        MouStatus::PDF_GENERATED, 
                        MouStatus::DOWNLOADED,
                        MouStatus::SIGNED_COPY_UPLOADED
                    ]))
                    ->requiresConfirmation(fn (?Mou $record) => (bool) $record?->hasMedia('draft_pdf'))
                    ->modalHeading(fn (?Mou $record) => $record?->hasMedia('draft_pdf') ? 'Regenerate Draft PDF' : 'Generate Draft PDF')
                    ->modalDescription(fn (?Mou $record) => $record?->hasMedia('signed_pdf') 
                        ? 'Are you sure you want to regenerate the draft PDF? The currently uploaded signed PDF will be archived, and the MOU status will revert to "PDF Generated".' 
                        : 'Are you sure you want to generate a new draft PDF? This will increment the document version.')
                    ->action(function (Mou $record) {
                        try {
                            app(MouWorkflowService::class)->generatePdf($record);
                            $record->refresh();
                            \Filament\Notifications\Notification::make()->title('PDF Generated')->success()->send();
                        } catch (\Exception $e) {
                            \Filament\Notifications\Notification::make()->title('Cannot Generate PDF')->body($e->getMessage())->danger()->send();
                        }
                    }),
                    
                Actions\Action::make('uploadSignedCopy')
                    ->label('Upload Signed PDF')
                    ->icon('heroicon-o-document-arrow-up')
                    ->color('info')
                    ->visible(fn (?Mou $record) => $record && in_array($record->status, [MouStatus::PDF_GENERATED, MouStatus::DOWNLOADED, MouStatus::SIGNED_COPY_UPLOADED]))
                    ->form([
                        Forms\Components\FileUpload::make('signed_pdf')
                            ->label('Signed PDF File')
                            ->directory('temp-signed-pdfs')
                            ->acceptedFileTypes(['application/pdf'])
                            ->required(),
                    ])
                    ->action(function (Mou $record, array $data) {
                        app(MouWorkflowService::class)->uploadSignedCopy($record, $data['signed_pdf']);
                        $record->refresh();
                        \Filament\Notifications\Notification::make()->title('Signed Copy Uploaded')->success()->send();
                    }),
                    
                Actions\Action::make('verify')
                    ->label('Verify Agreement')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn (?Mou $record) => $record?->status === MouStatus::SIGNED_COPY_UPLOADED)
                    ->requiresConfirmation()
                    ->action(function (Mou $record) {
                        app(MouWorkflowService::class)->verify($record);
                        $record->refresh();
                        \Filament\Notifications\Notification::make()->title('Agreement Verified')->success()->send();
                    }),
                    
                Actions\Action::make('convertToProperty')
                    ->label('Convert to Property')
                    ->icon('heroicon-o-building-office')
                    ->color('success')
                    ->visible(fn (?Mou $record) => $record?->status === MouStatus::VERIFIED && ($record?->type === \App\Domain\Mou\Enums\MouType::ONBOARDING || $record?->type === null))
                    ->requiresConfirmation()
                    ->action(function (?Mou $record = null) {
                        $property = app(\App\Domain\Property\Services\PropertyOnboardingService::class)->createPropertyFromMou($record);
                        app(MouWorkflowService::class)->convert($record);
                        
                        \Filament\Notifications\Notification::make()->title('Property Created')->success()->send();
                        
                        return redirect(\App\Filament\Resources\Properties\PropertyResource::getUrl('edit', ['record' => $property]));
                    }),

                Actions\Action::make('archive')
                    ->label('Archive')
                    ->icon('heroicon-o-archive-box')
                    ->color('danger')
                    ->visible(fn (?Mou $record) => $record && $record->verified_at === null && !in_array($record->status, [
                        MouStatus::VERIFIED,
                        MouStatus::CONVERTED,
                        MouStatus::COMPLETED,
                        MouStatus::CANCELLED
                    ]))
                    ->requiresConfirmation()
                    ->modalHeading('Archive MOU')
                    ->modalDescription('Are you sure you want to archive this MOU? The corresponding opportunity will also be marked as Closed Lost.')
                    ->modalSubmitActionLabel('Archive')
                    ->action(function (Mou $record) {
                        try {
                            app(MouWorkflowService::class)->archive($record);
                            \Filament\Notifications\Notification::make()
                                ->title('MOU Archived')
                                ->body('The MOU has been archived and the opportunity marked as Closed Lost.')
                                ->success()
                                ->send();
                            $this->redirect(MOUResource::getUrl('index'));
                        } catch (\Exception $e) {
                            \Filament\Notifications\Notification::make()
                                ->title('Cannot Archive MOU')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->label('Actions')
            ->icon('heroicon-m-ellipsis-vertical'),
        ];
    }
}
