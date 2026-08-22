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

    protected function afterSave(): void
    {
        $this->dispatch('refresh-onboarding-progress');
    }

    public function getSubheading(): string | \Illuminate\Support\HtmlString | null
    {
        /** @var Property $record */
        $record = $this->getRecord();
        if (! $record) {
            return null;
        }

        $code = $record->code;
        $name = $record->building_name ?? $record->address_line_1 ?? 'Property #' . $record->id;
        $propertyUrl = PropertyResource::getUrl('edit', ['record' => $record]);
        $stage = $record->onboardingProject?->status ?? 'Draft';

        $stageColor = match ($stage) {
            'Activated' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/60 dark:text-emerald-200 font-bold',
            'Pending Review' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/60 dark:text-amber-200 font-semibold animate-pulse',
            'Changes Requested' => 'bg-rose-100 text-rose-800 dark:bg-rose-900/60 dark:text-rose-200 font-semibold',
            default => 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200',
        };

        $codeBadge = $code
            ? '<span class="inline-flex items-center px-2.5 py-0.5 rounded text-xs font-mono font-bold bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200 border border-gray-300 dark:border-gray-700">#' . e($code) . '</span>'
            : '';

        $owner = $record->mous()->latest()->first()?->party;
        $ownerBadge = $owner
            ? '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200">👤 ' . e($owner->display_name) . '</span>'
            : '';

        return new \Illuminate\Support\HtmlString(
            '<div class="flex items-center gap-2 text-sm font-medium mt-1 flex-wrap">' .
                '<span>Stage: <strong class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs ' . $stageColor . '">' . e($stage) . '</strong></span>' .
                ($codeBadge ? '<span class="text-gray-300 dark:text-gray-700">&bull;</span>' . $codeBadge : '') .
                '<span class="text-gray-300 dark:text-gray-700">&bull;</span>' .
                '<span class="text-gray-900 dark:text-white font-semibold text-sm">' . e($name) . '</span>' .
                ($ownerBadge ? '<span class="text-gray-300 dark:text-gray-700">&bull;</span>' . $ownerBadge : '') .
                '<a href="' . $propertyUrl . '" class="inline-flex items-center gap-1 text-xs font-semibold text-primary-600 hover:text-primary-700 hover:underline dark:text-primary-400 ml-1" title="View Property Profile">' .
                    '<span>Property Profile &rarr;</span>' .
                '</a>' .
            '</div>'
        );
    }
}
