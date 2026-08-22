<?php

namespace App\Filament\Resources\Operations\MOUResource\Pages;

use App\Domain\Mou\Models\Mou;
use App\Domain\Mou\Services\MouWorkflowService;
use App\Domain\Opportunity\Enums\MouStatus;
use App\Filament\Resources\Operations\MOUResource;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class EditMOU extends EditRecord
{
    protected static string $resource = MOUResource::class;

    public function getSubheading(): ?Htmlable
    {
        /** @var Mou $record */
        $record = $this->getRecord();
        $status = $record->status ?? MouStatus::DRAFT;
        $statusLabel = e($status->getLabel());

        $typeBadge = $record->type
            ? '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-indigo-100 text-indigo-800 dark:bg-indigo-900/60 dark:text-indigo-200">' . e($record->type->getLabel()) . '</span>'
            : '';

        $partyBadge = $record->party
            ? '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200">👤 ' . e($record->party->display_name) . '</span>'
            : '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-800 dark:bg-amber-900/60 dark:text-amber-200">⚠️ Unresolved Party</span>';

        $rent = $record->legal_terms['rent_amount'] ?? $record->opportunity?->expected_rent;
        $rentBadge = $rent > 0
            ? '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-900/60 dark:text-emerald-200">💰 ₹' . number_format((float) $rent) . ' /mo</span>'
            : '';

        $propertyName = $record->property?->building_name ?? $record->property?->address_line_1 ?? 'Property';
        $propertyBadge = $record->property
            ? '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-teal-100 text-teal-800 dark:bg-teal-900/60 dark:text-teal-200">🏢 ' . e($propertyName) . '</span>'
            : '';

        return new HtmlString(
            '<div class="flex items-center gap-2 text-sm text-gray-500 mt-1 flex-wrap">' .
            '<span>Status: <strong class="text-gray-900 dark:text-gray-100">' . $statusLabel . '</strong></span>' .
            ($typeBadge ? '<span class="text-gray-300 dark:text-gray-700">&bull;</span>' . $typeBadge : '') .
            ($partyBadge ? '<span class="text-gray-300 dark:text-gray-700">&bull;</span>' . $partyBadge : '') .
            ($rentBadge ? '<span class="text-gray-300 dark:text-gray-700">&bull;</span>' . $rentBadge : '') .
            ($propertyBadge ? '<span class="text-gray-300 dark:text-gray-700">&bull;</span>' . $propertyBadge : '') .
            '</div>'
        );
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('viewHistoryPdf')
                ->extraAttributes(['style' => 'display: none !important;']) // Hide from header visually, but keep mountable
                ->modalHeading(fn (array $arguments) => $arguments['title'] ?? 'View Document')
                ->modalWidth('7xl')
                ->modalSubmitActionLabel('Download Document')
                ->modalCancelActionLabel('Close')
                ->modalContent(function (array $arguments) {
                    $mediaId = $arguments['mediaId'] ?? null;
                    if (! $mediaId) {
                        return null;
                    }

                    $media = Media::find($mediaId);
                    if (! $media) {
                        return null;
                    }

                    return view('components.pdf-viewer-raw', [
                        'path' => $media->getPath(),
                    ]);
                })
                ->action(function (array $arguments, Mou $record) {
                    $mediaId = $arguments['mediaId'] ?? null;
                    if (! $mediaId) {
                        return;
                    }

                    $media = Media::find($mediaId);
                    if (! $media) {
                        return;
                    }

                    if ($media->collection_name === 'draft_pdf' && $record->status === MouStatus::PDF_GENERATED) {
                        app(MouWorkflowService::class)->markAsDownloaded($record);
                        $record->refresh();
                    }

                    // Use a clean filename for the download
                    $filename = $record->number . '-' . $media->file_name;

                    return response()->download($media->getPath(), $filename);
                }),

            ActionGroup::make([
                ViewAction::make(),
                MOUResource::getResolvePartyAction('dropdownResolveParty'),
                MOUResource::getUpdatePartyAction('dropdownUpdateParty'),
                MOUResource::getProvisionAccountingAction('dropdownProvisionAccounting'),
                MOUResource::getGeneratePdfAction('dropdownGeneratePdf'),
                MOUResource::getUploadSignedCopyAction('dropdownUploadSignedCopy'),
                MOUResource::getVerifyAction('dropdownVerify'),
                MOUResource::getConvertToPropertyAction('dropdownConvertToProperty'),
                MOUResource::getArchiveAction('dropdownArchive'),
            ])
            ->label('Actions')
            ->icon('heroicon-m-ellipsis-vertical'),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
    
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (!empty($data['legal_terms']['city_id'])) {
            $city = \App\Domain\Geographic\Models\City::find($data['legal_terms']['city_id']);
            if ($city) {
                $data['legal_terms']['city_name'] = $city->name;
            }
        }

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

        unset($data['legal_terms']['pricing_model']);
        
        if (empty($data['is_signatory_different'])) {
            $mou = $this->getRecord();
            $party = $mou->party ?? (!empty($data['party_id']) ? \App\Domain\Party\Models\Party::find($data['party_id']) : null);
            $opportunity = $mou->opportunity ?? (!empty($data['opportunity_id']) ? \App\Domain\Opportunity\Models\Opportunity::find($data['opportunity_id']) : null);
            $data['signatory_details'] = app(\App\Domain\Mou\Services\MouService::class)->getSignatoryDetailsForOwner($party, $opportunity);
        }

        return $data;
    }
}
