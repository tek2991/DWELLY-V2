<?php

namespace App\Filament\Resources\Properties\Tables;

use App\Domain\Property\Enums\PropertyStatus;
use App\Domain\Property\Models\Property;
use App\Filament\Resources\Properties\PropertyResource;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PropertiesTable
{
    public static function configure(Table $table): Table
    {
        return $table
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
                        if ($city) {
                            return "📍 {$city}";
                        }

                        return $record->address_line_1 ? "📍 {$record->address_line_1}" : null;
                    }),

                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(function ($state) {
                        if ($state instanceof PropertyStatus) {
                            return $state->getLabel();
                        }
                        $statusEnum = PropertyStatus::fromValue((string) $state);

                        return $statusEnum?->getLabel() ?? ucfirst((string) $state);
                    })
                    ->color(function ($state) {
                        if ($state instanceof PropertyStatus) {
                            return $state->getColor();
                        }
                        $statusEnum = PropertyStatus::fromValue((string) $state);

                        return $statusEnum?->getColor() ?? match (strtolower((string) $state)) {
                            'vacant' => 'success',
                            'occupied' => 'primary',
                            'onboarding' => 'warning',
                            'maintenance' => 'danger',
                            default => 'gray',
                        };
                    })
                    ->icon(function ($state) {
                        if ($state instanceof PropertyStatus) {
                            return $state->getIcon();
                        }
                        $statusEnum = PropertyStatus::fromValue((string) $state);

                        return $statusEnum?->getIcon();
                    })
                    ->sortable(),

                TextColumn::make('pricingVersions.rent')
                    ->label('Active Rent')
                    ->state(fn (Property $record) => $record->pricingVersions()->latest('effective_from')->value('rent'))
                    ->money('INR')
                    ->placeholder('—')
                    ->sortable(),

                ToggleColumn::make('is_listed')
                    ->label('Listed')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'Vacant' => 'Vacant',
                        'Occupied' => 'Occupied',
                        'Onboarding' => 'Onboarding',
                        'Maintenance' => 'Maintenance',
                        'Draft' => 'Draft',
                    ]),
            ])
            ->recordActions([
                Action::make('onboarding')
                    ->label('Onboarding')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->color('warning')
                    ->tooltip('Open Onboarding Dashboard')
                    ->hidden(fn (Property $record): bool => $record->onboardingProject?->status === 'Activated')
                    ->url(fn (Property $record): string => PropertyResource::getUrl('onboarding', ['record' => $record])),

                Action::make('financials')
                    ->label('Financials')
                    ->icon('heroicon-o-currency-rupee')
                    ->color('success')
                    ->tooltip('View Financial Terms & MOU')
                    ->url(fn (Property $record): string => PropertyResource::getUrl('financials', ['record' => $record])),

                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
