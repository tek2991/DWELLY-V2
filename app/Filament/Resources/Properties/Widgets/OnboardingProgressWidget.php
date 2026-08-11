<?php

namespace App\Filament\Resources\Properties\Widgets;

use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Model;

class OnboardingProgressWidget extends Widget implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;

    protected string $view = 'filament.resources.properties.pages.onboarding-dashboard';

    public ?Model $record = null;

    protected int | string | array $columnSpan = 'full';

    public function canUserReview(): bool
    {
        $user = auth()->user();
        if (!$user) {
            return false;
        }

        if ($user->roles->isEmpty()) {
            return true;
        }

        return $user->hasAnyRole(['Business Owner', 'Operations Manager', 'Admin', 'Super Admin']);
    }

    public function submitForReviewAction(): Action
    {
        return Action::make('submitForReview')
            ->label('Submit for Review')
            ->icon('heroicon-o-paper-airplane')
            ->color('primary')
            ->requiresConfirmation()
            ->modalHeading('Submit for Operations Review')
            ->modalDescription('Are you sure all onboarding checklist steps are complete and ready for operations review?')
            ->modalIcon('heroicon-o-paper-airplane')
            ->modalSubmitActionLabel('Yes, Submit for Review')
            ->disabled(function (): bool {
                if (!$this->record) {
                    return true;
                }

                $validationData = app(\App\Domain\Property\Services\PropertyOnboardingValidator::class)->validate($this->record);
                $status = $this->record->onboardingProject?->status;

                return ($validationData['progress'] ?? 0) != 100 || in_array($status, ['Pending Review', 'Activated']);
            })
            ->action(function () {
                if (!$this->record) {
                    return;
                }

                $validationData = app(\App\Domain\Property\Services\PropertyOnboardingValidator::class)->validate($this->record);
                if (($validationData['progress'] ?? 0) != 100) {
                    return;
                }

                $this->record->onboardingProject()->update([
                    'status' => 'Pending Review',
                    'submitted_at' => now(),
                ]);

                activity()
                    ->performedOn($this->record)
                    ->causedBy(auth()->user())
                    ->log('Onboarding: Submitted for Operations Review');

                $this->record->refresh();

                \Filament\Notifications\Notification::make()
                    ->success()
                    ->title('Submitted for Review')
                    ->body('Onboarding checklist submitted. Awaiting Operations Manager review and activation.')
                    ->send();
            });
    }

    public function activatePropertyAction(): Action
    {
        return Action::make('activateProperty')
            ->label('Approve & Activate Property')
            ->icon('heroicon-o-check-badge')
            ->color('success')
            ->visible(fn (): bool => $this->canUserReview())
            ->requiresConfirmation()
            ->modalHeading('Approve & Activate Property')
            ->modalDescription('Are you sure you want to approve and activate this property? It will be marked as Vacant and available for operations.')
            ->modalIcon('heroicon-o-check-badge')
            ->modalSubmitActionLabel('Yes, Approve & Activate')
            ->disabled(function (): bool {
                if (!$this->record) {
                    return true;
                }

                return $this->record->onboardingProject?->status === 'Activated';
            })
            ->action(function () {
                if (!$this->record) {
                    return;
                }

                $this->record->onboardingProject()->update([
                    'status' => 'Activated',
                    'reviewer_id' => auth()->id(),
                    'reviewed_at' => now(),
                ]);

                $this->record->update([
                    'status' => 'Vacant',
                ]);

                activity()
                    ->performedOn($this->record)
                    ->causedBy(auth()->user())
                    ->log('Onboarding: Approved & Activated Property (Status updated to Vacant)');

                \Filament\Notifications\Notification::make()
                    ->success()
                    ->title('Property Activated')
                    ->body('All onboarding steps approved. Property is now Vacant.')
                    ->send();

                $this->redirect(\App\Filament\Resources\Properties\PropertyResource::getUrl('index'));
            });
    }

    public function requestChangesAction(): Action
    {
        return Action::make('requestChanges')
            ->label('Request Changes')
            ->icon('heroicon-o-arrow-path')
            ->color('warning')
            ->visible(fn (): bool => $this->canUserReview())
            ->modalHeading('Request Onboarding Revisions')
            ->modalDescription('Provide feedback describing what needs correction before property activation.')
            ->modalIcon('heroicon-o-exclamation-triangle')
            ->form([
                Textarea::make('review_notes')
                    ->label('Revision Notes / Feedback')
                    ->placeholder('Specify missing photos, incorrect utility data, or missing documents...')
                    ->required()
                    ->rows(3),
            ])
            ->action(function (array $data) {
                if (!$this->record) {
                    return;
                }

                $reviewNotes = $data['review_notes'] ?? '';

                $this->record->onboardingProject()->update([
                    'status' => 'Changes Requested',
                    'review_notes' => $reviewNotes,
                    'reviewer_id' => auth()->id(),
                    'reviewed_at' => now(),
                ]);

                activity()
                    ->performedOn($this->record)
                    ->causedBy(auth()->user())
                    ->withProperties(['review_notes' => $reviewNotes])
                    ->log('Onboarding: Revisions Requested — Feedback: ' . $reviewNotes);

                $this->record->refresh();

                \Filament\Notifications\Notification::make()
                    ->warning()
                    ->title('Revisions Requested')
                    ->body('Onboarding status updated to Changes Requested. Feedback sent to team.')
                    ->send();
            });
    }
}
