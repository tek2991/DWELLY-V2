<?php

namespace Tek2991\Accounting\Filament\Resources\Contacts\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Tek2991\Accounting\Enums\ContactType;
use Tek2991\Accounting\Enums\GstRegistrationType;
use Tek2991\Accounting\Models\State;
use Tek2991\Accounting\Models\Organization;
use Tek2991\Accounting\Enums\TaxRegimeType;
use Tek2991\Accounting\Utilities\GstinValidator;

class ContactForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Basic Information')
                    ->columns(2)
                    ->components([
                        Forms\Components\Select::make('type')
                            ->label('Contact Type')
                            ->options(ContactType::class)
                            ->default(ContactType::Customer)
                            ->disabled(fn () => !config('accounting.contacts.allow_update', true))
                            ->required(),
                            
                        Forms\Components\TextInput::make('name')
                            ->label('Name / Company')
                            ->required()
                            ->disabled(fn () => !config('accounting.contacts.allow_update', true))
                            ->maxLength(255),
                            
                        Forms\Components\TextInput::make('email')
                            ->label('Email Address')
                            ->email()
                            ->disabled(fn () => !config('accounting.contacts.allow_update', true))
                            ->maxLength(255),
                            
                        Forms\Components\TextInput::make('phone')
                            ->label('Phone Number')
                            ->disabled(fn () => !config('accounting.contacts.allow_update', true))
                            ->maxLength(255),
                            
                        Forms\Components\TextInput::make('tax_id')
                            ->label(fn () => Organization::current()->tax_regime === TaxRegimeType::IndiaGst ? 'Tax ID / PAN' : 'Tax ID')
                            ->disabled(fn () => !config('accounting.contacts.allow_update', true))
                            ->maxLength(255),
                            
                        Forms\Components\Toggle::make('is_tax_registered')
                            ->label('Is Tax Registered')
                            ->visible(fn () => Organization::current()->tax_regime === TaxRegimeType::IndiaGst)
                            ->disabled(fn () => !config('accounting.contacts.allow_update', true))
                            ->live()
                            ->default(false),
                            
                        Forms\Components\Select::make('gst_registration_type')
                            ->label('GST Registration Type')
                            ->options(GstRegistrationType::class)
                            ->disabled(fn () => !config('accounting.contacts.allow_update', true))
                            ->visible(fn (Get $get) => Organization::current()->tax_regime === TaxRegimeType::IndiaGst && $get('is_tax_registered')),
                            
                        Forms\Components\TextInput::make('gstin')
                            ->label('GSTIN')
                            ->maxLength(15)
                            ->disabled(fn () => !config('accounting.contacts.allow_update', true))
                            ->visible(fn (Get $get) => Organization::current()->tax_regime === TaxRegimeType::IndiaGst && $get('is_tax_registered'))
                            ->live(debounce: 500)
                            ->afterStateUpdated(function (?string $state, Set $set, Get $get) {
                                if (empty($state)) return;
                                
                                $validator = new GstinValidator();
                                $stateCode = $validator->extractStateCode($state);
                                
                                if ($stateCode && empty($get('state_id'))) {
                                    $matchedState = State::where('gst_state_code', $stateCode)->first();
                                    if ($matchedState) {
                                        $set('state_id', $matchedState->id);
                                    }
                                }
                            })
                            ->rules([
                                fn (Get $get): \Closure => function (string $attribute, $value, \Closure $fail) use ($get) {
                                    $validator = new GstinValidator();
                                    $selectedState = State::find($get('state_id'));
                                    $result = $validator->validate($value, $selectedState);
                                    
                                    if (!$result->isValidFormat) {
                                        $fail('The GSTIN format is invalid.');
                                    } elseif (!$result->isValidStateCode) {
                                        $fail("The GSTIN prefix does not match the selected state's code.");
                                    }
                                },
                            ]),
                            
                        Forms\Components\Select::make('state_id')
                            ->label('State')
                            ->options(State::all()->pluck('name', 'id'))
                            ->disabled(fn () => !config('accounting.contacts.allow_update', true))
                            ->searchable()
                            ->visible(fn () => Organization::current()->tax_regime === TaxRegimeType::IndiaGst),
                    ]),
                    
                Section::make('Bank Details')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('bank_beneficiary_name')
                            ->label('Beneficiary Name')
                            ->disabled(fn () => !config('accounting.contacts.allow_update', true))
                            ->maxLength(255),
                            
                        Forms\Components\TextInput::make('bank_name')
                            ->label('Name of the Bank')
                            ->disabled(fn () => !config('accounting.contacts.allow_update', true))
                            ->maxLength(255),
                            
                        Forms\Components\Textarea::make('bank_address')
                            ->label('Address of the Bank')
                            ->columnSpanFull()
                            ->disabled(fn () => !config('accounting.contacts.allow_update', true))
                            ->rows(2),
                            
                        Forms\Components\TextInput::make('bank_account_no')
                            ->label('Bank Account No.')
                            ->disabled(fn () => !config('accounting.contacts.allow_update', true))
                            ->maxLength(255),
                            
                        Forms\Components\TextInput::make('bank_ifsc_code')
                            ->label('IFSC Code')
                            ->disabled(fn () => !config('accounting.contacts.allow_update', true))
                            ->maxLength(255),
                    ]),

                Section::make('Addresses')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Textarea::make('billing_address')
                            ->label('Billing Address')
                            ->disabled(fn () => !config('accounting.contacts.allow_update', true))
                            ->rows(3),
                            
                        Forms\Components\Textarea::make('shipping_address')
                            ->label('Shipping Address')
                            ->disabled(fn () => !config('accounting.contacts.allow_update', true))
                            ->rows(3),
                    ]),
            ]);
    }
}
