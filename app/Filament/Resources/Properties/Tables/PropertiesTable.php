<?php

namespace App\Filament\Resources\Properties\Tables;

use App\Domain\Property\Enums\PropertyStatus;
use App\Domain\Property\Models\Property;
use App\Filament\Resources\Properties\Pages\PropertyFinancials;
use App\Filament\Resources\Properties\PropertyResource;
use Carbon\CarbonInterface;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class PropertiesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['openMaintenanceRequests']))
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
                    ->visible(fn (): bool => PropertyFinancials::canAccess())
                    ->sortable(),

                TextColumn::make('maintenance_status')
                    ->label('Maintenance')
                    ->state(function (Property $record): ?string {
                        $openTickets = $record->openMaintenanceRequests;
                        if ($openTickets->isEmpty()) {
                            return null;
                        }

                        $count = $openTickets->count();
                        $oldest = $openTickets->sortBy('created_at')->first();
                        $age = $oldest?->created_at?->diffForHumans();

                        return $count === 1
                            ? "1 Ticket • {$age}"
                            : "{$count} Tickets • oldest {$age}";
                    })
                    ->badge()
                    ->color('danger')
                    ->icon('heroicon-o-wrench-screwdriver')
                    ->placeholder('—')
                    ->description(function (Property $record): ?string {
                        $oldest = $record->openMaintenanceRequests->sortBy('created_at')->first();
                        if (! $oldest) {
                            return null;
                        }

                        $ticketNum = $oldest->ticket_number ? "#{$oldest->ticket_number}: " : '';

                        return Str::limit("{$ticketNum}{$oldest->title}", 35);
                    })
                    ->tooltip(function (Property $record): ?string {
                        $openTickets = $record->openMaintenanceRequests;
                        if ($openTickets->isEmpty()) {
                            return null;
                        }

                        return $openTickets->map(fn ($t) => ($t->ticket_number ? "#{$t->ticket_number}: " : '') . "{$t->title} (Created {$t->created_at->diffForHumans()})")->implode("\n");
                    }),

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
                    ->visible(fn (Property $record): bool => PropertyFinancials::canAccess(['record' => $record->id]))
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
