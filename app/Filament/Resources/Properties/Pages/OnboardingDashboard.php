<?php

namespace App\Filament\Resources\Properties\Pages;

use App\Domain\Property\Models\OnboardingProject;
use App\Domain\Property\Models\Property;
use App\Domain\Property\Services\PropertyOnboardingValidator;
use App\Filament\Resources\Properties\PropertyResource;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class OnboardingDashboard extends EditRecord
{
    protected static string $resource = PropertyResource::class;

    protected static ?string $title = 'Onboarding Dashboard';

    protected function getHeaderWidgets(): array
    {
        return [
            \App\Filament\Resources\Properties\Widgets\OnboardingProgressWidget::class,
        ];
    }

    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }

    public function getContentTabLabel(): ?string
    {
        return 'Property Overview';
    }

    public function getContentTabIcon(): ?string
    {
        return 'heroicon-o-information-circle';
    }

    public function mount(int | string $record): void
    {
        parent::mount($record);

        // Ensure OnboardingProject exists when this page is loaded
        if (!$this->record->onboardingProject) {
            OnboardingProject::create([
                'property_id' => $this->record->id,
                'status' => 'Draft',
            ]);
            $this->record->load('onboardingProject');
        }

        if ($this->record->onboardingProject->status === 'Activated') {
            Notification::make()
                ->warning()
                ->title('Property already activated')
                ->body('This property has already completed onboarding.')
                ->send();
                
            $this->redirect($this->getResource()::getUrl('edit', ['record' => $this->record]));
        }
    }


}
