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
                ->label('Financials')
                ->icon('heroicon-o-currency-rupee')
                ->color('success')
                ->url(fn (\App\Domain\Property\Models\Property $record): string => \App\Filament\Resources\Properties\PropertyResource::getUrl('financials', ['record' => $record])),
            Action::make('onboarding')
                ->label('Onboarding')
                ->icon('heroicon-o-clipboard-document-check')
                ->color('warning')
                ->hidden(fn (\App\Domain\Property\Models\Property $record): bool => $record->onboardingProject?->status === 'Activated')
                ->url(fn (\App\Domain\Property\Models\Property $record): string => \App\Filament\Resources\Properties\PropertyResource::getUrl('onboarding', ['record' => $record])),
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
        $status = $this->record->status ?? 'Draft';
        $color = match($status) {
            'Vacant' => 'success',
            'Occupied' => 'primary',
            'Maintenance' => 'warning',
            default => 'gray',
        };

        $declaration = $this->record->isLockedDuringOnboarding()
            ? "<div class='text-warning-600 text-sm font-medium mt-2'>Property must complete onboarding and be activated before changes can be made.</div>"
            : "";

        return new \Illuminate\Support\HtmlString(
            \Illuminate\Support\Facades\Blade::render(
                "<div class='flex flex-col'>
                    <div class='flex items-center gap-2 text-sm text-gray-500'>
                        <span>Status:</span>
                        <x-filament::badge color=\"{$color}\">{$status}</x-filament::badge>
                    </div>
                    {$declaration}
                </div>"
            )
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
