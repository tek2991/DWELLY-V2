<?php

namespace App\Filament\Resources\Properties\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Model;

class OnboardingProgressWidget extends Widget
{
    protected string $view = 'filament.resources.properties.pages.onboarding-dashboard';

    public ?Model $record = null;
    
    // Filament injects the record into widgets on the record page if defined as a public property
    
    protected int | string | array $columnSpan = 'full';

    public function activateProperty()
    {
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
    }
}
