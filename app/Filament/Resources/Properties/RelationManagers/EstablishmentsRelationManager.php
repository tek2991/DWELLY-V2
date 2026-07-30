<?php

namespace App\Filament\Resources\Properties\RelationManagers;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Utilities\Get;
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
                        $propertyCityId = $property->city_id;
                        $cities = \App\Domain\Geographic\Models\City::pluck('name', 'id');

                        return [
                            Select::make('filter_city_id')
                                ->label('Filter Establishments by City')
                                ->options($cities)
                                ->default($propertyCityId)
                                ->placeholder('All Cities')
                                ->live()
                                ->dehydrated(false),

                            Group::make()
                                ->schema(function (Get $get) use ($propertyCityId) {
                                    $selectedCityId = $get('filter_city_id');

                                    $query = \App\Domain\Property\Models\Establishment::with(['establishmentType', 'city']);
                                    if ($selectedCityId) {
                                        $query->where('city_id', $selectedCityId);
                                    }
                                    $establishments = $query->orderBy('name')->get();

                                    if ($establishments->isEmpty()) {
                                        return [
                                            Placeholder::make('no_est_notice')
                                                ->hiddenLabel()
                                                ->content(new \Illuminate\Support\HtmlString('<div class="py-4 text-center text-sm text-gray-500">No establishments found for the selected city.</div>')),
                                        ];
                                    }

                                    $gridSchema = [
                                        Placeholder::make('h_name')->hiddenLabel()->content(new \Illuminate\Support\HtmlString('<strong>Establishment Name</strong>')),
                                        Placeholder::make('h_type')->hiddenLabel()->content(new \Illuminate\Support\HtmlString('<strong>Type / City</strong>')),
                                        Placeholder::make('h_dist')->hiddenLabel()->content(new \Illuminate\Support\HtmlString('<strong>Distance (KM) *</strong>')),
                                        Placeholder::make('h_time')->hiddenLabel()->content(new \Illuminate\Support\HtmlString('<strong>Time (Mins)</strong>')),
                                    ];

                                    foreach ($establishments as $est) {
                                        $gridSchema[] = Placeholder::make("label_{$est->id}")
                                            ->hiddenLabel()
                                            ->content($est->name);
                                            
                                        $gridSchema[] = Placeholder::make("type_{$est->id}")
                                            ->hiddenLabel()
                                            ->content(($est->establishmentType?->name ?? '-') . ($est->city ? " ({$est->city->name})" : ''));
                                            
                                        $gridSchema[] = TextInput::make("est_{$est->id}_dist")
                                            ->hiddenLabel()
                                            ->numeric()
                                            ->placeholder("0.0");
                                            
                                        $gridSchema[] = TextInput::make("est_{$est->id}_time")
                                            ->hiddenLabel()
                                            ->numeric()
                                            ->placeholder("0");
                                    }

                                    return [
                                        Grid::make(4)->schema($gridSchema),
                                    ];
                                }),
                        ];
                    })
                    ->action(function (array $data, RelationManager $livewire) {
                        $property = $livewire->getOwnerRecord();
                        $establishments = \App\Domain\Property\Models\Establishment::all();
                        
                        $addedCount = 0;
                        $skippedCount = 0;
                        foreach ($establishments as $est) {
                            $dist = $data["est_{$est->id}_dist"] ?? null;
                            $time = $data["est_{$est->id}_time"] ?? null;
                            
                            // If user entered distance for this establishment
                            if ($dist !== null && $dist !== '') {
                                $existing = $property->establishments()->where('establishment_id', $est->id)->first();
                                if (!$existing) {
                                    $property->establishments()->create([
                                        'establishment_id' => $est->id,
                                        'distance_km' => $dist,
                                        'travel_time_minutes' => $time,
                                    ]);
                                    $addedCount++;
                                } else {
                                    $existing->update([
                                        'distance_km' => $dist,
                                        'travel_time_minutes' => $time ?? $existing->travel_time_minutes,
                                    ]);
                                }
                            } elseif ($time !== null && $time !== '') {
                                $skippedCount++;
                            }
                        }
                        if ($addedCount > 0) {
                            \Filament\Notifications\Notification::make()->title("{$addedCount} Establishments mapped successfully")->success()->send();
                        } elseif ($skippedCount > 0) {
                            \Filament\Notifications\Notification::make()->title("Establishments require Distance (KM) to be saved")->warning()->send();
                        } else {
                            \Filament\Notifications\Notification::make()->title("Establishment mappings updated")->success()->send();
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
