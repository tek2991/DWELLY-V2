<?php

namespace App\Filament\Resources\Properties\Widgets;

use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
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
    
    // Filament injects the record into widgets on the record page if defined as a public property
    
    protected int | string | array $columnSpan = 'full';

    public function activatePropertyAction(): Action
    {
        return Action::make('activateProperty')
            ->label('Activate Property')
            ->icon('heroicon-o-check-badge')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Activate Property')
            ->modalDescription('Are you sure you want to activate this property? It will be marked as Vacant and available for operations.')
            ->modalIcon('heroicon-o-check-badge')
            ->modalSubmitActionLabel('Yes, Activate')
            ->disabled(function (): bool {
                if (!$this->record) {
                    return true;
                }

                $validationData = app(\App\Domain\Property\Services\PropertyOnboardingValidator::class)->validate($this->record);
                return ($validationData['progress'] ?? 0) != 100 || $this->record->onboardingProject?->status === 'Activated';
            })
            ->action(function () {
                if (!$this->record) {
                    return;
                }

                $validationData = app(\App\Domain\Property\Services\PropertyOnboardingValidator::class)->validate($this->record);
                if ($validationData['progress'] != 100 || $this->record->onboardingProject?->status === 'Activated') {
                    return;
                }

                // Update onboarding status
                $this->record->onboardingProject()->update([
                    'status' => 'Activated',
                ]);

                // Update property status
                $this->record->update([
                    'status' => 'Vacant',
                ]);

                \Filament\Notifications\Notification::make()
                    ->success()
                    ->title('Property Activated')
                    ->body('All onboarding steps are complete and the property is now Vacant.')
                    ->send();

                $this->redirect(\App\Filament\Resources\Properties\PropertyResource::getUrl('index'));
            });
    }
}

