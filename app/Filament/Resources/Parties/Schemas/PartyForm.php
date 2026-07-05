<?php

namespace App\Filament\Resources\Parties\Schemas;

use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\CheckboxList;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Tek2991\Accounting\Enums\GstRegistrationType;
use Tek2991\Accounting\Models\State;
use Tek2991\Accounting\Utilities\GstinValidator;

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
                        Select::make('state_id')
                            ->label('State (Accounting)')
                            ->options(fn() => \Tek2991\Accounting\Models\State::pluck('name', 'id'))
                            ->searchable(),

                        Toggle::make('is_tax_registered')
                            ->label('Is Tax Registered')
                            ->live()
                            ->default(false),
                        Select::make('gst_registration_type')
                            ->label('GST Registration Type')
                            ->options(GstRegistrationType::class)
                            ->visible(fn (Get $get) => $get('is_tax_registered')),
                    ])->columns(2),

                Section::make('Individual Details')
                    ->schema([
                        TextInput::make('individual_data.first_name')->required(),
                        TextInput::make('individual_data.last_name'),
                        TextInput::make('individual_data.aadhaar_number'),
                        TextInput::make('individual_data.pan_number'),
                        TextInput::make('individual_data.gstin')
                            ->label('GSTIN')
                            ->key('individual_gstin')
                            ->maxLength(15)
                            ->live(debounce: 500)
                            ->afterStateUpdated(function (?string $state, $set, $get) {
                                if (empty($state)) return;
                                
                                $validator = new GstinValidator();
                                $stateCode = $validator->extractStateCode($state);
                                
                                if ($stateCode && empty($get('../../state_id'))) {
                                    $matchedState = \Tek2991\Accounting\Models\State::where('gst_state_code', $stateCode)->first();
                                    if ($matchedState) {
                                        $set('../../state_id', $matchedState->id);
                                    }
                                }
                            })
                            ->rules([
                                fn ($get): \Closure => function (string $attribute, $value, \Closure $fail) use ($get) {
                                    $validator = new GstinValidator();
                                    $selectedState = \Tek2991\Accounting\Models\State::find($get('../../state_id'));
                                    $result = $validator->validate($value, $selectedState);
                                    
                                    if (!$result->isValidFormat) {
                                        $fail('The GSTIN format is invalid.');
                                    } elseif (!$result->isValidStateCode) {
                                        $fail("The GSTIN prefix does not match the selected state's code.");
                                    }
                                },
                            ])
                            ->visible(fn ($get) => $get('is_tax_registered')),
                    ])
                    ->columns(2)
                    ->visible(fn (Get $get) => $get('party_type') === 'individual'),

                Section::make('Organization Details')
                    ->schema([
                        TextInput::make('organization_data.legal_name')->required(),
                        TextInput::make('organization_data.gstin')
                            ->label('GSTIN')
                            ->key('organization_gstin')
                            ->maxLength(15)
                            ->live(debounce: 500)
                            ->afterStateUpdated(function (?string $state, $set, $get) {
                                if (empty($state)) return;
                                
                                $validator = new GstinValidator();
                                $stateCode = $validator->extractStateCode($state);
                                
                                if ($stateCode && empty($get('../../state_id'))) {
                                    $matchedState = \Tek2991\Accounting\Models\State::where('gst_state_code', $stateCode)->first();
                                    if ($matchedState) {
                                        $set('../../state_id', $matchedState->id);
                                    }
                                }
                            })
                            ->rules([
                                fn ($get): \Closure => function (string $attribute, $value, \Closure $fail) use ($get) {
                                    $validator = new GstinValidator();
                                    $selectedState = \Tek2991\Accounting\Models\State::find($get('../../state_id'));
                                    $result = $validator->validate($value, $selectedState);
                                    
                                    if (!$result->isValidFormat) {
                                        $fail('The GSTIN format is invalid.');
                                    } elseif (!$result->isValidStateCode) {
                                        $fail("The GSTIN prefix does not match the selected state's code.");
                                    }
                                },
                            ])
                            ->visible(fn ($get) => $get('is_tax_registered')),
                        TextInput::make('organization_data.pan'),
                        TextInput::make('organization_data.contact_person_name'),
                    ])
                    ->columns(2)
                    ->visible(fn (Get $get) => $get('party_type') === 'organization'),

                Section::make('Bank Details')
                    ->columns(2)
                    ->schema([
                        TextInput::make('bank_details.bank_beneficiary_name')
                            ->label('Beneficiary Name')
                            ->maxLength(255),
                        TextInput::make('bank_details.bank_name')
                            ->label('Name of the Bank')
                            ->maxLength(255),
                        Textarea::make('bank_details.bank_address')
                            ->label('Address of the Bank')
                            ->columnSpanFull()
                            ->rows(2),
                        TextInput::make('bank_details.bank_account_no')
                            ->label('Bank Account No.')
                            ->maxLength(255),
                        TextInput::make('bank_details.bank_ifsc_code')
                            ->label('IFSC Code')
                            ->maxLength(255),
                    ]),

                Section::make('Addresses')
                    ->columns(2)
                    ->schema([
                        Textarea::make('address_details.billing_address')
                            ->label('Billing Address')
                            ->rows(3),
                        Textarea::make('address_details.shipping_address')
                            ->label('Shipping Address')
                            ->rows(3),
                    ]),

                Section::make('Profile Options')
                    ->schema([
                        CheckboxList::make('roles')
                            ->options([
                                'owner' => 'Owner',
                                'tenant' => 'Tenant',
                                'vendor' => 'Vendor',
                            ])
                            ->required()
                            ->helperText('Select one or more profiles for this party.'),
                    ])
            ]);
    }
}
