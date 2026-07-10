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

                \Filament\Schemas\Components\Section::make('Owner Details')
                    ->description('Details of the primary owner linked via the signed Management Agreement (MOU).')
                    ->schema([
                        \Filament\Forms\Components\Placeholder::make('owner_name')
                            ->label('Name')
                            ->content(fn (?\Illuminate\Database\Eloquent\Model $record) => $record?->mou?->party?->display_name ?? 'N/A'),
                        \Filament\Forms\Components\Placeholder::make('owner_email')
                            ->label('Email')
                            ->content(fn (?\Illuminate\Database\Eloquent\Model $record) => $record?->mou?->party?->email ?? 'N/A'),
                        \Filament\Forms\Components\Placeholder::make('owner_phone')
                            ->label('Phone')
                            ->content(fn (?\Illuminate\Database\Eloquent\Model $record) => $record?->mou?->party?->phone ?? 'N/A'),
                        \Filament\Forms\Components\Placeholder::make('owner_profile')
                            ->label('Action')
                            ->content(function (?\Illuminate\Database\Eloquent\Model $record) {
                                if ($record && $record->mou && $record->mou->party_id) {
                                    $url = \App\Filament\Resources\Parties\PartyResource::getUrl('edit', ['record' => $record->mou->party_id]);
                                    return new \Illuminate\Support\HtmlString("<a href=\"{$url}\" target=\"_blank\" class=\"text-primary-600 hover:underline\">View Owner Profile &rarr;</a>");
                                }
                                return 'N/A';
                            }),
                    ])->columns(2)
                    ->hidden(fn (?\Illuminate\Database\Eloquent\Model $record) => !($record && $record->mou_id)),

                \Filament\Schemas\Components\Section::make('Current Pricing')
                    ->description('Overview of the currently active pricing version. Manage pricing history in the Pricing Versions tab.')
                    ->schema([
                        \Filament\Forms\Components\Placeholder::make('current_rent')
                            ->label('Rent')
                            ->content(function (?\Illuminate\Database\Eloquent\Model $record) {
                                $pricing = $record?->pricingVersions()->latest('effective_from')->first();
                                return $pricing && $pricing->rent ? '₹ ' . number_format($pricing->rent, 2) : 'N/A';
                            }),
                        \Filament\Forms\Components\Placeholder::make('current_deposit')
                            ->label('Security Deposit')
                            ->content(function (?\Illuminate\Database\Eloquent\Model $record) {
                                $pricing = $record?->pricingVersions()->latest('effective_from')->first();
                                return $pricing && $pricing->security_deposit ? '₹ ' . number_format($pricing->security_deposit, 2) : 'N/A';
                            }),
                        \Filament\Forms\Components\Placeholder::make('current_model')
                            ->label('Pricing Model')
                            ->content(function (?\Illuminate\Database\Eloquent\Model $record) {
                                $pricing = $record?->pricingVersions()->latest('effective_from')->first();
                                return $pricing && $pricing->pricing_model ? $pricing->pricing_model : 'N/A';
                            }),
                    ])->columns(3)
                    ->hidden(fn (?\Illuminate\Database\Eloquent\Model $record) => !$record || $record->pricingVersions()->count() === 0),

                \Filament\Schemas\Components\Section::make('Location & Address')
                    ->schema([
                        TextInput::make('address_line_1')->maxLength(255),
                        TextInput::make('address_line_2')->maxLength(255),
                        TextInput::make('landmark')->maxLength(255),
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
            ]);
    }
}
