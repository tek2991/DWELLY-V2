<?php

namespace App\Filament\Resources\Operations\MOUResource\Pages;

use App\Filament\Resources\Operations\MOUResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Forms;
use App\Domain\Mou\Models\Mou;
use App\Domain\Opportunity\Enums\MouStatus;
use App\Domain\Mou\Services\MouWorkflowService;

class ViewMOU extends ViewRecord
{
    protected static string $resource = MOUResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            
            Actions\Action::make('viewOpportunity')
                ->label('View Opportunity')
                ->icon('heroicon-o-eye')
                ->color('gray')
                ->modalHeading('Opportunity Details')
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
                ]),
            
            Actions\Action::make('resolveParty')
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
                        ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('action_type') === 'create_new' && $get('party_type') === 'individual'),
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
                    Forms\Components\TextInput::make('phone')
                        ->label('Phone Number')
                        ->default(fn (Mou $record) => $record->opportunity->owner_phone)
                        ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('action_type') === 'create_new'),
                    Forms\Components\TextInput::make('email')
                        ->label('Email Address')
                        ->default(fn (Mou $record) => $record->opportunity->owner_email)
                        ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('action_type') === 'create_new'),
                    Forms\Components\Textarea::make('address')
                        ->label(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('party_type') === 'organization' ? 'Registered Address' : 'Personal Address')
                        ->default(fn (Mou $record) => $record->opportunity?->address)
                        ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('action_type') === 'create_new')
                        ->required(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('action_type') === 'create_new')
                        ->columnSpanFull(),
                ])
                ->action(function (Mou $record, array $data) {
                    app(\App\Domain\Mou\Services\MouService::class)->resolveParty($record, $data);
                    \Filament\Notifications\Notification::make()->title('Party Resolved')->success()->send();
                }),

            Actions\Action::make('provisionAccounting')
                ->label('Provision Accounting')
                ->icon('heroicon-o-banknotes')
                ->color('primary')
                ->visible(fn (Mou $record) => $record->party_id && empty($record->bank_details) && $record->status === MouStatus::DRAFT)
                ->form([
                    Forms\Components\TextInput::make('bank_name')->required(),
                    Forms\Components\TextInput::make('account_holder_name')->required(),
                    Forms\Components\TextInput::make('account_number')->required(),
                    Forms\Components\TextInput::make('ifsc_code')->required(),
                    Forms\Components\Textarea::make('branch_address')->label('Address of the Bank')->required()->columnSpanFull(),
                ])
                ->action(function (Mou $record, array $data) {
                    app(\App\Domain\Mou\Services\MouService::class)->provisionAccounting($record, $data);
                    \Filament\Notifications\Notification::make()->title('Accounting Provisioned')->success()->send();
                }),

            Actions\Action::make('generatePdf')
                ->label('Generate PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->color('warning')
                ->visible(fn (Mou $record) => in_array($record->status, [MouStatus::DRAFT, MouStatus::PARTY_PENDING]))
                ->action(function (Mou $record) {
                    try {
                        app(MouWorkflowService::class)->generatePdf($record);
                        \Filament\Notifications\Notification::make()->title('PDF Generated')->success()->send();
                    } catch (\Exception $e) {
                        \Filament\Notifications\Notification::make()->title('Cannot Generate PDF')->body($e->getMessage())->danger()->send();
                    }
                }),
                
            Actions\Action::make('viewDraftPdf')
                ->label('View Draft PDF')
                ->icon('heroicon-o-document-magnifying-glass')
                ->color('gray')
                ->visible(fn (Mou $record) => $record->hasMedia('draft_pdf') && !in_array($record->status, [MouStatus::VERIFIED, MouStatus::CONVERTED]))
                ->modalHeading('Draft MOU PDF')
                ->modalWidth('7xl')
                ->modalContent(fn (Mou $record) => view('components.pdf-viewer', [
                    'record' => $record,
                    'mediaCollection' => 'draft_pdf'
                ]))
                ->modalSubmitActionLabel('Download PDF')
                ->action(function (Mou $record) {
                    app(MouWorkflowService::class)->markAsDownloaded($record);
                    return response()->download($record->getFirstMedia('draft_pdf')->getPath(), $record->number . '-draft.pdf');
                }),
                
            Actions\Action::make('viewSignedPdf')
                ->label('View Signed PDF')
                ->icon('heroicon-o-document-check')
                ->color('info')
                ->visible(fn (Mou $record) => $record->hasMedia('signed_pdf'))
                ->modalHeading('Signed MOU PDF')
                ->modalWidth('7xl')
                ->modalContent(fn (Mou $record) => view('components.pdf-viewer', [
                    'record' => $record,
                    'mediaCollection' => 'signed_pdf'
                ]))
                ->modalSubmitActionLabel('Download PDF')
                ->action(function (Mou $record) {
                    return response()->download($record->getFirstMedia('signed_pdf')->getPath(), $record->number . '-signed.pdf');
                }),
                
            Actions\Action::make('uploadSignedCopy')
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
                    \Filament\Notifications\Notification::make()->title('Signed Copy Uploaded')->success()->send();
                }),
                
            Actions\Action::make('verify')
                ->label('Verify Agreement')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->visible(fn (Mou $record) => $record->status === MouStatus::SIGNED_COPY_UPLOADED)
                ->requiresConfirmation()
                ->action(function (Mou $record) {
                    app(MouWorkflowService::class)->verify($record);
                    \Filament\Notifications\Notification::make()->title('Agreement Verified')->success()->send();
                }),
                
            Actions\Action::make('convertToProperty')
                ->label('Convert to Property')
                ->icon('heroicon-o-building-office')
                ->color('success')
                ->visible(fn (Mou $record) => $record->status === MouStatus::VERIFIED)
                ->requiresConfirmation()
                ->action(function (Mou $record) {
                    $property = app(\App\Domain\Property\Services\PropertyOnboardingService::class)->createPropertyFromMou($record);
                    app(MouWorkflowService::class)->convert($record);
                    \Filament\Notifications\Notification::make()->title('Property Created')->success()->send();
                    // In real scenario, redirect to Property Resource View
                }),
        ];
    }
}
