<?php

namespace App\Filament\Widgets;

use App\Domain\Property\Enums\OnboardingStatus;
use App\Domain\Property\Models\Property;
use App\Domain\Property\Services\PropertyOnboardingValidator;
use App\Filament\Resources\Properties\PropertyResource;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class OnboardingPropertiesWidget extends BaseWidget
{
    protected static ?int $sort = 6;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Property::query()
                    ->where('status', 'Onboarding')
                    ->orWhereHas('onboardingProject', fn ($q) => $q->where('status', '!=', 'Activated'))
                    ->with(['localityRef.city', 'onboardingProject'])
                    ->latest('updated_at')
            )
            ->columns([
                TextColumn::make('code')
                    ->label('Code')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->copyable()
                    ->copyMessage('Property code copied')
                    ->tooltip('Click to copy Property Code')
                    ->placeholder('Unassigned'),

                TextColumn::make('building_name')
                    ->label('Property / Building')
                    ->searchable()
                    ->sortable()
                    ->weight('semibold')
                    ->description(function (Property $record): ?string {
                        $locality = $record->localityRef?->name;
                        $city = $record->localityRef?->city?->name ?? $record->city;
                        if ($locality && $city) {
                            return "📍 {$locality}, {$city}";
                        }
                        if ($locality) {
                            return "📍 {$locality}";
                        }

                        return $record->address_line_1 ? "📍 {$record->address_line_1}" : null;
                    }),

                TextColumn::make('onboarding_progress')
                    ->label('Progress')
                    ->state(function (Property $record): string {
                        $validator = app(PropertyOnboardingValidator::class);
                        $data = $validator->validate($record);

                        return $data['progress'] . '%';
                    })
                    ->badge()
                    ->color(fn (string $state): string => (int) $state === 100 ? 'success' : 'warning'),

                TextColumn::make('onboardingProject.status')
                    ->label('Stage')
                    ->placeholder('Draft')
                    ->badge()
                    ->formatStateUsing(function ($state) {
                        $enum = OnboardingStatus::fromValue((string) $state);

                        return $enum?->getLabel() ?? ucfirst((string) $state);
                    })
                    ->color(function ($state) {
                        $enum = OnboardingStatus::fromValue((string) $state);

                        return $enum?->getColor() ?? match ($state) {
                            'Pending Review' => 'warning',
                            'Changes Requested' => 'danger',
                            'Activated' => 'success',
                            default => 'gray',
                        };
                    })
                    ->icon(function ($state) {
                        $enum = OnboardingStatus::fromValue((string) $state);

                        return $enum?->getIcon();
                    }),

                TextColumn::make('updated_at')
                    ->label('Last Updated')
                    ->since(),
            ])
            ->actions([
                Action::make('open_onboarding')
                    ->label('Checklist')
                    ->icon('heroicon-m-clipboard-document-check')
                    ->color('primary')
                    ->url(fn (Property $record): string => PropertyResource::getUrl('onboarding', ['record' => $record])),
            ])
            ->heading('Properties in Onboarding Pipeline')
            ->emptyStateHeading('No Properties in Onboarding')
            ->emptyStateDescription('All onboarding projects are complete or active.');
    }
}
