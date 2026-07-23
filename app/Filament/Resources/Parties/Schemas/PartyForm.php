<?php

namespace App\Filament\Resources\Parties\Schemas;

use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Hidden;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Actions\Action;
use Illuminate\Support\HtmlString;
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
                        TextInput::make('individual_data.name')->label('Full Name')->required(),
                        TextInput::make('individual_data.aadhaar_number'),
                        TextInput::make('individual_data.pan_number'),
                        TextInput::make('individual_data.voter_id')->label('Voter ID'),
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
                    ->description('Bank account details for remittances and financial payouts.')
                    ->headerActions([
                        Action::make('editBankDetails')
                            ->label('Edit Bank Details')
                            ->icon('heroicon-o-lock-closed')
                            ->color('warning')
                            ->modalHeading('Update Bank Details Warning')
                            ->modalDescription('Bank details should ideally be updated via the official MOU update workflow on the Property\'s Financial Terms & MOU page.')
                            ->modalContent(function ($record) {
                                if (!$record) {
                                    return new HtmlString('<p class="text-sm text-gray-600 dark:text-gray-400">Save the party profile before linking to properties.</p>');
                                }
                                
                                $properties = \App\Domain\Property\Models\Property::where('owner_party_id', $record->id)
                                    ->orWhereHas('mous', fn ($q) => $q->where('party_id', $record->id))
                                    ->distinct()
                                    ->get();

                                if ($properties->isEmpty()) {
                                    return new HtmlString('<p class="text-sm text-gray-600 dark:text-gray-400">No properties are currently linked to this party.</p>');
                                }

                                $links = $properties->map(function ($property) {
                                    $url = \App\Filament\Resources\Properties\PropertyResource::getUrl('financials', ['record' => $property]);
                                    $code = e($property->code ?? $property->building_name ?? 'Property #' . $property->id);
                                    return "<li class=\"py-1\"><a href=\"{$url}\" target=\"_blank\" class=\"text-primary-600 hover:underline font-semibold inline-flex items-center gap-1\"><svg class=\"w-4 h-4 inline\" fill=\"none\" stroke=\"currentColor\" viewBox=\"0 0 24 24\"><path stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-width=\"2\" d=\"M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14\"/></svg> {$code} &mdash; Financial Terms & MOU</a></li>";
                                })->implode('');

                                return new HtmlString("
                                    <div class=\"space-y-3 text-sm text-gray-700 dark:text-gray-300\">
                                        <p>To ensure legally binding financial terms, please initiate a <strong>Bank Details Update MOU</strong> from the financial page of the relevant property:</p>
                                        <ul class=\"list-disc pl-5 font-medium\">
                                            {$links}
                                        </ul>
                                        <p class=\"pt-2 text-xs text-gray-500\">If you need to make an emergency manual correction without an MOU update, click <strong>Unlock Manual Editing</strong> below.</p>
                                    </div>
                                ");
                            })
                            ->modalSubmitActionLabel('Unlock Manual Editing')
                            ->action(function (Set $set) {
                                $set('is_bank_editing_unlocked', true);
                                \Filament\Notifications\Notification::make()
                                    ->title('Manual Editing Unlocked')
                                    ->body('You can now edit bank details directly. Remember to save changes.')
                                    ->warning()
                                    ->send();
                            })
                            ->visible(fn (Get $get) => !$get('is_bank_editing_unlocked')),
                    ])
                    ->disabled(fn (Get $get) => !$get('is_bank_editing_unlocked'))
                    ->columns(2)
                    ->schema([
                        Hidden::make('is_bank_editing_unlocked')
                            ->default(false)
                            ->dehydrated(false),
                        TextInput::make('bank_details.beneficiary_name')
                            ->label('Beneficiary Name')
                            ->maxLength(255),
                        TextInput::make('bank_details.bank_name')
                            ->label('Name of the Bank')
                            ->maxLength(255),
                        Textarea::make('bank_details.bank_address')
                            ->label('Address of the Bank')
                            ->columnSpanFull()
                            ->rows(2),
                        TextInput::make('bank_details.account_number')
                            ->label('Account Number')
                            ->maxLength(255),
                        TextInput::make('bank_details.ifsc_code')
                            ->label('IFSC Code')
                            ->maxLength(255),
                    ]),

                Section::make('Addresses')
                    ->columns(2)
                    ->schema([
                        Textarea::make('address_details.primary_address')
                            ->label(fn (Get $get) => $get('party_type') === 'organization' ? 'Registered Office Address' : 'Residential Address')
                            ->helperText('Note: This address is used for MOUs and legal agreements. It is NOT synced with the Accounting module.')
                            ->rows(3)
                            ->columnSpanFull(),
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
