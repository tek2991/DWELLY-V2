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
                        $cityName = null;
                        if ($cityId) {
                            $cityName = \App\Domain\Geographic\Models\City::find($cityId)?->name;
                        }
                        if (!$cityName && $property->city) {
                            $cityName = $property->city;
                        }

                        $noticeHtml = $cityName
                            ? "<div class=\"p-3 rounded-lg bg-primary-50 dark:bg-primary-950/40 border border-primary-200 dark:border-primary-800 text-sm text-primary-800 dark:text-primary-300 flex items-center gap-2\"><svg width=\"20\" height=\"20\" style=\"width:20px; height:20px; min-width:20px; min-height:20px; flex-shrink:0; display:inline-block;\" fill=\"none\" stroke=\"currentColor\" viewBox=\"0 0 24 24\"><path stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-width=\"2\" d=\"M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z\"/><path stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-width=\"2\" d=\"M15 11a3 3 0 11-6 0 3 3 0 016 0z\"/></svg><span>Suggestions filtered by city: <strong>{$cityName}</strong></span></div>"
                            : "<div class=\"p-3 rounded-lg bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800 text-sm text-amber-800 dark:text-amber-300\">Showing suggestions across all cities (No city set on property).</div>";

                        $types = \App\Domain\Property\Models\EstablishmentType::where('is_active', true)
                            ->orderBy('name')
                            ->get();

                        $defaultItems = $types->map(fn ($type) => [
                            'is_default' => true,
                            'establishment_type_id' => $type->id,
                            'establishment_name' => '',
                            'distance_km' => null,
                            'travel_time_minutes' => null,
                        ])->toArray();

                        return [
                            Placeholder::make('city_filter_notice')
                                ->hiddenLabel()
                                ->content(new \Illuminate\Support\HtmlString($noticeHtml)),
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
                                                    $cityId = $livewire->getOwnerRecord()->city_id;

                                                    $query = \App\Domain\Property\Models\Establishment::query();
                                                    if ($typeId) {
                                                        $query->where('establishment_type_id', $typeId);
                                                    }
                                                    if ($cityId) {
                                                        $query->where(function ($q) use ($cityId) {
                                                            $q->where('city_id', $cityId)
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
                                                ])
                                                ->createOptionUsing(function (array $data, Get $get, RelationManager $livewire) {
                                                    $typeId = $get('establishment_type_id');
                                                    $cityId = $livewire->getOwnerRecord()->city_id;

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
