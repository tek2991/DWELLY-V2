<?php

namespace App\Filament\Resources\Properties\Schemas;

use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Section;

class PropertyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Basic Details')
                    ->schema([
                        TextInput::make('building_name')
                            ->required()
                            ->maxLength(255),
                        Select::make('property_type_id')
                            ->options(fn() => \Illuminate\Support\Facades\DB::table('property_types')->pluck('name', 'id'))
                            ->searchable(),
                        Select::make('bhk_type_id')
                            ->options(fn() => \Illuminate\Support\Facades\DB::table('bhk_types')->pluck('name', 'id'))
                            ->searchable(),
                    ])->columns(2),

                Section::make('Geographic Location')
                    ->schema([
                        Select::make('state_id')
                            ->label('State')
                            ->options(fn() => \Tek2991\Accounting\Models\State::pluck('name', 'id'))
                            ->live()
                            ->afterStateHydrated(function ($component, $state, ?\Illuminate\Database\Eloquent\Model $record) {
                                if ($record && $record->locality_id) {
                                    $locality = \App\Domain\Geographic\Models\Locality::with('city.district.state')->find($record->locality_id);
                                    if ($locality && $locality->city && $locality->city->district) {
                                        $component->state($locality->city->district->state_id);
                                    }
                                }
                            })
                            ->afterStateUpdated(function ($set) {
                                $set('district_id', null);
                                $set('city_id', null);
                                $set('locality_id', null);
                            })
                            ->dehydrated(false)
                            ->searchable(),
                        
                        Select::make('district_id')
                            ->label('District')
                            ->options(function ($get) {
                                $stateId = $get('state_id');
                                if (! $stateId) {
                                    return [];
                                }
                                return \App\Domain\Geographic\Models\District::where('state_id', $stateId)->pluck('name', 'id');
                            })
                            ->live()
                            ->afterStateHydrated(function ($component, $state, ?\Illuminate\Database\Eloquent\Model $record) {
                                if ($record && $record->locality_id) {
                                    $locality = \App\Domain\Geographic\Models\Locality::with('city.district')->find($record->locality_id);
                                    if ($locality && $locality->city) {
                                        $component->state($locality->city->district_id);
                                    }
                                }
                            })
                            ->afterStateUpdated(function ($set) {
                                $set('city_id', null);
                                $set('locality_id', null);
                            })
                            ->dehydrated(false)
                            ->searchable(),

                        Select::make('city_id')
                            ->label('City')
                            ->options(function ($get) {
                                $districtId = $get('district_id');
                                if (! $districtId) {
                                    return [];
                                }
                                return \App\Domain\Geographic\Models\City::where('district_id', $districtId)->pluck('name', 'id');
                            })
                            ->live()
                            ->afterStateHydrated(function ($component, $state, ?\Illuminate\Database\Eloquent\Model $record) {
                                if ($record && $record->locality_id) {
                                    $locality = \App\Domain\Geographic\Models\Locality::find($record->locality_id);
                                    if ($locality) {
                                        $component->state($locality->city_id);
                                    }
                                }
                            })
                            ->afterStateUpdated(function ($set) {
                                $set('locality_id', null);
                            })
                            ->dehydrated(false)
                            ->searchable(),

                        Select::make('locality_id')
                            ->label('Locality')
                            ->options(function ($get) {
                                $cityId = $get('city_id');
                                if (! $cityId) {
                                    return [];
                                }
                                return \App\Domain\Geographic\Models\Locality::where('city_id', $cityId)->pluck('name', 'id');
                            })
                            ->required()
                            ->searchable(),
                    ])->columns(2),

                Section::make('Address')
                    ->schema([
                        TextInput::make('address_line_1')->maxLength(255),
                        TextInput::make('address_line_2')->maxLength(255),
                        TextInput::make('landmark')->maxLength(255),
                    ])->columns(2),
            ]);
    }
}
