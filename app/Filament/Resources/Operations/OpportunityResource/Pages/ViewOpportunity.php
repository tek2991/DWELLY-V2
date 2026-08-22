<?php

namespace App\Filament\Resources\Operations\OpportunityResource\Pages;

use App\Domain\Mou\Models\Mou;
use App\Domain\Opportunity\Enums\OpportunityStatus;
use App\Domain\Opportunity\Models\Opportunity;
use App\Domain\Opportunity\Services\OpportunityReadinessService;
use App\Domain\Opportunity\Services\OpportunityWorkflowService;
use App\Filament\Resources\Operations\MOUResource;
use App\Filament\Resources\Operations\OpportunityResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

class ViewOpportunity extends ViewRecord
{
    protected static string $resource = OpportunityResource::class;

    public function getSubheading(): ?Htmlable
    {
        /** @var Opportunity $record */
        $record = $this->getRecord();
        $status = $record->status ?? OpportunityStatus::NEW;
        $statusLabel = e($status->getLabel());

        $ownerBadge = $record->owner_name
            ? '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200">👤 ' . e($record->owner_name) . '</span>'
            : '';

        $rentBadge = $record->expected_rent > 0
            ? '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-900/60 dark:text-emerald-200">💰 ₹' . number_format((float) $record->expected_rent) . ' /mo</span>'
            : '';

        $mouBadge = '';
        if ($record->mou) {
            $mouBadge = '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-blue-100 text-blue-800 dark:bg-blue-900/60 dark:text-blue-200">📄 MOU #' . e($record->mou->number) . '</span>';
        } elseif ($record->status === OpportunityStatus::READY_FOR_MOU) {
            $mouBadge = '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-800 dark:bg-amber-900/60 dark:text-amber-200 animate-pulse">⏳ Ready for MOU Creation</span>';
        }

        return new HtmlString(
            '<div class="flex items-center gap-2 text-sm text-gray-500 mt-1 flex-wrap">' .
            '<span>Status: <strong class="text-gray-900 dark:text-gray-100">' . $statusLabel . '</strong></span>' .
            ($ownerBadge ? '<span class="text-gray-300 dark:text-gray-700">&bull;</span>' . $ownerBadge : '') .
            ($rentBadge ? '<span class="text-gray-300 dark:text-gray-700">&bull;</span>' . $rentBadge : '') .
            ($mouBadge ? '<span class="text-gray-300 dark:text-gray-700">&bull;</span>' . $mouBadge : '') .
            '</div>'
        );
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->visible(fn (Opportunity $record) => OpportunityResource::canEdit($record)),

            Action::make('markReadyForMou')
                ->label('Ready For MOU')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Mark Opportunity as Ready for MOU')
                ->modalDescription('Confirm that initial owner consultations and commercial terms have been validated. This will unlock official MOU agreement generation.')
                ->modalSubmitActionLabel('Yes, Mark Ready for MOU')
                ->modalIcon('heroicon-o-check-badge')
                ->visible(fn (Opportunity $record) => $record->status === OpportunityStatus::NEW)
                ->action(function (Opportunity $record) {
                    $readiness = app(OpportunityReadinessService::class)->canCreateMOU($record);
                    if (! $readiness['is_ready']) {
                        Notification::make()
                            ->title('Cannot Mark as Ready')
                            ->body(implode(' ', $readiness['errors']))
                            ->danger()
                            ->persistent()
                            ->send();

                        return;
                    }
                    app(OpportunityWorkflowService::class)->markReadyForMou($record);
                    Notification::make()->title('Opportunity marked as Ready for MOU')->success()->send();
                }),

            Action::make('manageMou')
                ->label(fn (Opportunity $record) => Mou::where('opportunity_id', $record->id)->exists() ? 'Open MOU Agreement' : 'Create Onboarding MOU')
                ->icon('heroicon-o-document-text')
                ->color('primary')
                ->visible(fn (Opportunity $record) => in_array($record->status, [OpportunityStatus::READY_FOR_MOU, OpportunityStatus::CONVERTED]))
                ->requiresConfirmation(fn (Opportunity $record) => ! Mou::where('opportunity_id', $record->id)->exists())
                ->modalHeading('Initialize Onboarding MOU')
                ->modalDescription('This will launch the MOU workspace and pre-populate owner, property estimates, and financial terms.')
                ->modalSubmitActionLabel('Yes, Launch MOU Workspace')
                ->modalIcon('heroicon-o-document-plus')
                ->action(function (Opportunity $record) {
                    $mou = Mou::where('opportunity_id', $record->id)->first();
                    if ($mou) {
                        return redirect(MOUResource::getUrl('view', ['record' => $mou]));
                    }

                    return redirect(MOUResource::getUrl('create', ['opportunity_id' => $record->id]));
                }),

            Action::make('closeLost')
                ->label('Close Lost')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn (Opportunity $record) => ! in_array($record->status, [OpportunityStatus::CLOSED_LOST, OpportunityStatus::CANCELLED, OpportunityStatus::CONVERTED]))
                ->modalHeading('Mark Lead as Closed / Lost')
                ->modalDescription('Specify the reason for losing this opportunity.')
                ->form([
                    Textarea::make('notes')->label('Reason for losing')->placeholder('Enter details regarding why this lead was lost...')->required(),
                ])
                ->action(fn (Opportunity $record, array $data) => app(OpportunityWorkflowService::class)->closeLost($record, $data['notes'] ?? null)),
        ];
    }
}
