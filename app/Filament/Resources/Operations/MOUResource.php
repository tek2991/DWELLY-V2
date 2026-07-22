<?php

namespace App\Filament\Resources\Operations;

use App\Domain\Mou\Models\Mou;
use App\Domain\Opportunity\Enums\MouStatus;
use App\Filament\Resources\Operations\MOUResource\Pages;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Domain\Mou\Services\MouWorkflowService;

class MOUResource extends Resource
{
    protected static ?string $model = Mou::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-document-check';
    protected static \UnitEnum|string|null $navigationGroup = 'Sales & CRM';
    protected static ?int $navigationSort = 1;

    public static function canViewAny(): bool
    {
        return auth()->user()->hasAnyRole(['Business Owner', 'Operations Manager', 'Legal']);
    }

    public static function canEdit(?\Illuminate\Database\Eloquent\Model $record = null): bool
    {
        if (!$record) return true;

        return !in_array($record->status, [
            \App\Domain\Opportunity\Enums\MouStatus::VERIFIED,
            \App\Domain\Opportunity\Enums\MouStatus::CONVERTED
        ]);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                \Filament\Schemas\Components\Group::make()->schema([
                    \Filament\Schemas\Components\Section::make('MOU Summary')
                        ->schema([
                            Forms\Components\TextInput::make('number')
                                ->disabled()
                                ->dehydrated(false),
                            Forms\Components\Select::make('opportunity_id')
                                ->relationship('opportunity', 'title')
                                ->searchable()
                                ->preload()
                                ->required()
                                ->disabled(fn (string $operation): bool => $operation !== 'create')
                                ->hintAction(
                                    \Filament\Actions\Action::make('viewOpportunity')
                                        ->icon('heroicon-m-eye')
                                        ->tooltip('View Opportunity Details')
                                        ->modalHeading(fn (?Mou $record) => new \Illuminate\Support\HtmlString(
                                            '<div class="flex items-center gap-3">
                                                <span>Opportunity Details</span>
                                                ' . ($record?->opportunity ? '<a href="' . \App\Filament\Resources\Operations\OpportunityResource::getUrl('view', ['record' => $record->opportunity]) . '" class="text-sm font-medium text-primary-600 hover:underline dark:text-primary-400">Open Full Page &rarr;</a>' : '') . '
                                            </div>'
                                        ))
                                        ->modalSubmitAction(false)
                                        ->modalCancelActionLabel('Close')
                                        ->infolist([
                                            \Filament\Schemas\Components\Section::make('General Information')
                                                ->schema([
                                                    \Filament\Infolists\Components\TextEntry::make('opportunity.title')->label('Title'),
                                                    \Filament\Infolists\Components\TextEntry::make('opportunity.status')->label('Status')->badge(),
                                                    \Filament\Infolists\Components\TextEntry::make('opportunity.opportunitySource.name')->label('Source'),
                                                    \Filament\Infolists\Components\TextEntry::make('opportunity.assignedUser.name')->label('Assigned To'),
                                                ])->columns(2),
                                                
                                            \Filament\Schemas\Components\Section::make('Owner Information')
                                                ->schema([
                                                    \Filament\Infolists\Components\TextEntry::make('opportunity.owner_name')->label('Owner Name'),
                                                    \Filament\Infolists\Components\TextEntry::make('opportunity.owner_phone')->label('Owner Phone'),
                                                    \Filament\Infolists\Components\TextEntry::make('opportunity.owner_email')->label('Owner Email'),
                                                    \Filament\Infolists\Components\TextEntry::make('opportunity.address')->label('Address')->columnSpanFull(),
                                                ])->columns(2),
                                                
                                            \Filament\Schemas\Components\Section::make('Property & Commercial Estimates')
                                                ->schema([
                                                    \Filament\Infolists\Components\TextEntry::make('opportunity.estimatedPropertyType.name')->label('Property Type'),
                                                    \Filament\Infolists\Components\TextEntry::make('opportunity.estimated_bhk')->label('BHK'),
                                                    \Filament\Infolists\Components\TextEntry::make('opportunity.estimated_size')->label('Size (Sq.Ft)'),
                                                    \Filament\Infolists\Components\IconEntry::make('opportunity.estimated_is_furnished')->label('Furnished')->boolean(),
                                                    \Filament\Infolists\Components\TextEntry::make('opportunity.expected_rent')->label('Expected Rent')->money('INR'),
                                                    \Filament\Infolists\Components\TextEntry::make('opportunity.expectedFinancialModel.name')->label('Financial Model'),
                                                    \Filament\Infolists\Components\TextEntry::make('opportunity.expected_onboarding_date')->label('Expected Onboarding')->date(),
                                                ])->columns(3),
                                                
                                            \Filament\Schemas\Components\Section::make('Internal Summary')
                                                ->schema([
                                                    \Filament\Infolists\Components\TextEntry::make('opportunity.internal_summary')
                                                        ->label('')
                                                        ->columnSpanFull()
                                                        ->default('No summary provided.'),
                                                ]),
                                        ])
                                        ->visible(fn (?Mou $record) => $record?->opportunity !== null)
                                ),
                            Forms\Components\Placeholder::make('party')
                                ->label('Associated Party')
                                ->content(function (?Mou $record): ?\Illuminate\Support\HtmlString {
                                    if ($record?->party) {
                                        return new \Illuminate\Support\HtmlString("<span class=\"font-medium text-gray-900 dark:text-white\">{$record->party->display_name}</span>");
                                    }
                                    return new \Illuminate\Support\HtmlString("<span class=\"text-gray-500\">Unresolved</span>");
                                })
                                ->hintAction(
                                    \Filament\Actions\Action::make('viewParty')
                                        ->icon('heroicon-m-eye')
                                        ->tooltip('View Party Details')
                                        ->modalHeading(fn (?Mou $record) => new \Illuminate\Support\HtmlString(
                                            '<div class="flex items-center gap-3">
                                                <span>Party Details</span>
                                                ' . ($record?->party ? '<a href="' . \App\Filament\Resources\Parties\PartyResource::getUrl('edit', ['record' => $record->party]) . '" class="text-sm font-medium text-primary-600 hover:underline dark:text-primary-400">Open Full Page &rarr;</a>' : '') . '
                                            </div>'
                                        ))
                                        ->modalSubmitAction(false)
                                        ->modalCancelActionLabel('Close')
                                        ->infolist([
                                            \Filament\Schemas\Components\Section::make('General Information')
                                                ->schema([
                                                    \Filament\Infolists\Components\TextEntry::make('party.party_type')
                                                        ->label('Type')
                                                        ->formatStateUsing(fn ($state) => ucfirst($state))
                                                        ->badge(),
                                                    \Filament\Infolists\Components\TextEntry::make('party.display_name')->label('Name'),
                                                    \Filament\Infolists\Components\TextEntry::make('party.phone')->label('Phone')->default('-'),
                                                    \Filament\Infolists\Components\TextEntry::make('party.email')->label('Email')->default('-'),
                                                ])->columns(2),
                                            
                                            \Filament\Schemas\Components\Section::make('Individual Details')
                                                ->schema([
                                                    \Filament\Infolists\Components\TextEntry::make('party.individual.pan_number')->label('PAN Number')->default('-'),
                                                    \Filament\Infolists\Components\TextEntry::make('party.individual.aadhaar_number')->label('Aadhar Number')->default('-'),
                                                    \Filament\Infolists\Components\TextEntry::make('party.individual.address_line_1')->label('Address')->columnSpanFull()->default('-'),
                                                ])->columns(2)
                                                ->visible(fn (?Mou $record) => $record?->party?->party_type === 'individual'),
                                                
                                            \Filament\Schemas\Components\Section::make('Organization Details')
                                                ->schema([
                                                    \Filament\Infolists\Components\TextEntry::make('party.organization.pan')->label('PAN Number')->default('-'),
                                                    \Filament\Infolists\Components\TextEntry::make('party.organization.gstin')->label('GSTIN')->default('-'),
                                                    \Filament\Infolists\Components\TextEntry::make('party.organization.contact_person_name')->label('Contact Person')->default('-'),
                                                    \Filament\Infolists\Components\TextEntry::make('party.organization.contact_person_phone')->label('Contact Phone')->default('-'),
                                                    \Filament\Infolists\Components\TextEntry::make('party.organization.registered_address')->label('Address')->columnSpanFull()->default('-'),
                                                ])->columns(2)
                                                ->visible(fn (?Mou $record) => $record?->party?->party_type === 'organization'),
                                        ])
                                        ->visible(fn (?Mou $record) => $record?->party !== null)
                                ),
                            Forms\Components\Placeholder::make('property')
                                ->label('Associated Property')
                                ->content(function (?Mou $record): ?\Illuminate\Support\HtmlString {
                                    if ($record?->property) {
                                        $code = $record->property->code ?? 'No_Property_Code_Assigned';
                                        return new \Illuminate\Support\HtmlString("<span class=\"font-medium text-gray-900 dark:text-white\">{$code}</span>");
                                    }
                                    return new \Illuminate\Support\HtmlString("<span class=\"text-gray-500\">No_Property_Code_Assigned</span>");
                                })
                                ->hintAction(
                                    \Filament\Actions\Action::make('viewProperty')
                                        ->icon('heroicon-m-eye')
                                        ->tooltip('View Property Details')
                                        ->modalHeading(fn (?Mou $record) => new \Illuminate\Support\HtmlString(
                                            '<div class="flex items-center gap-3">
                                                <span>Property Details</span>
                                                ' . ($record?->property ? '<a href="' . \App\Filament\Resources\Properties\PropertyResource::getUrl('edit', ['record' => $record->property]) . '" class="text-sm font-medium text-primary-600 hover:underline dark:text-primary-400">Open Full Page &rarr;</a>' : '') . '
                                            </div>'
                                        ))
                                        ->modalSubmitAction(false)
                                        ->modalCancelActionLabel('Close')
                                        ->infolist([
                                            \Filament\Schemas\Components\Section::make('General Information')
                                                ->schema([
                                                    \Filament\Infolists\Components\TextEntry::make('property.code')->label('Code')->default('-'),
                                                    \Filament\Infolists\Components\TextEntry::make('property.status')->label('Status')->formatStateUsing(fn ($state) => ucfirst($state))->badge(),
                                                    \Filament\Infolists\Components\TextEntry::make('property.building_name')->label('Building Name')->default('-'),
                                                ])->columns(3),
                                            \Filament\Schemas\Components\Section::make('Location Details')
                                                ->schema([
                                                    \Filament\Infolists\Components\TextEntry::make('property.address_line_1')->label('Address Line 1')->columnSpanFull()->default('-'),
                                                    \Filament\Infolists\Components\TextEntry::make('property.locality.name')->label('Locality')->default('-'),
                                                    \Filament\Infolists\Components\TextEntry::make('property.city')->label('City')->default('-'),
                                                ])->columns(2),
                                        ])
                                        ->visible(fn (?Mou $record) => $record?->property !== null)
                                ),
                        ])->columns(2),

                    \Filament\Schemas\Components\Section::make('Property & Commercial Details')
                        ->description('These details are mapped to the MOU Document and can be modified here without affecting the original Opportunity.')
                        ->schema([
                            Forms\Components\Textarea::make('legal_terms.address')
                                ->label('Property Address')
                                ->required()
                                ->columnSpanFull(),
                            Forms\Components\TextInput::make('legal_terms.rent_amount')
                                ->label('Rent Amount')
                                ->numeric(),
                            Forms\Components\TextInput::make('legal_terms.fee_percentage')
                                ->label('Fee Percentage')
                                ->numeric()
                                ->suffix('%')
                                ->step(0.01)
                                ->minValue(0)
                                ->maxValue(100),
                            Forms\Components\Select::make('legal_terms.financial_model_id')
                                ->label('Financial Model')
                                ->options(fn () => \App\Domain\Opportunity\Models\FinancialModel::pluck('name', 'id'))
                                ->required(),
                            Forms\Components\Select::make('legal_terms.electricity_provider_id')
                                ->label('Electricity Provider')
                                ->options(function () {
                                    return \App\Domain\Property\Models\UtilityProvider::whereHas('utilityType', function ($query) {
                                        $query->where('slug', 'electricity');
                                    })->pluck('name', 'id');
                                })
                                ->searchable()
                                ->required(),
                            Forms\Components\TextInput::make('legal_terms.electricity_consumer_id')
                                ->label('Connection Number')
                                ->maxLength(255)
                                ->required(),
                        ])->columns(2),

                    \Filament\Schemas\Components\Section::make('Legal Terms')
                        ->schema([
                            Forms\Components\DatePicker::make('start_date')
                                ->label('Start Date')
                                ->required(),
                                
                            \Filament\Forms\Components\SpatieMediaLibraryFileUpload::make('mou_attachments')
                                ->collection('mou_attachments')
                                ->multiple()
                                ->label('Owner KYC & Cancelled Cheque')
                                ->helperText('Upload Aadhar, PAN, Cancelled Cheque, etc.')
                                ->required(),

                            Forms\Components\Toggle::make('is_signatory_different')
                                ->label('Is Signatory Authority different from Property Owner?')
                                ->default(false)
                                ->live(),

                            \Filament\Schemas\Components\Grid::make(2)
                                ->schema([
                                    Forms\Components\TextInput::make('signatory_details.name')
                                        ->label('Signatory Full Name')
                                        ->required(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('is_signatory_different')),
                                    Forms\Components\TextInput::make('signatory_details.relation')
                                        ->label('Relation to Owner (e.g. POA, Son)')
                                        ->required(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('is_signatory_different')),
                                    Forms\Components\TextInput::make('signatory_details.phone')
                                        ->label('Phone Number')
                                        ->tel()
                                        ->required(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('is_signatory_different')),
                                    Forms\Components\TextInput::make('signatory_details.email')
                                        ->label('Email Address')
                                        ->email()
                                        ->required(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('is_signatory_different')),
                                    Forms\Components\TextInput::make('signatory_details.aadhar_number')
                                        ->label('Aadhaar Number')
                                        ->required(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('is_signatory_different')),
                                    Forms\Components\TextInput::make('signatory_details.pan_number')
                                        ->label('PAN Number')
                                        ->required(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('is_signatory_different')),
                                ])
                                ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('is_signatory_different')),

                            \Filament\Forms\Components\SpatieMediaLibraryFileUpload::make('signatory_documents')
                                ->collection('signatory_documents')
                                ->multiple()
                                ->label('Signatory Authorization & KYC')
                                ->helperText('Upload Power of Attorney, Signatory Aadhar, PAN, etc.')
                                ->required(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('is_signatory_different'))
                                ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('is_signatory_different')),
                        ])->columns(1)
                        ->collapsible(),

                    \Filament\Schemas\Components\Section::make('Bank Details')
                        ->schema([
                            Forms\Components\TextInput::make('bank_details.bank_name')
                                ->label('Bank Name')
                                ->required(),
                            Forms\Components\TextInput::make('bank_details.beneficiary_name')
                                ->label('Beneficiary Name')
                                ->required(),
                            Forms\Components\TextInput::make('bank_details.account_number')
                                ->label('Account Number')
                                ->required(),
                            Forms\Components\TextInput::make('bank_details.ifsc_code')
                                ->label('IFSC Code')
                                ->required(),
                            Forms\Components\Textarea::make('bank_details.bank_address')
                                ->label('Address of the Bank')
                                ->required()
                                ->columnSpanFull(),
                        ])->columns(2),
                ])->columnSpan(['lg' => 2]),

                \Filament\Schemas\Components\Group::make()->schema([
                    \Filament\Schemas\Components\Section::make('Status & Documents')
                        ->schema([
                            Forms\Components\Placeholder::make('status')
                                ->content(fn (?Mou $record): string => $record?->status?->getLabel() ?? 'Draft'),
                            Forms\Components\Placeholder::make('versions')
                                ->label('Document History')
                                ->content(fn (?Mou $record): \Illuminate\Contracts\View\View => view('mou.version-history', ['record' => $record])),
                        ]),
                ])->columnSpan(['lg' => 1]),
            ])
            ->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('number')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('opportunity.title')
                    ->label('Opportunity')
                    ->searchable(),
                Tables\Columns\TextColumn::make('party.display_name')
                    ->label('Party')
                    ->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
                Tables\Filters\SelectFilter::make('status')
                    ->options(MouStatus::class),
            ])
            ->actions([
                \Filament\Actions\ViewAction::make(),
                \Filament\Actions\EditAction::make()
                    ->visible(fn ($record) => static::canEdit($record)),
                \Filament\Actions\ActionGroup::make([
                    \Filament\Actions\Action::make('resolveParty')
                        ->label('Resolve Party')
                        ->icon('heroicon-o-users')
                        ->color('primary')
                        ->visible(fn (Mou $record) => !$record->party_id && $record->status === MouStatus::DRAFT)
                        ->form([
                            Forms\Components\Radio::make('action_type')
                                ->label('Action')
                                ->options([
                                    'select_existing' => 'Select Existing Party',
                                    'create_new' => 'Create New Party',
                                ])
                                ->default('select_existing')
                                ->live()
                                ->required(),
                                
                            Forms\Components\Select::make('existing_party_id')
                                ->label('Existing Party')
                                ->options(function () {
                                    return \App\Domain\Party\Models\Party::all()->mapWithKeys(function ($party) {
                                        $phone = $party->phone ? " ({$party->phone})" : '';
                                        return [$party->id => $party->display_name . $phone];
                                    });
                                })
                                ->searchable()
                                ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('action_type') === 'select_existing')
                                ->required(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('action_type') === 'select_existing'),

                            Forms\Components\Radio::make('party_type')
                                ->label('Entity Type')
                                ->options([
                                    'individual' => 'Individual',
                                    'organization' => 'Company',
                                ])
                                ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('action_type') === 'create_new')
                                ->required(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('action_type') === 'create_new')
                                ->live(),

                            // --- INDIVIDUAL FIELDS ---
                            Forms\Components\TextInput::make('name')
                                ->label('Full Name')
                                ->default(fn (Mou $record) => $record->opportunity?->owner_name)
                                ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('action_type') === 'create_new' && $get('party_type') === 'individual')
                                ->required(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('action_type') === 'create_new' && $get('party_type') === 'individual'),
                            Forms\Components\TextInput::make('parent_name')
                                ->label('S/o or D/o (Parent/Guardian Name)')
                                ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('action_type') === 'create_new' && $get('party_type') === 'individual')
                                ->required(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('action_type') === 'create_new' && $get('party_type') === 'individual'),
                            Forms\Components\DatePicker::make('date_of_birth')
                                ->label('Date of Birth')
                                ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('action_type') === 'create_new' && $get('party_type') === 'individual'),
                            Forms\Components\Select::make('gender')
                                ->label('Gender')
                                ->options([
                                    'male' => 'Male',
                                    'female' => 'Female',
                                    'other' => 'Other',
                                ])
                                ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('action_type') === 'create_new' && $get('party_type') === 'individual'),

                            // --- ORGANIZATION FIELDS ---
                            Forms\Components\TextInput::make('legal_name')
                                ->label('Company Legal Name')
                                ->default(fn (Mou $record) => $record->opportunity?->owner_name)
                                ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('action_type') === 'create_new' && $get('party_type') === 'organization')
                                ->required(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('action_type') === 'create_new' && $get('party_type') === 'organization'),
                            Forms\Components\TextInput::make('contact_person_name')
                                ->label('Contact Person Name')
                                ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('action_type') === 'create_new' && $get('party_type') === 'organization'),
                            Forms\Components\TextInput::make('contact_person_phone')
                                ->label('Contact Person Phone')
                                ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('action_type') === 'create_new' && $get('party_type') === 'organization'),

                            // --- COMMON FIELDS ---
                            Forms\Components\TextInput::make('pan_number')
                                ->label('PAN Number')
                                ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('action_type') === 'create_new'),
                            Forms\Components\TextInput::make('gst_number')
                                ->label('GST Number')
                                ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('action_type') === 'create_new' && $get('party_type') === 'organization'),
                            Forms\Components\TextInput::make('aadhar_number')
                                ->label('Aadhar Number')
                                ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('action_type') === 'create_new' && $get('party_type') === 'individual'),
                            Forms\Components\TextInput::make('voter_id')
                                ->label('Voter ID')
                                ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('action_type') === 'create_new' && $get('party_type') === 'individual'),
                            Forms\Components\TextInput::make('phone')
                                ->label('Phone Number')
                                ->default(fn (Mou $record) => $record->opportunity->owner_phone)
                                ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('action_type') === 'create_new'),
                            Forms\Components\TextInput::make('email')
                                ->label('Email Address')
                                ->default(fn (Mou $record) => $record->opportunity->owner_email)
                                ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('action_type') === 'create_new'),
                            Forms\Components\Select::make('state_id')
                                ->label('State (Accounting)')
                                ->options(fn() => \Tek2991\Accounting\Models\State::pluck('name', 'id'))
                                ->searchable()
                                ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('action_type') === 'create_new')
                                ->required(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('action_type') === 'create_new'),
                            Forms\Components\Textarea::make('address')
                                ->label(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('party_type') === 'organization' ? 'Registered Address' : 'Personal Address')
                                ->default(fn (Mou $record) => $record->opportunity?->address)
                                ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('action_type') === 'create_new')
                                ->required(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('action_type') === 'create_new')
                                ->columnSpanFull(),
                        ])
                        ->action(function (Mou $record, array $data) {
                            app(\App\Domain\Mou\Services\MouService::class)->resolveParty($record, $data);
                            $record->refresh();
                            \Filament\Notifications\Notification::make()->title('Party Resolved')->success()->send();
                        }),

                    \Filament\Actions\Action::make('provisionAccounting')
                        ->label('Provision Accounting')
                        ->icon('heroicon-o-banknotes')
                        ->color('primary')
                        ->visible(fn (Mou $record) => $record->party_id && empty($record->bank_details) && $record->status === MouStatus::DRAFT)
                        ->form([
                            Forms\Components\TextInput::make('bank_name')->required(),
                            Forms\Components\TextInput::make('account_holder_name')->required(),
                            Forms\Components\TextInput::make('account_number')->required(),
                            Forms\Components\TextInput::make('ifsc_code')->required(),
                            Forms\Components\Textarea::make('bank_address')->label('Address of the Bank')->required()->columnSpanFull(),
                        ])
                        ->action(function (Mou $record, array $data) {
                            app(\App\Domain\Mou\Services\MouService::class)->provisionAccounting($record, $data);
                            $record->refresh();
                            \Filament\Notifications\Notification::make()->title('Accounting Provisioned')->success()->send();
                        }),

                    \Filament\Actions\Action::make('generatePdf')
                        ->label(fn (Mou $record) => $record->hasMedia('draft_pdf') ? 'Regenerate PDF' : 'Generate PDF')
                        ->icon('heroicon-o-document-arrow-down')
                        ->color('warning')
                        ->visible(fn (Mou $record) => in_array($record->status, [
                            MouStatus::DRAFT, 
                            MouStatus::PARTY_PENDING, 
                            MouStatus::READY_TO_GENERATE, 
                            MouStatus::PDF_GENERATED, 
                            MouStatus::DOWNLOADED,
                            MouStatus::SIGNED_COPY_UPLOADED
                        ]))
                        ->requiresConfirmation(fn (Mou $record) => $record->hasMedia('draft_pdf'))
                        ->modalHeading(fn (Mou $record) => $record->hasMedia('draft_pdf') ? 'Regenerate Draft PDF' : 'Generate Draft PDF')
                        ->modalDescription(fn (Mou $record) => $record->hasMedia('signed_pdf') 
                            ? 'Are you sure you want to regenerate the draft PDF? The currently uploaded signed PDF will be archived, and the MOU status will revert to "PDF Generated".' 
                            : 'Are you sure you want to generate a new draft PDF? This will increment the document version.')
                        ->action(function (Mou $record) {
                            try {
                                app(MouWorkflowService::class)->generatePdf($record);
                                $record->refresh();
                                \Filament\Notifications\Notification::make()->title('PDF Generated')->success()->send();
                            } catch (\Exception $e) {
                                \Filament\Notifications\Notification::make()->title('Cannot Generate PDF')->body($e->getMessage())->danger()->send();
                            }
                        }),
                        
                    \Filament\Actions\Action::make('uploadSignedCopy')
                        ->label('Upload Signed PDF')
                        ->icon('heroicon-o-document-arrow-up')
                        ->color('info')
                        ->visible(fn (Mou $record) => in_array($record->status, [MouStatus::PDF_GENERATED, MouStatus::DOWNLOADED, MouStatus::SIGNED_COPY_UPLOADED]))
                        ->form([
                            Forms\Components\FileUpload::make('signed_pdf')
                                ->label('Signed PDF File')
                                ->directory('temp-signed-pdfs')
                                ->acceptedFileTypes(['application/pdf'])
                                ->required(),
                        ])
                        ->action(function (Mou $record, array $data) {
                            app(MouWorkflowService::class)->uploadSignedCopy($record, $data['signed_pdf']);
                            $record->refresh();
                            \Filament\Notifications\Notification::make()->title('Signed Copy Uploaded')->success()->send();
                        }),
                        
                    \Filament\Actions\Action::make('verify')
                        ->label('Verify Agreement')
                        ->icon('heroicon-o-check-badge')
                        ->color('success')
                        ->visible(fn (Mou $record) => $record->status === MouStatus::SIGNED_COPY_UPLOADED)
                        ->requiresConfirmation()
                        ->action(function (Mou $record) {
                            app(MouWorkflowService::class)->verify($record);
                            $record->refresh();
                            \Filament\Notifications\Notification::make()->title('Agreement Verified')->success()->send();
                        }),
                        
                    \Filament\Actions\Action::make('convertToProperty')
                        ->label('Convert to Property')
                        ->icon('heroicon-o-building-office')
                        ->color('success')
                        ->visible(fn (Mou $record) => $record->status === MouStatus::VERIFIED)
                        ->requiresConfirmation()
                        ->action(function (Mou $record) {
                            $property = app(\App\Domain\Property\Services\PropertyOnboardingService::class)->createPropertyFromMou($record);
                            app(MouWorkflowService::class)->convert($record);
                            
                            \Filament\Notifications\Notification::make()->title('Property Created')->success()->send();
                            
                            return redirect(\App\Filament\Resources\Properties\PropertyResource::getUrl('edit', ['record' => $property]));
                        }),
                ])->label('Workflow Actions'),
            ])
            ->bulkActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\DeleteBulkAction::make(),
                    \Filament\Actions\ForceDeleteBulkAction::make(),
                    \Filament\Actions\RestoreBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMOUs::route('/'),
            'create' => Pages\CreateMOU::route('/create'),
            'view' => Pages\ViewMOU::route('/{record}'),
            'edit' => Pages\EditMOU::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
