<?php

namespace App\Filament\Resources\Properties\RelationManagers;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class EstablishmentsRelationManager extends RelationManager
{
    use \App\Filament\Resources\Properties\RelationManagers\Traits\LocksDuringPropertyOnboarding;

    protected static string $relationship = 'establishments';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('city_id')
                    ->label('Filter by City')
                    ->options(fn () => \App\Domain\Geographic\Models\City::pluck('name', 'id'))
                    ->default(fn (RelationManager $livewire) => $livewire->getOwnerRecord()->city_id)
                    ->live()
                    ->dehydrated(false),
                Select::make('establishment_id')
                    ->label('Establishment')
                    ->options(function (Get $get, RelationManager $livewire) {
                        $cityId = $get('city_id') ?? $livewire->getOwnerRecord()->city_id;
                        $query = \App\Domain\Property\Models\Establishment::query();
                        if ($cityId) {
                            $query->where('city_id', $cityId);
                        }
                        return $query->pluck('name', 'id');
                    })
                    ->searchable()
                    ->preload()
                    ->required()
                    ->unique(modifyRuleUsing: function (\Illuminate\Validation\Rules\Unique $rule, RelationManager $livewire) {
                        return $rule->where('property_id', $livewire->getOwnerRecord()->id);
                    }, ignoreRecord: true)
                    ->createOptionForm([
                        Select::make('city_id')
                            ->label('City')
                            ->options(fn () => \App\Domain\Geographic\Models\City::pluck('name', 'id'))
                            ->default(fn (RelationManager $livewire) => $livewire->getOwnerRecord()->city_id)
                            ->required(),
                        Select::make('establishment_type_id')
                            ->label('Establishment Type')
                            ->options(fn() => \Illuminate\Support\Facades\DB::table('establishment_types')->pluck('name', 'id'))
                            ->required(),
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                    ]),
                TextInput::make('distance_km')
                    ->numeric()
                    ->label('Distance (KM)')
                    ->required(),
                TextInput::make('travel_time_minutes')
                    ->numeric()
                    ->label('Travel Time (Mins)'),
                Textarea::make('remarks')
                    ->maxLength(65535)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('establishment_id')
            ->columns([
                TextColumn::make('establishment.name')
                    ->label('Establishment Name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('establishment.city.name')
                    ->label('City')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('distance_km')
                    ->label('Distance (KM)')
                    ->sortable(),
                TextColumn::make('travel_time_minutes')
                    ->label('Time (Mins)')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('city')
                    ->relationship('establishment.city', 'name')
                    ->label('City')
                    ->default(fn (RelationManager $livewire) => $livewire->getOwnerRecord()->city_id),
            ])
            ->headerActions([
                CreateAction::make(),
                Action::make('bulkCreate')
                    ->hidden(fn (RelationManager $livewire) => $livewire->isReadOnly())
                    ->label('Bulk Create')
                    ->icon('heroicon-o-squares-plus')
                    ->form(function (RelationManager $livewire) {
                        $property = $livewire->getOwnerRecord();
                        $cityId = $property->city_id;
                        $initialCityIds = $cityId ? [$cityId] : [];

                        $initialTypes = !empty($initialCityIds)
                            ? \App\Domain\Property\Models\EstablishmentType::whereHas('cities', fn ($q) => $q->whereIn('cities.id', $initialCityIds))->distinct()->orderBy('name')->get()
                            : collect();

                        $defaultItems = $initialTypes->map(fn ($type) => [
                            'is_default' => true,
                            'establishment_type_id' => $type->id,
                            'establishment_id' => null,
                            'distance_km' => null,
                            'travel_time_minutes' => null,
                        ])->toArray();

                        return [
                            Select::make('selected_cities')
                                ->label('Filter & Suggest by Cities')
                                ->placeholder('Select cities...')
                                ->helperText('Select cities to automatically suggest mapped establishment types and filter establishment names.')
                                ->options(fn () => \App\Domain\Geographic\Models\City::orderBy('name')->pluck('name', 'id'))
                                ->multiple()
                                ->searchable()
                                ->preload()
                                ->default($initialCityIds)
                                ->live()
                                ->afterStateUpdated(function (Set $set, ?array $state, Get $get) {
                                    $selectedCityIds = $state ?? [];
                                    if (empty($selectedCityIds)) {
                                        $set('establishments', []);
                                        return;
                                    }

                                    $types = \App\Domain\Property\Models\EstablishmentType::whereHas('cities', function ($q) use ($selectedCityIds) {
                                        $q->whereIn('cities.id', $selectedCityIds);
                                    })->distinct()->orderBy('name')->get();

                                    $currentItems = $get('establishments') ?? [];
                                    $existingByType = [];
                                    foreach ($currentItems as $item) {
                                        if (!empty($item['establishment_type_id'])) {
                                            $existingByType[$item['establishment_type_id']] = $item;
                                        }
                                    }

                                    $newItems = $types->map(function ($type) use ($existingByType) {
                                        if (isset($existingByType[$type->id])) {
                                            return $existingByType[$type->id];
                                        }
                                        return [
                                            'is_default' => true,
                                            'establishment_type_id' => $type->id,
                                            'establishment_id' => null,
                                            'distance_km' => null,
                                            'travel_time_minutes' => null,
                                        ];
                                    })->values()->toArray();

                                    $set('establishments', $newItems);
                                }),
                            Repeater::make('establishments')
                                ->label('Establishments')
                                ->default($defaultItems)
                                ->addActionLabel('Add More')
                                ->reorderable(false)
                                ->itemHeaders(false)
                                ->compact()
                                ->schema([
                                    \Filament\Forms\Components\Hidden::make('is_default'),
                                    Grid::make(12)
                                        ->schema([
                                             Select::make('establishment_type_id')
                                                ->label('Establishment Type')
                                                ->options(fn () => \App\Domain\Property\Models\EstablishmentType::orderBy('name')->pluck('name', 'id'))
                                                ->required()
                                                ->live()
                                                ->disabled(fn (Get $get) => (bool) $get('is_default'))
                                                ->dehydrated()
                                                ->markAsRequired()
                                                ->columnSpan(3),
                                            Select::make('establishment_id')
                                                ->label('Establishment Name')
                                                ->options(function (Get $get, RelationManager $livewire) {
                                                    $typeId = $get('establishment_type_id');
                                                    $selectedCities = $get('../../selected_cities');
                                                    if ($selectedCities === null) {
                                                        $cityId = $livewire->getOwnerRecord()->city_id;
                                                        $selectedCities = $cityId ? [$cityId] : [];
                                                    }

                                                    $query = \App\Domain\Property\Models\Establishment::query();
                                                    if ($typeId) {
                                                        $query->where('establishment_type_id', $typeId);
                                                    }
                                                    if (!empty($selectedCities)) {
                                                        $query->where(function ($q) use ($selectedCities) {
                                                            $q->whereIn('city_id', $selectedCities)
                                                              ->orWhereNull('city_id');
                                                        });
                                                    }
                                                    return $query->pluck('name', 'id');
                                                })
                                                ->searchable()
                                                ->preload()
                                                ->createOptionForm([
                                                    TextInput::make('name')
                                                        ->label('Establishment Name')
                                                        ->required(),
                                                    Select::make('city_id')
                                                        ->label('City')
                                                        ->options(fn () => \App\Domain\Geographic\Models\City::orderBy('name')->pluck('name', 'id'))
                                                        ->default(fn (RelationManager $livewire) => $livewire->getOwnerRecord()->city_id)
                                                        ->searchable()
                                                        ->preload(),
                                                ])
                                                ->createOptionUsing(function (array $data, Get $get, RelationManager $livewire) {
                                                    $typeId = $get('establishment_type_id');
                                                    $cityId = $data['city_id'] ?? $livewire->getOwnerRecord()->city_id;

                                                    $establishment = \App\Domain\Property\Models\Establishment::create([
                                                        'name' => trim($data['name']),
                                                        'establishment_type_id' => $typeId,
                                                        'city_id' => $cityId,
                                                    ]);

                                                    return $establishment->id;
                                                })
                                                ->required(fn (Get $get) => filled($get('distance_km')) || filled($get('travel_time_minutes')))
                                                ->distinct()
                                                ->validationMessages([
                                                    'distinct' => 'This establishment is already selected in another row.',
                                                ])
                                                ->markAsRequired()
                                                ->columnSpan(4),
                                            TextInput::make('distance_km')
                                                ->label('Distance (KM)')
                                                ->numeric()
                                                ->required(fn (Get $get) => filled($get('establishment_id')))
                                                ->markAsRequired()
                                                ->columnSpan(2),
                                            TextInput::make('travel_time_minutes')
                                                ->label('Time (Mins)')
                                                ->numeric()
                                                ->columnSpan(2),
                                            \Filament\Schemas\Components\Actions::make([
                                                Action::make('deleteRow')
                                                    ->hiddenLabel()
                                                    ->icon('heroicon-m-trash')
                                                    ->color('danger')
                                                    ->iconButton()
                                                    ->tooltip('Remove row')
                                                    ->visible(fn (Get $get) => !$get('is_default'))
                                                    ->action(function (array $arguments, \Filament\Schemas\Components\Actions $component, Get $get, Set $set) {
                                                        $statePath = $component->getContainer()->getStatePath();
                                                        $key = last(explode('.', $statePath));

                                                        $repeater = $component->getContainer()->getParentComponent();
                                                        if ($repeater instanceof Repeater) {
                                                            $items = $repeater->getRawState();
                                                            if (isset($items[$key])) {
                                                                unset($items[$key]);
                                                                $repeater->rawState($items);
                                                                $repeater->callAfterStateUpdated();
                                                                $repeater->partiallyRender();
                                                            }
                                                        }

                                                        $state = $get('../../establishments');
                                                        if (is_array($state) && isset($state[$key])) {
                                                            unset($state[$key]);
                                                            $set('../../establishments', $state);
                                                        }
                                                    }),
                                            ])
                                            ->columnSpan(1)
                                            ->alignEnd(),
                                        ]),
                                ]),
                        ];
                    })
                    ->action(function (array $data, RelationManager $livewire) {
                        $property = $livewire->getOwnerRecord();
                        $items = $data['establishments'] ?? [];

                        $addedCount = 0;

                        foreach ($items as $item) {
                            $typeId = $item['establishment_type_id'] ?? null;
                            $estIdOrName = $item['establishment_id'] ?? $item['establishment_name'] ?? null;
                            $dist = $item['distance_km'] ?? null;
                            $time = $item['travel_time_minutes'] ?? null;

                            if (empty($estIdOrName) || $dist === null || $dist === '') {
                                continue;
                            }

                            $establishment = \App\Domain\Property\Models\Establishment::find($estIdOrName);
                            if (!$establishment) {
                                $establishment = \App\Domain\Property\Models\Establishment::firstOrCreate([
                                    'name' => trim($estIdOrName),
                                    'establishment_type_id' => $typeId,
                                    'city_id' => $property->city_id,
                                ]);
                            }

                            $existing = $property->establishments()
                                ->where('establishment_id', $establishment->id)
                                ->first();

                            if (!$existing) {
                                $property->establishments()->create([
                                    'establishment_id' => $establishment->id,
                                    'distance_km' => $dist,
                                    'travel_time_minutes' => $time,
                                ]);
                                $addedCount++;
                            } else {
                                $existing->update([
                                    'distance_km' => $dist,
                                    'travel_time_minutes' => $time ?? $existing->travel_time_minutes,
                                ]);
                                $addedCount++;
                            }
                        }

                        if ($addedCount > 0) {
                            \Filament\Notifications\Notification::make()
                                ->title("{$addedCount} Establishments mapped successfully")
                                ->success()
                                ->send();
                        } else {
                            \Filament\Notifications\Notification::make()
                                ->title("No establishment entries updated")
                                ->warning()
                                ->send();
                        }

                        $livewire->dispatch('refresh-onboarding-progress');
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
