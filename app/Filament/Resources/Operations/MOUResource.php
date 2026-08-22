<?php

namespace App\Filament\Resources\Operations;

use App\Domain\Mou\Enums\MouType;
use App\Domain\Mou\Models\Mou;
use App\Domain\Opportunity\Enums\MouStatus;
use App\Domain\Mou\Services\MouWorkflowService;
use App\Domain\Property\Services\PropertyOnboardingService;
use App\Filament\Resources\Operations\MOUResource\Pages;
use App\Filament\Resources\Operations\MOUResource\Schemas\MOUForm;
use App\Filament\Resources\Operations\MOUResource\Tables\MOUsTable;
use App\Filament\Resources\Properties\PropertyResource;
use BackedEnum;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class MOUResource extends Resource
{
    protected static ?string $model = Mou::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentCheck;

    protected static \UnitEnum|string|null $navigationGroup = 'Sales & CRM';

    protected static ?int $navigationSort = 2;

    public static function canViewAny(): bool
    {
        return auth()->user()->hasAnyRole(['Business Owner', 'Operations Manager', 'Legal']);
    }

    public static function canEdit(?\Illuminate\Database\Eloquent\Model $record = null): bool
    {
        if (! $record) {
            return true;
        }

        return ! in_array($record->status, [
            \App\Domain\Opportunity\Enums\MouStatus::VERIFIED,
            \App\Domain\Opportunity\Enums\MouStatus::CONVERTED,
        ]);
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function canForceDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return MOUForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MOUsTable::configure($table);
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

    public static function getGeneratePdfAction(string $name = 'generatePdf'): \Filament\Actions\Action
    {
        return \Filament\Actions\Action::make($name)
            ->label(fn (?Mou $record) => $record?->hasMedia('draft_pdf') ? 'Regenerate PDF' : 'Generate PDF')
            ->icon('heroicon-o-document-arrow-down')
            ->color('warning')
            ->size('sm')
            ->visible(fn (?Mou $record) => $record && in_array($record->status, [
                MouStatus::DRAFT, 
                MouStatus::PARTY_PENDING, 
                MouStatus::READY_TO_GENERATE, 
                MouStatus::PDF_GENERATED, 
                MouStatus::DOWNLOADED,
                MouStatus::SIGNED_COPY_UPLOADED
            ]))
            ->requiresConfirmation(fn (?Mou $record) => (bool) $record?->hasMedia('draft_pdf'))
            ->modalHeading(fn (?Mou $record) => $record?->hasMedia('draft_pdf') ? 'Regenerate Draft PDF' : 'Generate Draft PDF')
            ->modalDescription(fn (?Mou $record) => $record?->hasMedia('signed_pdf') 
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
            });
    }

    public static function getUploadSignedCopyAction(string $name = 'uploadSignedCopy'): \Filament\Actions\Action
    {
        return \Filament\Actions\Action::make($name)
            ->label('Upload Signed PDF')
            ->icon('heroicon-o-document-arrow-up')
            ->color('info')
            ->size('sm')
            ->visible(fn (?Mou $record) => $record && in_array($record->status, [
                MouStatus::PDF_GENERATED, 
                MouStatus::DOWNLOADED, 
                MouStatus::SIGNED_COPY_UPLOADED
            ]))
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
            });
    }

    public static function getResolvePartyAction(string $name = 'resolveParty'): \Filament\Actions\Action
    {
        return \Filament\Actions\Action::make($name)
            ->label('Resolve Party')
            ->icon('heroicon-o-users')
            ->color('primary')
            ->visible(fn (?Mou $record) => $record && !$record->party_id && static::canEdit($record))
            ->form(static::getResolvePartyFormSchema())
            ->action(function (Mou $record, array $data) {
                app(\App\Domain\Mou\Services\MouService::class)->resolveParty($record, $data);
                $record->refresh();
                \Filament\Notifications\Notification::make()->title('Party Resolved')->success()->send();
            });
    }

    public static function getUpdatePartyAction(string $name = 'updateParty'): \Filament\Actions\Action
    {
        return \Filament\Actions\Action::make($name)
            ->label('Update Party Details')
            ->icon('heroicon-o-pencil-square')
            ->color('primary')
            ->visible(fn (?Mou $record) => $record && $record->party_id && static::canEdit($record))
            ->fillForm(function (Mou $record): array {
                $party = $record->party;
                if (!$party) {
                    return [
                        'action_type' => 'create_new',
                        'party_type' => 'individual',
                        'name' => $record->opportunity?->owner_name ?? ($record->owner_details['name'] ?? ''),
                        'phone' => $record->opportunity?->owner_phone ?? ($record->owner_details['phone'] ?? ''),
                        'email' => $record->opportunity?->owner_email ?? ($record->owner_details['email'] ?? ''),
                        'address' => $record->opportunity?->address ?? ($record->owner_details['address'] ?? ''),
                    ];
                }

                $party->loadMissing(['individual', 'organization', 'addresses']);
                $primaryAddress = $party->addresses->where('is_primary', true)->first() ?? $party->addresses->first();

                return [
                    'action_type' => 'update_current',
                    'existing_party_id' => $party->id,
                    'party_type' => $party->party_type ?? 'individual',
                    'name' => $party->individual?->name ?? $party->display_name ?? ($record->owner_details['name'] ?? ''),
                    'parent_name' => $party->individual?->parent_name ?? ($record->owner_details['parent_name'] ?? ''),
                    'date_of_birth' => $party->individual?->date_of_birth,
                    'gender' => $party->individual?->gender,
                    'pan_number' => $party->individual?->pan_number ?? $party->organization?->pan ?? ($record->owner_details['pan_number'] ?? ''),
                    'aadhar_number' => $party->individual?->aadhaar_number ?? ($record->owner_details['aadhar_number'] ?? ''),
                    'voter_id' => $party->individual?->voter_id ?? ($record->owner_details['voter_id'] ?? ''),
                    'legal_name' => $party->organization?->legal_name ?? $party->display_name ?? ($record->owner_details['name'] ?? ''),
                    'contact_person_name' => $party->organization?->contact_person_name ?? ($record->owner_details['contact_person_name'] ?? ''),
                    'contact_person_phone' => $party->organization?->contact_person_phone ?? ($record->owner_details['contact_person_phone'] ?? ''),
                    'gst_number' => $party->organization?->gstin ?? ($record->owner_details['gstin'] ?? ''),
                    'phone' => $party->phone ?? ($record->owner_details['phone'] ?? ''),
                    'email' => $party->email ?? ($record->owner_details['email'] ?? ''),
                    'state_id' => $party->state_id,
                    'address' => $primaryAddress?->address_line_1 ?? ($record->owner_details['address'] ?? ''),
                ];
            })
            ->form(static::getUpdatePartyFormSchema())
            ->action(function (Mou $record, array $data) {
                app(\App\Domain\Mou\Services\MouService::class)->updatePartyDetails($record, $data);
                $record->refresh();
                \Filament\Notifications\Notification::make()->title('Party Details Updated')->success()->send();
            });
    }

    public static function getVerifyAction(string $name = 'verify'): \Filament\Actions\Action
    {
        return \Filament\Actions\Action::make($name)
            ->label('Verify Agreement')
            ->icon('heroicon-o-check-badge')
            ->color('success')
            ->visible(fn (?Mou $record) => $record?->status === MouStatus::SIGNED_COPY_UPLOADED)
            ->requiresConfirmation()
            ->modalHeading('Verify & Legally Approve MOU')
            ->modalDescription('Confirming verification will lock all legal terms and unlock property conversion.')
            ->modalSubmitActionLabel('Yes, Verify Agreement')
            ->action(function (Mou $record) {
                app(MouWorkflowService::class)->verify($record);
                $record->refresh();
                Notification::make()->title('Agreement Verified')->success()->send();
            });
    }

    public static function getConvertToPropertyAction(string $name = 'convertToProperty'): \Filament\Actions\Action
    {
        return \Filament\Actions\Action::make($name)
            ->label('Convert to Property')
            ->icon('heroicon-o-building-office')
            ->color('success')
            ->visible(fn (?Mou $record) => $record?->status === MouStatus::VERIFIED && ($record?->type === MouType::ONBOARDING || $record?->type === null))
            ->requiresConfirmation()
            ->modalHeading('Convert Verified MOU into Active Property')
            ->modalDescription('This will create an official property record, establish unit structures, and transition onboarding workflow.')
            ->modalSubmitActionLabel('Yes, Convert to Property')
            ->action(function (?Mou $record = null) {
                $property = app(PropertyOnboardingService::class)->createPropertyFromMou($record);
                app(MouWorkflowService::class)->convert($record);

                Notification::make()->title('Property Created')->success()->send();

                return redirect(PropertyResource::getUrl('edit', ['record' => $property]));
            });
    }

    public static function getArchiveAction(string $name = 'archive'): \Filament\Actions\Action
    {
        return \Filament\Actions\Action::make($name)
            ->label('Archive')
            ->icon('heroicon-o-archive-box')
            ->color('danger')
            ->visible(fn (?Mou $record) => $record && $record->verified_at === null && ! in_array($record->status, [
                MouStatus::VERIFIED,
                MouStatus::CONVERTED,
                MouStatus::COMPLETED,
                MouStatus::CANCELLED,
            ]))
            ->requiresConfirmation()
            ->modalHeading('Archive MOU')
            ->modalDescription('Are you sure you want to archive this MOU? The corresponding opportunity will also be marked as Closed Lost.')
            ->modalSubmitActionLabel('Archive')
            ->action(function (Mou $record, $livewire = null) {
                try {
                    app(MouWorkflowService::class)->archive($record);
                    Notification::make()
                        ->title('MOU Archived')
                        ->body('The MOU has been archived and the opportunity marked as Closed Lost.')
                        ->success()
                        ->send();
                    if ($livewire && method_exists($livewire, 'redirect')) {
                        $livewire->redirect(static::getUrl('index'));
                    }
                } catch (\Exception $e) {
                    Notification::make()
                        ->title('Cannot Archive MOU')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }

    public static function getProvisionAccountingAction(string $name = 'provisionAccounting'): \Filament\Actions\Action
    {
        return \Filament\Actions\Action::make($name)
            ->label('Provision Accounting')
            ->icon('heroicon-o-banknotes')
            ->color('primary')
            ->visible(fn (?Mou $record) => $record && $record->party_id && empty($record->bank_details) && $record->status === MouStatus::DRAFT)
            ->form([
                Forms\Components\TextInput::make('bank_name')->required(),
                Forms\Components\TextInput::make('account_holder_name')->required(),
                Forms\Components\TextInput::make('account_number')->required(),
                Forms\Components\Select::make('account_type')
                    ->label('Account Type')
                    ->options([
                        'Saving' => 'Savings Account',
                        'Current' => 'Current Account',
                    ])
                    ->default('Current')
                    ->required(),
                Forms\Components\TextInput::make('ifsc_code')->required(),
                Forms\Components\Textarea::make('bank_address')->label('Address of the Bank')->required()->columnSpanFull(),
            ])
            ->action(function (Mou $record, array $data) {
                app(\App\Domain\Mou\Services\MouService::class)->provisionAccounting($record, $data);
                $record->refresh();
                Notification::make()->title('Accounting Provisioned')->success()->send();
            });
    }

    public static function getResolvePartyFormSchema(): array
    {
        return [
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
                ->default(fn (?Mou $record) => $record?->opportunity?->owner_name)
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
                ->default(fn (?Mou $record) => $record?->opportunity?->owner_name)
                ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('action_type') === 'create_new' && $get('party_type') === 'organization')
                ->required(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('action_type') === 'create_new' && $get('party_type') === 'organization'),
            Forms\Components\TextInput::make('contact_person_name')
                ->label('Contact Person Name')
                ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('action_type') === 'create_new' && $get('party_type') === 'organization')
                ->required(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('action_type') === 'create_new' && $get('party_type') === 'organization'),
            Forms\Components\TextInput::make('contact_person_phone')
                ->label('Contact Person Phone')
                ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('action_type') === 'create_new' && $get('party_type') === 'organization')
                ->required(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('action_type') === 'create_new' && $get('party_type') === 'organization'),

            // --- COMMON FIELDS ---
            Forms\Components\TextInput::make('pan_number')
                ->label('PAN Number')
                ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('action_type') === 'create_new')
                ->required(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('action_type') === 'create_new'),
            Forms\Components\TextInput::make('gst_number')
                ->label('GST Number')
                ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('action_type') === 'create_new' && $get('party_type') === 'organization'),
            Forms\Components\TextInput::make('aadhar_number')
                ->label('Aadhar Number')
                ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('action_type') === 'create_new' && $get('party_type') === 'individual')
                ->required(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('action_type') === 'create_new' && $get('party_type') === 'individual'),
            Forms\Components\TextInput::make('voter_id')
                ->label('Voter ID')
                ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('action_type') === 'create_new' && $get('party_type') === 'individual'),
            Forms\Components\TextInput::make('phone')
                ->label('Phone Number')
                ->default(fn (?Mou $record) => $record?->opportunity?->owner_phone)
                ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('action_type') === 'create_new')
                ->required(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('action_type') === 'create_new'),
            Forms\Components\TextInput::make('email')
                ->label('Email Address')
                ->default(fn (?Mou $record) => $record?->opportunity?->owner_email)
                ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('action_type') === 'create_new')
                ->required(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('action_type') === 'create_new'),
            Forms\Components\Select::make('state_id')
                ->label('State (Accounting)')
                ->options(fn() => \Tek2991\Accounting\Models\State::pluck('name', 'id'))
                ->searchable()
                ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('action_type') === 'create_new')
                ->required(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('action_type') === 'create_new'),
            Forms\Components\Textarea::make('address')
                ->label(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('party_type') === 'organization' ? 'Registered Address' : 'Personal Address')
                ->default(fn (?Mou $record) => $record?->opportunity?->address)
                ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('action_type') === 'create_new')
                ->required(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('action_type') === 'create_new')
                ->columnSpanFull(),
        ];
    }

    public static function getUpdatePartyFormSchema(): array
    {
        return [
            Forms\Components\Radio::make('action_type')
                ->label('Action')
                ->options(function (?Mou $record) {
                    $partyName = $record?->party?->display_name ?? 'Resolved Party';
                    return [
                        'update_current' => "Update Current Party ({$partyName})",
                        'select_existing' => 'Switch to Different Existing Party',
                        'create_new' => 'Create & Switch to New Party',
                    ];
                })
                ->default('update_current')
                ->live()
                ->required(),

            Forms\Components\Select::make('existing_party_id')
                ->label('Select Existing Party')
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
                ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => in_array($get('action_type'), ['update_current', 'create_new']))
                ->required(fn (\Filament\Schemas\Components\Utilities\Get $get) => in_array($get('action_type'), ['update_current', 'create_new']))
                ->live(),

            // --- INDIVIDUAL FIELDS ---
            Forms\Components\TextInput::make('name')
                ->label('Full Name')
                ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => in_array($get('action_type'), ['update_current', 'create_new']) && $get('party_type') === 'individual')
                ->required(fn (\Filament\Schemas\Components\Utilities\Get $get) => in_array($get('action_type'), ['update_current', 'create_new']) && $get('party_type') === 'individual'),
            Forms\Components\TextInput::make('parent_name')
                ->label('S/o or D/o (Parent/Guardian Name)')
                ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => in_array($get('action_type'), ['update_current', 'create_new']) && $get('party_type') === 'individual')
                ->required(fn (\Filament\Schemas\Components\Utilities\Get $get) => in_array($get('action_type'), ['update_current', 'create_new']) && $get('party_type') === 'individual'),
            Forms\Components\DatePicker::make('date_of_birth')
                ->label('Date of Birth')
                ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => in_array($get('action_type'), ['update_current', 'create_new']) && $get('party_type') === 'individual'),
            Forms\Components\Select::make('gender')
                ->label('Gender')
                ->options([
                    'male' => 'Male',
                    'female' => 'Female',
                    'other' => 'Other',
                ])
                ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => in_array($get('action_type'), ['update_current', 'create_new']) && $get('party_type') === 'individual'),

            // --- ORGANIZATION FIELDS ---
            Forms\Components\TextInput::make('legal_name')
                ->label('Company Legal Name')
                ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => in_array($get('action_type'), ['update_current', 'create_new']) && $get('party_type') === 'organization')
                ->required(fn (\Filament\Schemas\Components\Utilities\Get $get) => in_array($get('action_type'), ['update_current', 'create_new']) && $get('party_type') === 'organization'),
            Forms\Components\TextInput::make('contact_person_name')
                ->label('Contact Person Name')
                ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => in_array($get('action_type'), ['update_current', 'create_new']) && $get('party_type') === 'organization')
                ->required(fn (\Filament\Schemas\Components\Utilities\Get $get) => in_array($get('action_type'), ['update_current', 'create_new']) && $get('party_type') === 'organization'),
            Forms\Components\TextInput::make('contact_person_phone')
                ->label('Contact Person Phone')
                ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => in_array($get('action_type'), ['update_current', 'create_new']) && $get('party_type') === 'organization')
                ->required(fn (\Filament\Schemas\Components\Utilities\Get $get) => in_array($get('action_type'), ['update_current', 'create_new']) && $get('party_type') === 'organization'),

            // --- COMMON FIELDS ---
            Forms\Components\TextInput::make('pan_number')
                ->label('PAN Number')
                ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => in_array($get('action_type'), ['update_current', 'create_new']))
                ->required(fn (\Filament\Schemas\Components\Utilities\Get $get) => in_array($get('action_type'), ['update_current', 'create_new'])),
            Forms\Components\TextInput::make('gst_number')
                ->label('GST Number')
                ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => in_array($get('action_type'), ['update_current', 'create_new']) && $get('party_type') === 'organization'),
            Forms\Components\TextInput::make('aadhar_number')
                ->label('Aadhar Number')
                ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => in_array($get('action_type'), ['update_current', 'create_new']) && $get('party_type') === 'individual')
                ->required(fn (\Filament\Schemas\Components\Utilities\Get $get) => in_array($get('action_type'), ['update_current', 'create_new']) && $get('party_type') === 'individual'),
            Forms\Components\TextInput::make('voter_id')
                ->label('Voter ID')
                ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => in_array($get('action_type'), ['update_current', 'create_new']) && $get('party_type') === 'individual'),
            Forms\Components\TextInput::make('phone')
                ->label('Phone Number')
                ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => in_array($get('action_type'), ['update_current', 'create_new']))
                ->required(fn (\Filament\Schemas\Components\Utilities\Get $get) => in_array($get('action_type'), ['update_current', 'create_new'])),
            Forms\Components\TextInput::make('email')
                ->label('Email Address')
                ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => in_array($get('action_type'), ['update_current', 'create_new']))
                ->required(fn (\Filament\Schemas\Components\Utilities\Get $get) => in_array($get('action_type'), ['update_current', 'create_new'])),
            Forms\Components\Select::make('state_id')
                ->label('State (Accounting)')
                ->options(fn() => \Tek2991\Accounting\Models\State::pluck('name', 'id'))
                ->searchable()
                ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => in_array($get('action_type'), ['update_current', 'create_new']))
                ->required(fn (\Filament\Schemas\Components\Utilities\Get $get) => in_array($get('action_type'), ['update_current', 'create_new'])),
            Forms\Components\Textarea::make('address')
                ->label(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('party_type') === 'organization' ? 'Registered Address' : 'Personal Address')
                ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => in_array($get('action_type'), ['update_current', 'create_new']))
                ->required(fn (\Filament\Schemas\Components\Utilities\Get $get) => in_array($get('action_type'), ['update_current', 'create_new']))
                ->columnSpanFull(),
        ];
    }
}
