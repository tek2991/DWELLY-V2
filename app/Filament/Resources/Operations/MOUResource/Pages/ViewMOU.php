<?php

namespace App\Filament\Resources\Operations\MOUResource\Pages;

use App\Domain\Mou\Enums\MouType;
use App\Domain\Mou\Models\Mou;
use App\Domain\Mou\Services\MouWorkflowService;
use App\Domain\Opportunity\Enums\MouStatus;
use App\Domain\Property\Services\PropertyOnboardingService;
use App\Filament\Resources\Operations\MOUResource;
use App\Filament\Resources\Properties\PropertyResource;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class ViewMOU extends ViewRecord
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
                ->modalHeading(fn (?array $arguments = null) => $arguments['title'] ?? 'View Document')
                ->modalWidth('7xl')
                ->modalSubmitActionLabel('Download Document')
                ->modalCancelActionLabel('Close')
                ->modalContent(function (?array $arguments = null) {
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
                ->action(function (?array $arguments = null, ?Mou $record = null) {
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

            MOUResource::getVerifyAction(),
            MOUResource::getConvertToPropertyAction(),

            EditAction::make()
                ->visible(fn ($record) => MOUResource::canEdit($record)),

            ActionGroup::make([
                MOUResource::getResolvePartyAction(),
                MOUResource::getUpdatePartyAction(),
                MOUResource::getProvisionAccountingAction(),
                MOUResource::getGeneratePdfAction(),
                MOUResource::getUploadSignedCopyAction(),
                MOUResource::getArchiveAction(),
            ])
                ->label('Actions')
                ->icon('heroicon-m-ellipsis-vertical'),
        ];
    }
}
