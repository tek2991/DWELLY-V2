<?php

namespace App\Filament\Widgets;

use App\Domain\Property\Models\Property;
use App\Domain\Property\Services\PropertyOnboardingValidator;
use App\Filament\Resources\Properties\PropertyResource;
use Filament\Actions\Action;
use Filament\Tables;
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
                    ->with(['localityRef', 'onboardingProject'])
                    ->latest('updated_at')
            )
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Property')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('localityRef.name')
                    ->label('Locality')
                    ->placeholder('N/A')
                    ->searchable(),

                Tables\Columns\TextColumn::make('onboarding_progress')
                    ->label('Progress')
                    ->state(function (Property $record): string {
                        $validator = app(PropertyOnboardingValidator::class);
                        $data = $validator->validate($record);
                        return $data['progress'] . '%';
                    })
                    ->badge()
                    ->color(fn (string $state): string => (int)$state === 100 ? 'success' : 'warning'),

                Tables\Columns\TextColumn::make('onboardingProject.status')
                    ->label('Stage')
                    ->placeholder('Draft')
                    ->badge(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Last Updated')
                    ->since(),
            ])
            ->actions([
                Action::make('open_onboarding')
                    ->label('Onboarding Checklist')
                    ->icon('heroicon-m-arrow-right-circle')
                    ->url(fn (Property $record): string => PropertyResource::getUrl('onboarding', ['record' => $record])),
            ])
            ->heading('Properties in Onboarding Pipeline')
            ->emptyStateHeading('No Properties in Onboarding')
            ->emptyStateDescription('All onboarding projects are complete or active.');
    }
}
