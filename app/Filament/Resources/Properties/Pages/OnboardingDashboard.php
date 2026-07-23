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

    public function getSubheading(): string | \Illuminate\Support\HtmlString | null
    {
        if (!$this->record) {
            return null;
        }

        $code = $this->record->code;
        $name = $this->record->building_name ?? $this->record->address_line_1 ?? 'Property #' . $this->record->id;
        $propertyUrl = PropertyResource::getUrl('edit', ['record' => $this->record]);

        $codeBadge = $code
            ? '<span class="inline-flex items-center gap-1 font-mono text-xs font-semibold px-2 py-0.5 rounded bg-primary-50 text-primary-700 dark:bg-primary-950/50 dark:text-primary-300 ring-1 ring-inset ring-primary-600/20">' . e($code) . '</span>'
            : '';

        return new \Illuminate\Support\HtmlString(
            '<div class="flex items-center gap-2 text-sm font-medium mt-1">' .
                $codeBadge .
                '<span class="text-gray-900 dark:text-white font-semibold text-base">' . e($name) . '</span>' .
                '<a href="' . $propertyUrl . '" class="inline-flex items-center justify-center p-1 rounded bg-primary-100 hover:bg-primary-200 text-primary-700 dark:bg-primary-900/60 dark:hover:bg-primary-900 dark:text-primary-300 transition-colors" title="View Property Profile" aria-label="View Property Profile">' .
                    '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>' .
                '</a>' .
            '</div>'
        );
    }
}
