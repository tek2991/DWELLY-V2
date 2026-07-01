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
use Tek2991\Accounting\Services\CompanyContext;
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
                            ->required(),
                            
                        Forms\Components\TextInput::make('name')
                            ->label('Name / Company')
                            ->required()
                            ->maxLength(255),
                            
                        Forms\Components\TextInput::make('email')
                            ->label('Email Address')
                            ->email()
                            ->maxLength(255),
                            
                        Forms\Components\TextInput::make('phone')
                            ->label('Phone Number')
                            ->maxLength(255),
                            
                        Forms\Components\TextInput::make('tax_id')
                            ->label(fn () => app(CompanyContext::class)->isIndiaGst() ? 'Tax ID / PAN' : 'Tax ID')
                            ->maxLength(255),
                            
                        Forms\Components\Toggle::make('is_tax_registered')
                            ->label('Is Tax Registered')
                            ->visible(fn () => app(CompanyContext::class)->isIndiaGst())
                            ->live()
                            ->default(false),
                            
                        Forms\Components\Select::make('gst_registration_type')
                            ->label('GST Registration Type')
                            ->options(GstRegistrationType::class)
                            ->visible(fn (Get $get) => app(CompanyContext::class)->isIndiaGst() && $get('is_tax_registered')),
                            
                        Forms\Components\TextInput::make('gstin')
                            ->label('GSTIN')
                            ->maxLength(15)
                            ->visible(fn (Get $get) => app(CompanyContext::class)->isIndiaGst() && $get('is_tax_registered'))
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
                            ->searchable()
                            ->visible(fn () => app(CompanyContext::class)->isIndiaGst()),
                    ]),
                    
                Section::make('Addresses')
                    ->columns(2)
                    ->components([
                        Forms\Components\Textarea::make('billing_address')
                            ->label('Billing Address')
                            ->rows(3),
                            
                        Forms\Components\Textarea::make('shipping_address')
                            ->label('Shipping Address')
                            ->rows(3),
                    ]),
            ]);
    }
}
