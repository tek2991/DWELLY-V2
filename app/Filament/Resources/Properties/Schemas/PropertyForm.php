<?php

namespace App\Filament\Resources\Properties\Schemas;

use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Section;

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
                        Select::make('region_id')
                            ->relationship('region', 'name')
                            ->searchable()
                            ->required(),
                        Select::make('property_type_id')
                            ->options(fn() => \Illuminate\Support\Facades\DB::table('property_types')->pluck('name', 'id'))
                            ->searchable(),
                        Select::make('bhk_type_id')
                            ->options(fn() => \Illuminate\Support\Facades\DB::table('bhk_types')->pluck('name', 'id'))
                            ->searchable(),
                    ])->columns(2),

                Section::make('Address')
                    ->schema([
                        TextInput::make('address_line_1')->maxLength(255),
                        TextInput::make('address_line_2')->maxLength(255),
                        TextInput::make('locality')->maxLength(255),
                        TextInput::make('city')->maxLength(255),
                        TextInput::make('pincode')->maxLength(20),
                    ])->columns(2),
            ]);
    }
}
