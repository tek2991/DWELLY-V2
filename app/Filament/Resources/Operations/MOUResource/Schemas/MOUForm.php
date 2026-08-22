<?php

namespace App\Filament\Resources\Operations\MOUResource\Schemas;

use App\Domain\Geographic\Models\City;
use App\Domain\Mou\Models\Mou;
use App\Domain\Opportunity\Enums\MouStatus;
use App\Domain\Opportunity\Models\FinancialModel;
use App\Domain\Property\Models\UtilityProvider;
use App\Filament\Resources\Operations\MOUResource;
use App\Filament\Resources\Operations\OpportunityResource;
use App\Filament\Resources\Parties\PartyResource;
use App\Filament\Resources\Properties\PropertyResource;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

class MOUForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(3)
                ->columnSpanFull()
                ->schema([
                    // Main Left Column (2 Cols)
                    Grid::make(1)
                        ->columnSpan(2)
                        ->schema([
                            // 📍 Section 1: MOU Summary & Origin
                            Section::make('📍 MOU Summary & Origin')
                                ->key('mou_summary')
                                ->headerActions([
                                    MOUResource::getResolvePartyAction(),
                                ])
                                ->schema([
                                    Select::make('opportunity_id')
                                        ->label('Source Opportunity')
                                        ->relationship('opportunity', 'title')
                                        ->searchable()
                                        ->preload()
                                        ->required()
                                        ->disabled(fn (string $operation): bool => $operation !== 'create')
                                        ->hintAction(
                                            Action::make('viewOpportunity')
                                                ->label('')
                                                ->icon('heroicon-m-eye')
                                                ->tooltip('View Opportunity Details')
                                                ->modalHeading(fn (?Mou $record) => new HtmlString(
                                                    '<div style="display: flex; align-items: center; gap: 12px;">
                                                        <span>Opportunity Details</span>
                                                        ' . ($record?->opportunity ? '<a href="' . OpportunityResource::getUrl('view', ['record' => $record->opportunity]) . '" style="font-size: 13px; font-weight: 600; color: #2563eb; text-decoration: underline;">Open Full Page &rarr;</a>' : '') . '
                                                    </div>'
                                                ))
                                                ->modalSubmitAction(false)
                                                ->modalCancelActionLabel('Close')
                                                ->infolist([
                                                    Section::make('General Information')
                                                        ->schema([
                                                             \Filament\Infolists\Components\TextEntry::make('opportunity.title')->label('Title'),
                                                             \Filament\Infolists\Components\TextEntry::make('opportunity.status')->label('Status')->badge(),
                                                             \Filament\Infolists\Components\TextEntry::make('opportunity.opportunitySource.name')->label('Source'),
                                                             \Filament\Infolists\Components\TextEntry::make('opportunity.source_phone')->label('Source Phone'),
                                                             \Filament\Infolists\Components\TextEntry::make('opportunity.assignedUser.name')->label('Assigned To'),
                                                        ])->columns(2),

                                                    Section::make('Owner Information')
                                                        ->schema([
                                                             \Filament\Infolists\Components\TextEntry::make('opportunity.owner_name')->label('Owner Name'),
                                                             \Filament\Infolists\Components\TextEntry::make('opportunity.owner_phone')->label('Owner Phone'),
                                                             \Filament\Infolists\Components\TextEntry::make('opportunity.owner_email')->label('Owner Email'),
                                                             \Filament\Infolists\Components\TextEntry::make('opportunity.address')->label('Address')->columnSpanFull(),
                                                        ])->columns(2),

                                                    Section::make('Property & Commercial Estimates')
                                                        ->schema([
                                                             \Filament\Infolists\Components\TextEntry::make('opportunity.estimatedPropertyType.name')->label('Property Type'),
                                                             \Filament\Infolists\Components\TextEntry::make('opportunity.estimated_bhk')->label('BHK'),
                                                             \Filament\Infolists\Components\TextEntry::make('opportunity.estimated_size')->label('Size (Sq.Ft)'),
                                                             \Filament\Infolists\Components\IconEntry::make('opportunity.estimated_is_furnished')->label('Furnished')->boolean(),
                                                             \Filament\Infolists\Components\TextEntry::make('opportunity.expected_rent')->label('Expected Rent')->money('INR'),
                                                             \Filament\Infolists\Components\TextEntry::make('opportunity.expectedFinancialModel.name')->label('Financial Model'),
                                                             \Filament\Infolists\Components\TextEntry::make('opportunity.expected_onboarding_date')->label('Expected Onboarding')->date(),
                                                        ])->columns(3),

                                                    Section::make('Internal Summary')
                                                        ->schema([
                                                             \Filament\Infolists\Components\TextEntry::make('opportunity.internal_summary')
                                                                ->label('')
                                                                ->columnSpanFull()
                                                                ->default('No summary provided.'),
                                                        ]),
                                                ])
                                                ->visible(fn (?Mou $record) => $record?->opportunity !== null)
                                        ),

                                    Placeholder::make('property')
                                        ->label('Associated Property')
                                        ->content(function (?Mou $record): ?HtmlString {
                                            if ($record?->property) {
                                                $propertyName = e($record->property->building_name ?? $record->property->address_line_1 ?? 'Property');
                                                $codeBadge = $record->property->code 
                                                    ? ' <span style="font-size: 11px; font-family: monospace; font-weight: 600; padding: 1px 6px; border-radius: 4px; background-color: #f1f5f9; border: 1px solid #cbd5e1; color: #475569;">' . e($record->property->code) . '</span>' 
                                                    : '';

                                                return new HtmlString("<div style=\"padding-top: 6px; font-weight: 600; font-size: 13px; color: #0f172a;\">🏢 {$propertyName}{$codeBadge}</div>");
                                            }

                                            return new HtmlString('<div style="padding-top: 6px;"><span style="display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; font-size: 12px; font-weight: 500; border-radius: 6px; background-color: #f8fafc; color: #475569; border: 1px solid #e2e8f0;">⏳ Pending Onboarding Conversion</span></div>');
                                        })
                                        ->hintAction(
                                            Action::make('viewProperty')
                                                ->label('')
                                                ->icon('heroicon-m-eye')
                                                ->tooltip('View Property Details')
                                                ->modalHeading(fn (?Mou $record) => new HtmlString(
                                                    '<div style="display: flex; align-items: center; gap: 12px;">
                                                        <span>Property Details</span>
                                                        ' . ($record?->property ? '<a href="' . PropertyResource::getUrl('edit', ['record' => $record->property]) . '" style="font-size: 13px; font-weight: 600; color: #2563eb; text-decoration: underline;">Open Full Page &rarr;</a>' : '') . '
                                                    </div>'
                                                ))
                                                ->modalSubmitAction(false)
                                                ->modalCancelActionLabel('Close')
                                                ->infolist([
                                                    Section::make('General Information')
                                                        ->schema([
                                                             \Filament\Infolists\Components\TextEntry::make('property.code')->label('Code')->default('-'),
                                                             \Filament\Infolists\Components\TextEntry::make('property.status')->label('Status')->formatStateUsing(fn ($state) => ucfirst($state))->badge(),
                                                             \Filament\Infolists\Components\TextEntry::make('property.building_name')->label('Building Name')->default('-'),
                                                        ])->columns(3),
                                                    Section::make('Location Details')
                                                        ->schema([
                                                             \Filament\Infolists\Components\TextEntry::make('property.address_line_1')->label('Address Line 1')->columnSpanFull()->default('-'),
                                                             \Filament\Infolists\Components\TextEntry::make('property.locality.name')->label('Locality')->default('-'),
                                                             \Filament\Infolists\Components\TextEntry::make('property.city')->label('City')->default('-'),
                                                        ])->columns(2),
                                                ])
                                                ->visible(fn (?Mou $record) => $record?->property !== null)
                                        ),

                                    // Context Panel: Party / Owner Entity Summary
                                    Placeholder::make('party_context_card')
                                        ->label('Associated Legal Party & Signatory')
                                        ->columnSpanFull()
                                        ->hintActions([
                                            Action::make('viewParty')
                                                ->label('')
                                                ->icon('heroicon-m-eye')
                                                ->tooltip('View Party Details')
                                                ->modalHeading(fn (?Mou $record) => new HtmlString(
                                                    '<div style="display: flex; align-items: center; gap: 12px;">
                                                        <span>Party Details</span>
                                                        ' . ($record?->party ? '<a href="' . PartyResource::getUrl('edit', ['record' => $record->party]) . '" style="font-size: 13px; font-weight: 600; color: #2563eb; text-decoration: underline;">Open Full Page &rarr;</a>' : '') . '
                                                    </div>'
                                                ))
                                                ->modalSubmitAction(false)
                                                ->modalCancelActionLabel('Close')
                                                ->infolist([
                                                    Section::make('General Information')
                                                        ->schema([
                                                             \Filament\Infolists\Components\TextEntry::make('party.party_type')
                                                                ->label('Type')
                                                                ->formatStateUsing(fn ($state) => ucfirst($state))
                                                                ->badge(),
                                                             \Filament\Infolists\Components\TextEntry::make('party.display_name')->label('Name'),
                                                             \Filament\Infolists\Components\TextEntry::make('party.phone')->label('Phone')->default('-'),
                                                             \Filament\Infolists\Components\TextEntry::make('party.email')->label('Email')->default('-'),
                                                        ])->columns(2),

                                                    Section::make('Individual Details')
                                                        ->schema([
                                                             \Filament\Infolists\Components\TextEntry::make('party.individual.pan_number')->label('PAN Number')->default('-'),
                                                             \Filament\Infolists\Components\TextEntry::make('party.individual.aadhaar_number')->label('Aadhar Number')->default('-'),
                                                             \Filament\Infolists\Components\TextEntry::make('party.individual.address_line_1')->label('Address')->columnSpanFull()->default('-'),
                                                        ])->columns(2)
                                                        ->visible(fn (?Mou $record) => $record?->party?->party_type === 'individual'),

                                                    Section::make('Organization Details')
                                                        ->schema([
                                                             \Filament\Infolists\Components\TextEntry::make('party.organization.pan')->label('PAN Number')->default('-'),
                                                             \Filament\Infolists\Components\TextEntry::make('party.organization.gstin')->label('GSTIN')->default('-'),
                                                             \Filament\Infolists\Components\TextEntry::make('party.organization.contact_person_name')->label('Contact Person')->default('-'),
                                                             \Filament\Infolists\Components\TextEntry::make('party.organization.contact_person_phone')->label('Contact Phone')->default('-'),
                                                             \Filament\Infolists\Components\TextEntry::make('party.organization.registered_address')->label('Address')->columnSpanFull()->default('-'),
                                                        ])->columns(2)
                                                        ->visible(fn (?Mou $record) => $record?->party?->party_type === 'organization'),
                                                ])
                                                ->visible(fn (?Mou $record) => $record?->party !== null),

                                            MOUResource::getUpdatePartyAction('editPartyFromHint')
                                                ->label('')
                                                ->tooltip('Update Party Details')
                                                ->icon('heroicon-m-pencil-square')
                                                ->visible(fn (?Mou $record) => (bool) $record?->party && MOUResource::canEdit($record)),
                                        ])
                                        ->content(function (?Mou $record): HtmlString {
                                            $party = $record?->party;
                                            if (! $party) {
                                                return new HtmlString(
                                                    '<div style="background-color: #fffbeb; border: 1px solid #fde68a; border-radius: 8px; padding: 14px 16px; margin-top: 4px;">' .
                                                        '<div style="display: flex; align-items: center; gap: 10px;">' .
                                                            '<div style="font-size: 20px;">⚠️</div>' .
                                                            '<div>' .
                                                                '<div style="font-size: 13px; font-weight: 700; color: #92400e;">Legal Party Unresolved</div>' .
                                                                '<div style="font-size: 12px; color: #b45309; margin-top: 2px;">Click "Resolve Party" in the section header above to link or register the legal owner party entity.</div>' .
                                                            '</div>' .
                                                        '</div>' .
                                                    '</div>'
                                                );
                                            }

                                            $partyType = ucfirst($party->party_type ?? 'Individual');
                                            $phone = e($party->phone ?? ($record->owner_details['phone'] ?? '—'));
                                            $email = e($party->email ?? ($record->owner_details['email'] ?? '—'));
                                            $partyName = e($party->display_name ?? ($record->owner_details['name'] ?? 'Unnamed'));
                                            $identifierLabel = $party->party_type === 'organization' ? 'GSTIN' : 'PAN';
                                            $identifierVal = $party->party_type === 'organization'
                                                ? e($party->organization?->gstin ?? ($record->owner_details['gstin'] ?? '—'))
                                                : e($party->individual?->pan_number ?? ($record->owner_details['pan_number'] ?? '—'));

                                            $address = e(
                                                $party->addresses?->first()?->address_line_1 
                                                ?? $party->individual?->address_line_1 
                                                ?? ($record->owner_details['address'] ?? '—')
                                            );

                                            return new HtmlString(
                                                '<div style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px 16px; margin-top: 4px; box-shadow: 0 1px 2px rgba(0,0,0,0.03);">' .
                                                    '<div style="display: flex; align-items: center; justify-content: space-between; padding-bottom: 10px; border-bottom: 1px solid #e2e8f0; margin-bottom: 12px;">' .
                                                        '<div style="display: flex; align-items: center; gap: 8px;">' .
                                                            '<span style="font-size: 14px; font-weight: 700; color: #0f172a;">👤 ' . $partyName . '</span>' .
                                                            '<span style="font-size: 11px; font-weight: 600; padding: 2px 8px; border-radius: 4px; background-color: #dbeafe; color: #1d4ed8;">' . $partyType . '</span>' .
                                                        '</div>' .
                                                        '<span style="font-size: 11px; font-family: monospace; font-weight: 600; padding: 2px 8px; border-radius: 4px; background-color: #ffffff; border: 1px solid #cbd5e1; color: #334155;">' . $identifierLabel . ': ' . $identifierVal . '</span>' .
                                                    '</div>' .
                                                    '<div style="display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 16px; font-size: 12px;">' .
                                                        '<div>' .
                                                            '<span style="display: block; font-size: 11px; font-weight: 500; color: #64748b; margin-bottom: 2px;">Phone</span>' .
                                                            '<span style="font-family: monospace; font-weight: 600; color: #0f172a;">' . $phone . '</span>' .
                                                        '</div>' .
                                                        '<div>' .
                                                            '<span style="display: block; font-size: 11px; font-weight: 500; color: #64748b; margin-bottom: 2px;">Email</span>' .
                                                            '<span style="font-weight: 600; color: #0f172a;">' . $email . '</span>' .
                                                        '</div>' .
                                                        '<div>' .
                                                            '<span style="display: block; font-size: 11px; font-weight: 500; color: #64748b; margin-bottom: 2px;">Registered Address</span>' .
                                                            '<span style="font-weight: 500; color: #0f172a; display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="' . $address . '">' . $address . '</span>' .
                                                        '</div>' .
                                                    '</div>' .
                                                '</div>'
                                            );
                                        }),
                                ])->columns(2),

                            // 🏢 Section 2: Property & Commercial Legal Terms
                            Section::make('🏢 Property & Commercial Legal Terms')
                                ->description('These details are mapped to the MOU Document and can be modified here without affecting the original Opportunity.')
                                ->schema([
                                    TextInput::make('legal_terms.rent_amount')
                                        ->label('Agreed Monthly Rent')
                                        ->placeholder('e.g. 50000')
                                        ->numeric()
                                        ->prefix('₹'),

                                    TextInput::make('legal_terms.fee_percentage')
                                        ->label('Dwelly Fee Percentage')
                                        ->placeholder('e.g. 10.00')
                                        ->numeric()
                                        ->suffix('%')
                                        ->step(0.01)
                                        ->minValue(0)
                                        ->maxValue(100),

                                    Select::make('legal_terms.financial_model_id')
                                        ->label('Financial Model')
                                        ->options(fn () => FinancialModel::pluck('name', 'id'))
                                        ->required()
                                        ->searchable()
                                        ->preload(),

                                    Select::make('legal_terms.electricity_provider_id')
                                        ->label('Electricity Provider')
                                        ->options(function () {
                                            return UtilityProvider::whereHas('utilityType', function ($query) {
                                                $query->where('slug', 'electricity');
                                            })->pluck('name', 'id');
                                        })
                                        ->searchable()
                                        ->preload()
                                        ->required(),

                                    TextInput::make('legal_terms.electricity_consumer_id')
                                        ->label('Electricity Consumer / Connection Number')
                                        ->placeholder('e.g. CA-992817263')
                                        ->maxLength(255)
                                        ->required(),

                                    Select::make('legal_terms.city_id')
                                        ->label('City')
                                        ->options(fn () => City::pluck('name', 'id'))
                                        ->searchable()
                                        ->preload()
                                        ->required(),

                                    Textarea::make('legal_terms.address')
                                        ->label('Property Address')
                                        ->placeholder('Full registered address of the property as stated in agreement...')
                                        ->required()
                                        ->columnSpanFull(),
                                ])->columns(2),

                            // 📁 Section 3: Owner KYC & Verification Documents
                            Section::make('📁 Owner KYC & Verification Documents')
                                ->description('Attach official identity, banking, and utility documents for agreement compilation and audit records.')
                                ->schema([
                                    DatePicker::make('start_date')
                                        ->label('MOU Agreement Start Date')
                                        ->required()
                                        ->native(false),

                                    Grid::make(2)
                                        ->schema([
                                            SpatieMediaLibraryFileUpload::make('owner_aadhaar')
                                                ->collection('owner_aadhaar')
                                                ->label('Owner Aadhaar Card')
                                                ->helperText('Front & Back image or PDF')
                                                ->openable()
                                                ->downloadable()
                                                ->previewable()
                                                ->required(),

                                            SpatieMediaLibraryFileUpload::make('owner_pan')
                                                ->collection('owner_pan')
                                                ->label('Owner PAN Card')
                                                ->helperText('Clear image or PDF')
                                                ->openable()
                                                ->downloadable()
                                                ->previewable()
                                                ->required(),

                                            SpatieMediaLibraryFileUpload::make('cancelled_cheque')
                                                ->collection('cancelled_cheque')
                                                ->label('Cancelled Cheque / Bank Proof')
                                                ->helperText('Cancelled Cheque or Bank Passbook copy')
                                                ->openable()
                                                ->downloadable()
                                                ->previewable()
                                                ->required(),

                                            SpatieMediaLibraryFileUpload::make('electricity_bill')
                                                ->collection('electricity_bill')
                                                ->label('Electricity Bill')
                                                ->helperText('Recent electricity bill image or PDF')
                                                ->openable()
                                                ->downloadable()
                                                ->previewable()
                                                ->required(),
                                        ]),

                                    Actions::make([
                                        Action::make('uploadDocumentModal')
                                            ->label('Upload Additional Documents')
                                            ->icon('heroicon-o-arrow-up-tray')
                                            ->color('primary')
                                            ->button()
                                            ->disabled(fn (?Mou $record) => $record && (
                                                in_array($record->status, [
                                                    MouStatus::VERIFIED,
                                                    MouStatus::CONVERTED,
                                                    MouStatus::COMPLETED,
                                                    MouStatus::CANCELLED,
                                                ]) || ! MOUResource::canEdit($record)
                                            ))
                                            ->tooltip(fn (?Mou $record) => $record && (
                                                in_array($record->status, [
                                                    MouStatus::VERIFIED,
                                                    MouStatus::CONVERTED,
                                                    MouStatus::COMPLETED,
                                                    MouStatus::CANCELLED,
                                                ]) || ! MOUResource::canEdit($record)
                                            ) ? 'Document uploads are locked once the agreement is verified or converted.' : null)
                                            ->modalHeading('Upload Document (Select Type & File)')
                                            ->modalDescription('Select document type (Passport, Voter ID, MGNREGA Card, Sale Deed, etc.) and attach file.')
                                            ->form([
                                                Select::make('document_type')
                                                    ->label('Document Type')
                                                    ->options(\App\Domain\Shared\Enums\DocumentType::class)
                                                    ->required()
                                                    ->searchable(),
                                                FileUpload::make('files')
                                                    ->label('Files (Images / PDF)')
                                                    ->multiple()
                                                    ->preserveFilenames()
                                                    ->required(),
                                            ])
                                            ->action(function (array $data, ?Mou $record, Set $set, Get $get) {
                                                $collection = match ($data['document_type']) {
                                                    'aadhaar', 'owner_aadhaar' => 'owner_aadhaar',
                                                    'pan', 'owner_pan' => 'owner_pan',
                                                    'cancelled_cheque' => 'cancelled_cheque',
                                                    'electricity_bill' => 'electricity_bill',
                                                    'power_of_attorney' => 'signatory_poa',
                                                    default => 'mou_attachments',
                                                };

                                                if ($record && $record->exists) {
                                                    foreach ($data['files'] as $path) {
                                                        $fullPath = Storage::disk(config('filament.default_filesystem_disk'))->path($path);
                                                        if (! file_exists($fullPath)) {
                                                            $fullPath = Storage::disk('public')->path($path);
                                                        }

                                                        $record->addMedia($fullPath)
                                                            ->withCustomProperties([
                                                                'document_type' => $data['document_type'],
                                                            ])
                                                            ->toMediaCollection($collection);
                                                    }
                                                    $record->refresh();
                                                    Notification::make()->title('Document Uploaded Successfully')->success()->send();
                                                } else {
                                                    $existing = $get($collection) ?? [];
                                                    if (is_string($existing)) {
                                                        $existing = [$existing];
                                                    }
                                                    $merged = array_values(array_unique(array_merge((array) $existing, (array) $data['files'])));
                                                    $set($collection, $merged);
                                                    Notification::make()
                                                        ->title('Document Attached')
                                                        ->body('File added to form. It will be saved when you submit the MOU.')
                                                        ->success()
                                                        ->send();
                                                }
                                            }),
                                    ]),
                                ]),

                            // ✍️ Section 4: Signatory Authority & POA Details
                            Section::make('✍️ Signatory Authority & POA Details')
                                ->description('Specify whether the agreement is signed by the registered owner or an authorized Power of Attorney (POA).')
                                ->schema([
                                    Toggle::make('is_signatory_different')
                                        ->label('Is Signatory Authority different from Property Owner?')
                                        ->default(false)
                                        ->live()
                                        ->helperText('Enable if an authorized representative / POA holder will sign this agreement.'),

                                    Grid::make(2)
                                        ->schema([
                                            TextInput::make('signatory_details.name')
                                                ->label('Signatory Full Name')
                                                ->required(fn (Get $get) => $get('is_signatory_different')),
                                            TextInput::make('signatory_details.relation')
                                                ->label('Relation to Owner (e.g. POA Holder, Son, Brother)')
                                                ->required(fn (Get $get) => $get('is_signatory_different')),
                                            TextInput::make('signatory_details.phone')
                                                ->label('Phone Number')
                                                ->tel()
                                                ->required(fn (Get $get) => $get('is_signatory_different')),
                                            TextInput::make('signatory_details.email')
                                                ->label('Email Address')
                                                ->email()
                                                ->required(fn (Get $get) => $get('is_signatory_different')),
                                            TextInput::make('signatory_details.aadhar_number')
                                                ->label('Aadhaar Number')
                                                ->required(fn (Get $get) => $get('is_signatory_different')),
                                            TextInput::make('signatory_details.pan_number')
                                                ->label('PAN Number')
                                                ->required(fn (Get $get) => $get('is_signatory_different')),
                                        ])
                                        ->visible(fn (Get $get) => $get('is_signatory_different')),

                                    Grid::make(3)
                                        ->schema([
                                            SpatieMediaLibraryFileUpload::make('signatory_aadhaar')
                                                ->collection('signatory_aadhaar')
                                                ->label('Signatory Aadhaar Card')
                                                ->openable()
                                                ->downloadable()
                                                ->previewable()
                                                ->required(fn (Get $get) => $get('is_signatory_different')),

                                            SpatieMediaLibraryFileUpload::make('signatory_pan')
                                                ->collection('signatory_pan')
                                                ->label('Signatory PAN Card')
                                                ->openable()
                                                ->downloadable()
                                                ->previewable()
                                                ->required(fn (Get $get) => $get('is_signatory_different')),

                                            SpatieMediaLibraryFileUpload::make('signatory_poa')
                                                ->collection('signatory_poa')
                                                ->label('Power of Attorney (POA) Document')
                                                ->openable()
                                                ->downloadable()
                                                ->previewable()
                                                ->required(fn (Get $get) => $get('is_signatory_different')),
                                        ])
                                        ->visible(fn (Get $get) => $get('is_signatory_different')),

                                    Actions::make([
                                        Action::make('uploadSignatoryDocumentModal')
                                            ->label('Upload Additional Signatory Documents')
                                            ->icon('heroicon-o-arrow-up-tray')
                                            ->color('primary')
                                            ->button()
                                            ->modalHeading('Upload Signatory Document (Select Type & File)')
                                            ->modalDescription('Select document type (Passport, Voter ID, Power of Attorney, etc.) and attach file for Signatory Authority.')
                                            ->form([
                                                Select::make('document_type')
                                                    ->label('Document Type')
                                                    ->options(\App\Domain\Shared\Enums\DocumentType::class)
                                                    ->required()
                                                    ->searchable(),
                                                FileUpload::make('files')
                                                    ->label('Files (Images / PDF)')
                                                    ->multiple()
                                                    ->preserveFilenames()
                                                    ->required(),
                                            ])
                                            ->action(function (array $data, ?Mou $record, Set $set, Get $get) {
                                                $collection = match ($data['document_type']) {
                                                    'aadhaar' => 'signatory_aadhaar',
                                                    'pan' => 'signatory_pan',
                                                    'power_of_attorney' => 'signatory_poa',
                                                    default => 'signatory_documents',
                                                };

                                                if ($record && $record->exists) {
                                                    foreach ($data['files'] as $path) {
                                                        $fullPath = Storage::disk(config('filament.default_filesystem_disk'))->path($path);
                                                        if (! file_exists($fullPath)) {
                                                            $fullPath = Storage::disk('public')->path($path);
                                                        }

                                                        $record->addMedia($fullPath)
                                                            ->withCustomProperties([
                                                                'document_type' => $data['document_type'],
                                                                'entity_type' => 'signatory',
                                                            ])
                                                            ->toMediaCollection($collection);
                                                    }
                                                    $record->refresh();
                                                    Notification::make()->title('Signatory Document Uploaded Successfully')->success()->send();
                                                } else {
                                                    $existing = $get($collection) ?? [];
                                                    if (is_string($existing)) {
                                                        $existing = [$existing];
                                                    }
                                                    $merged = array_values(array_unique(array_merge((array) $existing, (array) $data['files'])));
                                                    $set($collection, $merged);
                                                    Notification::make()
                                                        ->title('Signatory Document Attached')
                                                        ->body('File added to form. It will be saved when you submit the MOU.')
                                                        ->success()
                                                        ->send();
                                                }
                                            }),
                                    ])
                                    ->visible(fn (Get $get) => $get('is_signatory_different')),
                                ])->columns(1)
                                ->collapsible(),

                            // 🏦 Section 5: Banking & Settlement Details
                            Section::make('🏦 Bank Account & Payout Details')
                                ->description('Owner bank account credentials for rent payouts and financial settlement credits.')
                                ->schema([
                                    TextInput::make('bank_details.bank_name')
                                        ->label('Bank Name')
                                        ->placeholder('e.g. HDFC Bank')
                                        ->required(),
                                    TextInput::make('bank_details.beneficiary_name')
                                        ->label('Beneficiary Account Name')
                                        ->placeholder('e.g. Rajesh Kumar')
                                        ->required(),
                                    TextInput::make('bank_details.account_number')
                                        ->label('Account Number')
                                        ->placeholder('e.g. 50100293847281')
                                        ->required(),
                                    Select::make('bank_details.account_type')
                                        ->label('Account Type')
                                        ->options([
                                            'Saving' => 'Savings Account',
                                            'Current' => 'Current Account',
                                        ])
                                        ->default('Current'),
                                    TextInput::make('bank_details.ifsc_code')
                                        ->label('IFSC Code')
                                        ->placeholder('e.g. HDFC0001234')
                                        ->required(),
                                    Textarea::make('bank_details.bank_address')
                                        ->label('Bank Branch Address')
                                        ->placeholder('Branch name, city, pin code...')
                                        ->required()
                                        ->columnSpanFull(),
                                ])->columns(2),
                        ]),

                    // Right Sidebar (1 Col)
                    Grid::make(1)
                        ->columnSpan(1)
                        ->schema([
                            // ⚡ MOU Status & Workflow Hub
                            Section::make('⚡ MOU Status & Workflow Hub')
                                ->schema([
                                    Actions::make([
                                        MOUResource::getUploadSignedCopyAction(),
                                        MOUResource::getGeneratePdfAction(),
                                    ])
                                        ->alignment(Alignment::End)
                                        ->columnSpanFull(),

                                    Placeholder::make('status_header_badge')
                                        ->label('Operational Status')
                                        ->content(function (?Mou $record) {
                                            $status = $record?->status ?? MouStatus::DRAFT;
                                            $label = e($status->getLabel());
                                            $color = match ($status) {
                                                MouStatus::DRAFT => 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200',
                                                MouStatus::PARTY_PENDING => 'bg-amber-100 text-amber-800 dark:bg-amber-900/60 dark:text-amber-200 font-semibold',
                                                MouStatus::READY_TO_GENERATE => 'bg-sky-100 text-sky-800 dark:bg-sky-900/60 dark:text-sky-200',
                                                MouStatus::PDF_GENERATED, MouStatus::DOWNLOADED => 'bg-blue-100 text-blue-800 dark:bg-blue-900/60 dark:text-blue-200 font-semibold',
                                                MouStatus::SIGNED_COPY_UPLOADED => 'bg-purple-100 text-purple-800 dark:bg-purple-900/60 dark:text-purple-200 font-semibold',
                                                MouStatus::VERIFIED => 'bg-teal-100 text-teal-800 dark:bg-teal-900/60 dark:text-teal-200 font-bold',
                                                MouStatus::COMPLETED, MouStatus::CONVERTED => 'bg-green-100 text-green-800 dark:bg-green-900/60 dark:text-green-200 font-bold',
                                                MouStatus::EXPIRED => 'bg-orange-100 text-orange-800 dark:bg-orange-900/60 dark:text-orange-200',
                                                MouStatus::CANCELLED => 'bg-red-100 text-red-800 dark:bg-red-900/60 dark:text-red-200',
                                            };

                                            return new HtmlString("<div class=\"flex items-center gap-1.5 flex-wrap\"><span class=\"inline-flex items-center px-3 py-1 rounded-full text-xs font-bold {$color}\">{$label}</span></div>");
                                        }),

                                    Placeholder::make('mou_number')
                                        ->label('MOU Number')
                                        ->content(function (?Mou $record) {
                                            $num = $record?->number;
                                            if (! $num) {
                                                return new HtmlString('<span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-mono text-gray-400 bg-gray-50 dark:bg-gray-800 border border-dashed border-gray-300 dark:border-gray-700">Auto-generated</span>');
                                            }

                                            return new HtmlString("<span class=\"inline-flex items-center px-3 py-1 rounded-md text-xs font-mono font-bold bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200 border border-gray-300 dark:border-gray-700\">#{$num}</span>");
                                        }),

                                    Placeholder::make('workflow_guidance_banner')
                                        ->hiddenLabel()
                                        ->columnSpanFull()
                                        ->visible(function (?Mou $record) {
                                            if (! $record) {
                                                return false;
                                            }

                                            return ! $record->party_id
                                                || in_array($record->status, [
                                                    MouStatus::PDF_GENERATED,
                                                    MouStatus::DOWNLOADED,
                                                    MouStatus::SIGNED_COPY_UPLOADED,
                                                    MouStatus::VERIFIED,
                                                    MouStatus::CONVERTED,
                                                ]);
                                        })
                                        ->content(function (?Mou $record) {
                                            if (! $record) {
                                                return null;
                                            }

                                            if (! $record->party_id) {
                                                return new HtmlString(
                                                    '<div style="background-color: rgba(217, 119, 6, 0.08); border-left: 4px solid #d97706; padding: 10px 14px; border-radius: 6px; font-size: 13px; color: #b45309;">' .
                                                    '⚠️ <strong>Party Resolution Required:</strong> Click <strong>Resolve Party</strong> above to map or create the owner profile before generating draft agreement.' .
                                                    '</div>'
                                                );
                                            }

                                            if ($record->status === MouStatus::PDF_GENERATED || $record->status === MouStatus::DOWNLOADED) {
                                                return new HtmlString(
                                                    '<div style="background-color: rgba(37, 99, 235, 0.08); border-left: 4px solid #2563eb; padding: 10px 14px; border-radius: 6px; font-size: 13px; color: #1e40af;">' .
                                                    '📄 <strong>Draft PDF Generated:</strong> Download agreement from Document History below for owner signature, then upload signed copy in actions.' .
                                                    '</div>'
                                                );
                                            }

                                            if ($record->status === MouStatus::SIGNED_COPY_UPLOADED) {
                                                return new HtmlString(
                                                    '<div style="background-color: rgba(147, 51, 234, 0.08); border-left: 4px solid #9333ea; padding: 10px 14px; border-radius: 6px; font-size: 13px; color: #6b21a8;">' .
                                                    '✍️ <strong>Signed Copy Uploaded:</strong> Legal review pending. Click <strong>Verify Agreement</strong> in actions to approve.' .
                                                    '</div>'
                                                );
                                            }

                                            if ($record->status === MouStatus::VERIFIED) {
                                                return new HtmlString(
                                                    '<div style="background-color: rgba(16, 185, 129, 0.08); border-left: 4px solid #10b981; padding: 10px 14px; border-radius: 6px; font-size: 13px; color: #065f46;">' .
                                                    '✅ <strong>Agreement Verified:</strong> Legal terms locked. Click <strong>Convert to Property</strong> in actions to initiate onboarding.' .
                                                    '</div>'
                                                );
                                            }

                                            if ($record->status === MouStatus::CONVERTED) {
                                                return new HtmlString(
                                                    '<div style="background-color: rgba(16, 185, 129, 0.08); border-left: 4px solid #10b981; padding: 10px 14px; border-radius: 6px; font-size: 13px; color: #065f46;">' .
                                                    '🏢 <strong>Converted to Active Property:</strong> Property profile is initialized.' .
                                                    '</div>'
                                                );
                                            }

                                            return null;
                                        }),

                                    Placeholder::make('versions')
                                        ->label('Document History & Versions')
                                        ->content(fn (?Mou $record): \Illuminate\Contracts\View\View => view('mou.version-history', ['record' => $record])),
                                ]),
                        ]),
                ]),
        ]);
    }
}
