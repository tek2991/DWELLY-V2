<?php

namespace App\Filament\Resources\Properties\Pages;


use App\Domain\Property\Models\Property;

use App\Filament\Resources\Properties\PropertyResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditProperty extends EditRecord
{
    protected static string $resource = PropertyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('financials')
                ->label('Financials & MOU')
                ->icon('heroicon-o-currency-rupee')
                ->color('success')
                ->tooltip('View active pricing versions, bank details, and linked MOU')
                ->url(fn (Property $record): string => PropertyResource::getUrl('financials', ['record' => $record])),

            Action::make('onboarding')
                ->label('Onboarding Dashboard')
                ->icon('heroicon-o-clipboard-document-check')
                ->color('warning')
                ->tooltip('Open onboarding setup checklist and progress tracker')
                ->hidden(fn (Property $record): bool => $record->onboardingProject?->status === 'Activated')
                ->url(fn (Property $record): string => PropertyResource::getUrl('onboarding', ['record' => $record])),

            DeleteAction::make(),
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

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    public function getSubheading(): ?\Illuminate\Contracts\Support\Htmlable
    {
        /** @var Property $record */
        $record = $this->getRecord();
        $statusStr = $record->status ?? 'Draft';
        $color = match (strtolower($statusStr)) {
            'vacant' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/60 dark:text-emerald-200 font-bold',
            'occupied' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/60 dark:text-blue-200 font-semibold',
            'onboarding' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/60 dark:text-amber-200 font-semibold animate-pulse',
            'maintenance', 'under maintenance' => 'bg-rose-100 text-rose-800 dark:bg-rose-900/60 dark:text-rose-200 font-semibold',
            default => 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200',
        };

        $codeBadge = $record->code
            ? '<span class="inline-flex items-center px-2.5 py-0.5 rounded text-xs font-mono font-bold bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200 border border-gray-300 dark:border-gray-700">#' . e($record->code) . '</span>'
            : '';

        $rent = $record->pricingVersions()->latest('effective_from')->value('rent');
        $rentBadge = $rent > 0
            ? '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-900/60 dark:text-emerald-200">💰 ₹' . number_format((float) $rent) . ' /mo</span>'
            : '';

        $onboardingAlert = '';
        if ($record->isLockedDuringOnboarding()) {
            $onboardingAlert = '<div class="mt-2 text-xs font-medium text-amber-700 dark:text-amber-300 bg-amber-50 dark:bg-amber-950/50 border border-amber-200 dark:border-amber-900 rounded-md p-2 flex items-center gap-2">' .
                '<span>⚠️</span> <span>Property must complete onboarding checklist and be activated before primary edits can be submitted.</span>' .
                '</div>';
        }

        return new \Illuminate\Support\HtmlString(
            '<div class="flex flex-col">' .
            '<div class="flex items-center gap-2 text-sm text-gray-500 mt-1 flex-wrap">' .
            '<span>Status: <strong class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs ' . $color . '">' . e(ucfirst($statusStr)) . '</strong></span>' .
            ($codeBadge ? '<span class="text-gray-300 dark:text-gray-700">&bull;</span>' . $codeBadge : '') .
            ($rentBadge ? '<span class="text-gray-300 dark:text-gray-700">&bull;</span>' . $rentBadge : '') .
            '</div>' .
            $onboardingAlert .
            '</div>'
        );
    }

    protected function getHeaderWidgets(): array
    {
        return [
            \App\Filament\Resources\Properties\Widgets\PropertyAuditWidget::class,
        ];
    }

    protected function getFormActions(): array
    {
        if ($this->record->isLockedDuringOnboarding()) {
            return [];
        }

        return parent::getFormActions();
    }

    public function form(\Filament\Schemas\Schema $schema): \Filament\Schemas\Schema
    {
        return parent::form($schema)
            ->disabled(fn (\App\Domain\Property\Models\Property $record): bool => 
                $record->isLockedDuringOnboarding()
            );
    }
}
