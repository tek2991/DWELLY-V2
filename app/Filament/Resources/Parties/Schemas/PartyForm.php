<?php

namespace App\Filament\Resources\Parties\Schemas;

use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Section;

class PartyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Party Information')
                    ->schema([
                        Select::make('party_type')
                            ->options([
                                'individual' => 'Individual',
                                'organization' => 'Organization',
                            ])
                            ->required()
                            ->reactive(),
                        TextInput::make('display_name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('phone')
                            ->tel()
                            ->maxLength(255),
                        TextInput::make('email')
                            ->email()
                            ->maxLength(255),
                        Select::make('region_id')
                            ->relationship('region', 'name')
                            ->searchable()
                            ->preload(),
                    ])->columns(2),

                Section::make('Individual Details')
                    ->schema([
                        TextInput::make('individual_data.first_name')->required(),
                        TextInput::make('individual_data.last_name'),
                        TextInput::make('individual_data.aadhaar_number'),
                        TextInput::make('individual_data.pan_number'),
                    ])
                    ->columns(2)
                    ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('party_type') === 'individual'),

                Section::make('Organization Details')
                    ->schema([
                        TextInput::make('organization_data.legal_name')->required(),
                        TextInput::make('organization_data.gstin'),
                        TextInput::make('organization_data.pan'),
                        TextInput::make('organization_data.contact_person_name'),
                    ])
                    ->columns(2)
                    ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('party_type') === 'organization'),
                
                Section::make('Profile Options')
                    ->schema([
                        Select::make('profile_type')
                            ->options([
                                'owner' => 'Owner',
                                'tenant' => 'Tenant',
                                'vendor' => 'Vendor',
                            ])
                            ->required()
                            ->helperText('Which profile should be created initially for this party?'),
                    ])
            ]);
    }
}
