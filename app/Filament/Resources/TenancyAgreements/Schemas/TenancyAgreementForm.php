<?php

namespace App\Filament\Resources\TenancyAgreements\Schemas;

use App\Domain\Agreement\Models\TenancyAgreement;
use App\Domain\Audit\Enums\AuditStatus;
use App\Domain\Audit\Models\Audit;
use App\Domain\Party\Models\Party;
use App\Domain\Property\Models\Property;
use App\Domain\Property\Models\UtilityProvider;
use App\Domain\Shared\Enums\DocumentType;
use App\Domain\Shared\Enums\KycRequirementLevel;
use App\Domain\Shared\Models\KycDocumentType;
use App\Filament\Resources\Operations\AuditResource;
use App\Filament\Resources\Operations\TenantDeboardingResource;
use App\Filament\Resources\Parties\PartyResource;
use App\Filament\Resources\Properties\PropertyResource;
use App\Filament\Resources\TenancyAgreements\TenancyAgreementResource;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions as SchemaActions;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\Rule;
use Tek2991\Accounting\Enums\InvoiceStatus;

class TenancyAgreementForm
{
    public static function configure(Schema $schema): Schema
    {
        $operation = $schema->getOperation();

        if ($operation === 'create') {
            return static::configureCreationForm($schema);
        }

        return static::configureOverviewForm($schema);
    }

    public static function configureCreationForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('1. Tenant Selection / Inline Party Creation')
                    ->schema([
                        Toggle::make('create_new_tenant')
                            ->label('Create New Tenant Party')
                            ->helperText('ON: Enter new tenant details inline (default) | OFF: Select an existing party from database')
                            ->default(true)
                            ->live()
                            ->columnSpanFull(),

                        // Existing Tenant Select
                        Select::make('primary_tenant_id')
                            ->label('Select Existing Tenant (Party)')
                            ->options(fn () => Party::whereHas('tenantProfile')
                                ->orWhere('party_type', 'individual')
                                ->pluck('display_name', 'id'))
                            ->searchable()
                            ->live()
                            ->hidden(fn (Get $get) => (bool) $get('create_new_tenant'))
                            ->required(fn (Get $get) => ! (bool) $get('create_new_tenant'))
                            ->afterStateUpdated(function (?string $state, Set $set) {
                                if ($state) {
                                    $party = Party::find($state);
                                    if ($party?->individual) {
                                        if ($party->individual->aadhaar_number) {
                                            $set('new_tenant.aadhaar_number', $party->individual->aadhaar_number);
                                        }
                                        if ($party->individual->pan_number) {
                                            $set('new_tenant.pan_number', $party->individual->pan_number);
                                        }
                                        if ($party->individual->voter_id) {
                                            $set('new_tenant.voter_id', $party->individual->voter_id);
                                        }
                                    }
                                }
                            })
                            ->columnSpanFull(),

                        Placeholder::make('existing_tenant_context_card')
                            ->label('Selected Tenant Information')
                            ->columnSpanFull()
                            ->hidden(fn (Get $get) => (bool) $get('create_new_tenant') || empty($get('primary_tenant_id')))
                            ->content(function (Get $get): HtmlString {
                                $partyId = $get('primary_tenant_id');
                                $party = $partyId ? Party::find($partyId) : null;
                                if (! $party) {
                                    return new HtmlString('');
                                }

                                $partyType = ucfirst($party->party_type ?? 'Individual');
                                $phone = e($party->phone ?? '—');
                                $email = e($party->email ?? '—');
                                $partyName = e($party->display_name ?? 'Unnamed');
                                $pan = e($party->individual?->pan_number ?? '—');
                                $aadhaar = e($party->individual?->aadhaar_number ?? '—');
                                $address = e($party->addresses?->first()?->address_line_1 ?? '—');

                                return new HtmlString(
                                    '<div style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px 16px; margin-top: 4px; box-shadow: 0 1px 2px rgba(0,0,0,0.03);">' .
                                        '<div style="display: flex; align-items: center; justify-content: space-between; padding-bottom: 10px; border-bottom: 1px solid #e2e8f0; margin-bottom: 12px;">' .
                                            '<div style="display: flex; align-items: center; gap: 8px;">' .
                                                '<span style="font-size: 14px; font-weight: 700; color: #0f172a;">👤 ' . $partyName . '</span>' .
                                                '<span style="font-size: 11px; font-weight: 600; padding: 2px 8px; border-radius: 4px; background-color: #dbeafe; color: #1d4ed8;">' . $partyType . '</span>' .
                                            '</div>' .
                                            '<div style="display: flex; gap: 8px;">' .
                                                '<span style="font-size: 11px; font-family: monospace; font-weight: 600; padding: 2px 8px; border-radius: 4px; background-color: #ffffff; border: 1px solid #cbd5e1; color: #334155;">PAN: ' . $pan . '</span>' .
                                                '<span style="font-size: 11px; font-family: monospace; font-weight: 600; padding: 2px 8px; border-radius: 4px; background-color: #ffffff; border: 1px solid #cbd5e1; color: #334155;">Aadhaar: ' . $aadhaar . '</span>' .
                                            '</div>' .
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
                                                '<span style="display: block; font-size: 11px; font-weight: 500; color: #64748b; margin-bottom: 2px;">Permanent Address</span>' .
                                                '<span style="font-weight: 500; color: #0f172a; display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="' . $address . '">' . $address . '</span>' .
                                            '</div>' .
                                        '</div>' .
                                    '</div>'
                                );
                            }),

                        // Inline New Tenant Form
                        Group::make()
                            ->schema([
                                Section::make('👤 Personal Information')
                                    ->compact()
                                    ->schema([
                                        Grid::make(2)
                                            ->schema([
                                                TextInput::make('new_tenant.display_name')
                                                    ->label('Tenant Full Name (Required)')
                                                    ->required(fn (Get $get) => (bool) $get('create_new_tenant')),

                                                TextInput::make('new_tenant.phone')
                                                    ->label('Phone Number (Required)')
                                                    ->tel()
                                                    ->required(fn (Get $get) => (bool) $get('create_new_tenant')),

                                                TextInput::make('new_tenant.email')
                                                    ->label('Email Address (Optional)')
                                                    ->email(),

                                                TextInput::make('new_tenant.parent_name')
                                                    ->label("Father's / Care-Of Name (Required)")
                                                    ->placeholder('e.g. S/o Late Rajesh Sharma')
                                                    ->required(fn (Get $get) => (bool) $get('create_new_tenant')),

                                                TextInput::make('new_tenant.address_line_1')
                                                    ->label('Permanent Address (Required)')
                                                    ->placeholder('Permanent residence address')
                                                    ->required(fn (Get $get) => (bool) $get('create_new_tenant'))
                                                    ->columnSpanFull(),
                                            ]),
                                    ]),
                            ])
                            ->visible(fn (Get $get) => (bool) $get('create_new_tenant'))
                            ->columnSpanFull(),
                    ]),

                Section::make('2. Select Property & Commencement Dates')
                    ->description('Select the target property for onboarding and initial commercial parameters')
                    ->schema([
                        Select::make('property_id')
                            ->label('Property')
                            ->options(function (?TenancyAgreement $record) {
                                $query = Property::query()
                                    ->whereRaw('LOWER(status) = ?', ['vacant']);

                                if ($record && $record->property_id) {
                                    $query->orWhere('id', $record->property_id);
                                }

                                return $query->get()->mapWithKeys(function ($p) {
                                    $name = $p->building_name ?? $p->address_line_1 ?? 'Property #'.$p->id;

                                    return [
                                        $p->id => $p->code ? "{$p->code} - {$name}" : $name,
                                    ];
                                });
                            })
                            ->searchable()
                            ->required()
                            ->default(request()->query('property_id'))
                            ->rules([
                                Rule::exists('properties', 'id')->where(function ($query) {
                                    $query->whereRaw('LOWER(status) = ?', ['vacant']);
                                }),
                            ])
                            ->validationMessages([
                                'exists' => 'The selected property is not vacant or available for tenancy.',
                            ])
                            ->live()
                            ->afterStateHydrated(function ($state, Set $set) {
                                if ($state) {
                                    $property = Property::find($state);
                                    $pricing = $property?->pricingVersions()->latest('effective_from')->first();
                                    if ($pricing && ! empty($pricing->booking_amount)) {
                                        $set('booking_amount', $pricing->booking_amount);
                                    }
                                }
                            })
                            ->afterStateUpdated(function (?string $state, Set $set) {
                                if ($state) {
                                    $property = Property::find($state);
                                    $pricing = $property?->pricingVersions()->latest('effective_from')->first();
                                    if ($pricing && ! empty($pricing->booking_amount)) {
                                        $set('booking_amount', $pricing->booking_amount);
                                    }
                                }
                            })
                            ->columnSpan(['default' => 1, 'sm' => 2]),

                        TextInput::make('booking_amount')
                            ->label('Booking Token Amount')
                            ->numeric()
                            ->prefix('₹')
                            ->placeholder('e.g. 5000')
                            ->required()
                            ->default(function () {
                                $propertyId = request()->query('property_id');
                                if ($propertyId) {
                                    $property = Property::find($propertyId);
                                    $pricing = $property?->pricingVersions()->latest('effective_from')->first();

                                    return $pricing?->booking_amount ?? 0;
                                }

                                return null;
                            })
                            ->helperText('Token collected at initiation')
                            ->columnSpan(['default' => 1, 'sm' => 2]),

                        DatePicker::make('start_date')
                            ->label('Agreement Start Date')
                            ->required()
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Get $get, Set $set, ?string $state) {
                                if ($state) {
                                    if (! $get('end_date')) {
                                        try {
                                            $startDate = Carbon::parse($state);
                                            $set('end_date', $startDate->copy()->addMonths(11)->subDay()->format('Y-m-d'));
                                        } catch (\Throwable $e) {
                                            // ignore
                                        }
                                    }
                                    $propertyId = $get('property_id');
                                    if ($propertyId) {
                                        $property = Property::find($propertyId);
                                        $pricing = $property?->pricingVersions()->latest('effective_from')->first();
                                        $rent = $pricing?->rent ?? 0;
                                        $set('first_month_rent', static::calculateProRatedFirstMonthRent($state, $rent));
                                    }
                                }
                            })
                            ->columnSpan(1),

                        DatePicker::make('end_date')
                            ->label('Agreement End Date')
                            ->required()
                            ->columnSpan(1),

                        TextInput::make('first_month_rent')
                            ->label('First Month Rent (Pro-rated / Custom)')
                            ->numeric()
                            ->prefix('₹')
                            ->placeholder('e.g. 15000')
                            ->required()
                            ->live(onBlur: true)
                            ->helperText('Supports proportional mid-month move-in')
                            ->columnSpan(['default' => 1, 'sm' => 2]),

                        Textarea::make('first_month_rent_notes')
                            ->label('Manual Adjustment Remarks / Proration Basis')
                            ->placeholder('Specify reason for custom/prorated first-month rent...')
                            ->rows(2)
                            ->required(fn (Get $get) => static::isFormStateProrated($get))
                            ->helperText(fn (Get $get) => static::isFormStateProrated($get)
                                ? 'Required: Provide reason / calculation basis for prorated rent.'
                                : 'Optional notes on rent calculation.')
                            ->columnSpan(['default' => 1, 'sm' => 2]),

                        SpatieMediaLibraryFileUpload::make('first_month_rent_proof')
                            ->label('Proration Basis & Adjustment Proof')
                            ->collection('first_month_rent_proof')
                            ->multiple()
                            ->downloadable()
                            ->openable()
                            ->acceptedFileTypes(['image/*', 'application/pdf'])
                            ->required(fn (Get $get) => static::isFormStateProrated($get))
                            ->helperText(fn (Get $get) => static::isFormStateProrated($get)
                                ? 'Required: Upload chat screenshots or calculation proof.'
                                : 'Optional chat screenshots or approval notes')
                            ->columnSpan(['default' => 1, 'sm' => 2]),
                    ])->columns(['default' => 1, 'sm' => 2, 'lg' => 4]),

                Section::make('📁 3. Primary Tenant KYC & Document Collection')
                    ->description('Attach official identity, photo, and banking verification documents for the primary tenant.')
                    ->headerActions([
                        static::getTenantKycUploadAction(),
                    ])
                    ->schema([
                        Grid::make(['default' => 1, 'md' => 2])
                            ->schema(static::getDynamicTenantKycFields()),
                    ])
                    ->columnSpanFull(),

                Placeholder::make('workflow_notice')
                    ->label('Workflow Initiation Notice')
                    ->content(new HtmlString(
                        '<div class="p-4 bg-blue-50 dark:bg-blue-950 border border-blue-200 dark:border-blue-800 rounded-lg text-blue-900 dark:text-blue-100 text-sm">'.
                            '<strong>What happens next?</strong><br>'.
                            '• Creates the Tenant Party record & enables Tenant role.<br>'.
                            '• Initiates the Tenancy Agreement record.<br>'.
                            '• <strong>Automatically triggers and creates a Move-In Audit</strong> linked to the Property and Tenant.<br>'.
                            '• Redirects you to the Onboarding Workflow management page.'.
                            '</div>'
                    ))
                    ->columnSpanFull(),
            ]);
    }

    public static function getWorkflowHeaderHtml(?TenancyAgreement $record): ?HtmlString
    {
        if (! $record) {
            return null;
        }

        $status = $record->status ?? 'draft';
        $code = $record->code ?? '';

        $property = $record->property;
        $propertyName = $property?->building_name ?? $property?->address_line_1 ?? 'Property #'.$property?->id;
        $propertyCode = $property?->code;
        $address = $property?->address_line_1 ?? '';
        $bhk = $property?->bhkType?->name;
        $ownerParty = $property?->owner;
        $ownerName = $ownerParty?->display_name ?? 'N/A';
        $ownerUrl = $ownerParty ? PartyResource::getUrl('edit', ['record' => $ownerParty]) : null;
        $ownerLinkHtml = $ownerUrl
            ? ' <a href="'.e($ownerUrl).'" target="_blank" title="View Owner Party Profile" style="display: inline-flex; align-items: center; color: #2563eb; text-decoration: none; margin-left: 2px;"><svg style="width: 14px; height: 14px; display: inline-block; vertical-align: text-bottom;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg></a>'
            : '';

        $primaryRole = $record->roles()->where('is_primary', true)->first();
        $tenantParty = $primaryRole?->party ?? $record->tenants->first();
        $tenantName = $tenantParty?->display_name ?? 'N/A';
        $tenantUrl = $tenantParty ? PartyResource::getUrl('edit', ['record' => $tenantParty]) : null;
        $tenantLinkHtml = $tenantUrl
            ? ' <a href="'.e($tenantUrl).'" target="_blank" title="View Tenant Party Profile" style="display: inline-flex; align-items: center; color: #2563eb; text-decoration: none; margin-left: 2px;"><svg style="width: 14px; height: 14px; display: inline-block; vertical-align: text-bottom;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg></a>'
            : '';

        $propertyUrl = $property ? PropertyResource::getUrl('edit', ['record' => $property]) : '#';

        $audit = $record->audit;
        $auditStatusValue = $audit?->status instanceof AuditStatus
            ? $audit->status->value
            : (string) ($audit?->status ?? '');
        $s1 = (bool) ($audit && in_array($auditStatusValue, ['approved', 'completed']));
        $s2 = static::areTermsComplete($record);
        $s3 = (bool) ($record->signed_by_tenant && $record->hasMedia('signed_agreement'));
        $s4 = (bool) $record->keys_handed_over || $status === 'active';

        $completedCount = ($s1 ? 1 : 0) + ($s2 ? 1 : 0) + ($s3 ? 1 : 0) + ($s4 ? 1 : 0);
        $progress = (int) round(($completedCount / 4) * 100);
        $progressColor = $progress === 100 ? '#10b981' : ($progress >= 50 ? '#3b82f6' : '#f59e0b');

        $getStepStyle = function (bool $isValid, string $title, string $desc) {
            $bg = $isValid ? 'rgba(16, 185, 129, 0.08)' : 'rgba(128, 128, 128, 0.04)';
            $border = $isValid ? 'rgba(16, 185, 129, 0.3)' : 'rgba(128, 128, 128, 0.18)';
            $titleColor = $isValid ? '#059669' : 'inherit';
            $descColor = $isValid ? '#10b981' : 'rgba(128, 128, 128, 0.75)';
            $icon = $isValid ? '✓' : '○';
            $iconColor = $isValid ? '#10b981' : 'rgba(128, 128, 128, 0.6)';

            return '<div style="padding: 0.85rem; border-radius: 0.75rem; border: 1px solid '.$border.'; background-color: '.$bg.';">'.
                '<div style="display: flex; align-items: center; gap: 0.5rem;">'.
                '<span style="font-size: 1rem; font-weight: 700; color: '.$iconColor.';">'.$icon.'</span>'.
                '<h4 style="font-size: 0.875rem; font-weight: 600; margin: 0; color: '.$titleColor.';">'.e($title).'</h4>'.
                '</div>'.
                '<div style="font-size: 0.75rem; margin-top: 0.35rem; color: '.$descColor.';">'.e($desc).'</div>'.
                '</div>';
        };

        $card1 = $getStepStyle($s1, '1. Move-In Audit', $s1 ? 'Audit approved' : 'Pending audit approval');
        $card2 = $getStepStyle($s2, '2. Terms & Drafts', $s2 ? 'Terms set & drafts ready' : 'Fill all agreement terms & bank details');
        $card3 = $getStepStyle($s3, '3. Signed Agreement', $s3 ? 'Agreement executed & signed' : 'Upload signed agreement PDF');
        $card4 = $getStepStyle($s4, '4. Keys & Active', $s4 ? 'Keys handed over & tenancy active' : 'Handover keys & activate');

        $badgeBg = $status === 'active' ? '#10b981' : '#3b82f6';

        $pendingList = static::getPendingActivationRequirements($record);
        $canActivate = empty($pendingList) && $status !== 'active';

        if ($status === 'active') {
            $user = auth()->user();
            $canRenew = ! $user || $user->can('renew', $record);
            $canDeboard = ! $user || $user->can('deboard', $record);

            $actionButtons = '';
            if ($canRenew) {
                $actionButtons .= '<button type="button" wire:click="mountAction(\'renewTenancyHeader\')" style="display: inline-flex; align-items: center; gap: 6px; padding: 7px 15px; background-color: #7c3aed; color: #ffffff; font-weight: 600; font-size: 13px; border-radius: 6px; border: none; cursor: pointer; box-shadow: 0 1px 2px rgba(0,0,0,0.05); transition: background-color 150ms;">'.
                    '<svg style="width: 15px; height: 15px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>'.
                    'Renew Agreement'.
                    '</button>';
            }
            if ($canDeboard) {
                $actionButtons .= '<button type="button" wire:click="mountAction(\'initiateDeboardingHeader\')" style="display: inline-flex; align-items: center; gap: 6px; padding: 7px 15px; background-color: #d97706; color: #ffffff; font-weight: 600; font-size: 13px; border-radius: 6px; border: none; cursor: pointer; box-shadow: 0 1px 2px rgba(0,0,0,0.05); transition: background-color 150ms;">'.
                    '<svg style="width: 15px; height: 15px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>'.
                    'Initiate Deboarding & Exit Audit'.
                    '</button>';
            }

            $activationBannerHtml = '<div style="margin-top: 1rem; padding: 0.85rem 1.25rem; background-color: rgba(16, 185, 129, 0.08); border: 1px solid rgba(16, 185, 129, 0.3); border-radius: 0.5rem; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">'.
                '<div style="display: flex; align-items: center; gap: 8px; color: #047857; font-weight: 700; font-size: 14px;">'.
                '<span>✓ Tenancy Agreement is Active</span>'.
                '<span style="font-size: 12px; font-weight: 500; color: #059669;">&bull; Move-In Audit is permanently locked</span>'.
                '</div>'.
                '<div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">'.
                $actionButtons.
                '</div>'.
                '</div>';
        } elseif ($status === 'renewed') {
            $latestRenewal = $record->latestRenewal;
            $renewalUrl = $latestRenewal ? TenancyAgreementResource::getUrl('edit', ['record' => $latestRenewal]) : '#';
            $activationBannerHtml = '<div style="margin-top: 1rem; padding: 0.85rem 1.25rem; background-color: rgba(124, 58, 237, 0.08); border: 1px solid rgba(124, 58, 237, 0.3); border-radius: 0.5rem; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">'.
                '<div>'.
                '<div style="font-weight: 700; font-size: 14px; color: #6d28d9;">🔄 Tenancy Agreement Renewed</div>'.
                '<div style="font-size: 12px; color: rgba(128, 128, 128, 0.85); margin-top: 2px;">This lease has been renewed and superseded by a subsequent agreement.</div>'.
                '</div>'.
                ($latestRenewal ? '<a href="'.e($renewalUrl).'" style="display: inline-flex; align-items: center; gap: 6px; padding: 7px 15px; background-color: #7c3aed; color: #ffffff; font-weight: 600; font-size: 13px; border-radius: 6px; text-decoration: none; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">Open Active Renewal ('.e($latestRenewal->code).') &rarr;</a>' : '').
                '</div>';
        } elseif (in_array($status, ['deboarding_initiated', 'vacated'])) {
            $deboardUrl = $record->deboarding
                ? TenantDeboardingResource::getUrl('edit', ['record' => $record->deboarding->id])
                : TenancyAgreementResource::getUrl('deboard', ['record' => $record]);
            $activationBannerHtml = '<div style="margin-top: 1rem; padding: 0.85rem 1.25rem; background-color: rgba(245, 158, 11, 0.08); border: 1px solid rgba(245, 158, 11, 0.25); border-radius: 0.5rem; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">'.
                '<div>'.
                '<div style="font-weight: 700; font-size: 14px; color: #b45309;">⚠️ Tenancy Deboarding in Progress ('.ucfirst(str_replace('_', ' ', $status)).')</div>'.
                '<div style="font-size: 12px; color: rgba(128, 128, 128, 0.85); margin-top: 2px;">Move-Out verification inspection, maintenance resolution, and security deposit settlement.</div>'.
                '</div>'.
                '<a href="'.e($deboardUrl).'" style="display: inline-flex; align-items: center; gap: 6px; padding: 7px 15px; background-color: #0284c7; color: #ffffff; font-weight: 600; font-size: 13px; border-radius: 6px; text-decoration: none; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">'.
                'Open Deboarding Workflow &rarr;'.
                '</a>'.
                '</div>';
        } elseif ($canActivate) {
            $user = auth()->user();
            $canUserActivate = ! $user || $user->can('activate', $record);

            $buttonHtml = $canUserActivate
                ? '<button type="button" wire:click="mountAction(\'activateTenancyHeader\')" wire:loading.attr="disabled" style="display: inline-flex; align-items: center; gap: 6px; padding: 8px 18px; background-color: #16a34a; color: #ffffff; font-weight: 700; font-size: 13px; border-radius: 6px; border: none; cursor: pointer; box-shadow: 0 1px 3px rgba(0,0,0,0.1); transition: background-color 150ms;">'.
                    '<svg style="width: 15px; height: 15px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>'.
                    'Activate Tenancy Now'.
                    '</button>'
                : '<span style="display: inline-flex; align-items: center; gap: 4px; padding: 6px 12px; border-radius: 6px; background-color: rgba(22, 163, 74, 0.1); color: #15803d; font-size: 12px; font-weight: 600;">Awaiting Dual-Key Activation Clearance</span>';

            $activationBannerHtml = '<div style="margin-top: 1rem; padding: 0.85rem 1.25rem; background-color: rgba(22, 163, 74, 0.08); border: 1px solid rgba(22, 163, 74, 0.3); border-radius: 0.5rem; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">'.
                '<div>'.
                '<div style="font-weight: 700; font-size: 14px; color: #15803d;">⚡ All Onboarding Checks Complete!</div>'.
                '<div style="font-size: 12px; color: rgba(128, 128, 128, 0.9); margin-top: 2px;">Ready to activate tenancy, set property to occupied, and lock move-in audit.</div>'.
                '</div>'.
                $buttonHtml.
                '</div>';
        } else {
            $pendingText = 'Pending checks: '.implode(', ', array_map('lcfirst', $pendingList));
            $activationBannerHtml = '<div style="margin-top: 1rem; padding: 0.85rem 1.25rem; background-color: rgba(245, 158, 11, 0.08); border: 1px solid rgba(245, 158, 11, 0.25); border-radius: 0.5rem; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">'.
                '<div>'.
                '<div style="font-weight: 700; font-size: 13px; color: #b45309;">⚠️ Tenancy Activation Pending</div>'.
                '<div style="font-size: 12px; color: rgba(128, 128, 128, 0.85); margin-top: 2px;">'.e($pendingText).'</div>'.
                '</div>'.
                '<span style="display: inline-flex; align-items: center; gap: 4px; padding: 6px 12px; border-radius: 6px; background-color: rgba(128, 128, 128, 0.1); color: rgba(128, 128, 128, 0.8); font-size: 12px; font-weight: 600;">'.
                'Activation Locked'.
                '</span>'.
                '</div>';
        }

        return new HtmlString(
            '<div style="background-color: rgba(128, 128, 128, 0.03); border: 1px solid rgba(128, 128, 128, 0.2); border-radius: 0.75rem; padding: 1.25rem; margin-top: 0.75rem; margin-bottom: 0.5rem; font-family: ui-sans-serif, system-ui, sans-serif; color: inherit;">'.
                '<!-- Property Info Header -->'.
                '<div style="display: flex; justify-content: space-between; align-items: flex-start; width: 100%; border-bottom: 1px solid rgba(128, 128, 128, 0.15); padding-bottom: 0.85rem; margin-bottom: 1rem;">'.
                '<div>'.
                '<div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">'.
                '<span style="font-size: 1.15rem; font-weight: 800; color: inherit;">'.e($propertyName).'</span>'.
                ($propertyCode ? '<span style="display: inline-flex; align-items: center; font-family: monospace; font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 4px; background-color: rgba(37, 99, 235, 0.15); color: #2563eb;">'.e($propertyCode).'</span>' : '').
                ($record->is_renewal && $record->previousAgreement ? '<a href="'.e(TenancyAgreementResource::getUrl('edit', ['record' => $record->previousAgreement])).'" style="display: inline-flex; align-items: center; gap: 4px; padding: 2px 8px; border-radius: 4px; background-color: rgba(124, 58, 237, 0.12); color: #7c3aed; font-size: 11px; font-weight: 700; text-decoration: none;" title="View Previous Agreement">🔄 Renewed from '.e($record->previousAgreement->code).'</a>' : '').
                '<a href="'.e($propertyUrl).'" target="_blank" style="display: inline-flex; align-items: center; gap: 4px; padding: 2px 8px; border-radius: 4px; background-color: rgba(37, 99, 235, 0.1); color: #2563eb; font-size: 12px; font-weight: 600; text-decoration: none;" title="View Property Profile">'.
                'View Property Profile &rarr;'.
                '</a>'.
                '</div>'.
                '<div style="font-size: 13px; color: rgba(128, 128, 128, 0.85); margin-top: 5px; display: flex; gap: 12px; flex-wrap: wrap;">'.
                '<span>📍 '.e($address).'</span>'.
                ($bhk ? '<span>🏢 <strong>'.e($bhk).'</strong></span>' : '').
                '<span>👤 Owner: <strong>'.e($ownerName).'</strong>'.$ownerLinkHtml.'</span>'.
                '<span>🔑 Tenant: <strong>'.e($tenantName).'</strong>'.$tenantLinkHtml.'</span>'.
                '</div>'.
                '</div>'.
                '<div style="text-align: right; flex-shrink: 0; margin-left: 1rem;">'.
                '<div style="display: flex; align-items: center; gap: 8px; justify-content: flex-end;">'.
                '<span style="font-size: 1.25rem; font-weight: 900; color: '.$progressColor.';">'.$progress.'%</span>'.
                '<span style="padding: 3px 10px; font-size: 0.75rem; border-radius: 9999px; font-weight: 700; background-color: '.$badgeBg.'; color: #ffffff; text-transform: uppercase;">'.e($status).'</span>'.
                '</div>'.
                '<div style="font-size: 11px; color: rgba(128, 128, 128, 0.7); margin-top: 2px;">Agreement Code: <strong>'.e($code).'</strong></div>'.
                '</div>'.
                '</div>'.

                '<!-- Progress Bar -->'.
                '<div style="width: 100%; border-radius: 9999px; height: 0.75rem; margin-bottom: 1.25rem; overflow: hidden; background-color: rgba(128, 128, 128, 0.15);">'.
                '<div style="height: 100%; border-radius: 9999px; transition: all 500ms ease-out; width: '.$progress.'%; background-color: '.$progressColor.';"></div>'.
                '</div>'.

                '<!-- Steps Grid -->'.
                '<div style="display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 0.75rem;">'.
                $card1.$card2.$card3.$card4.
                '</div>'.

                '<!-- Top Activation Banner -->'.
                $activationBannerHtml.
                '</div>'
        );
    }

    public static function configureOverviewForm(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('1. Move-In Audit Status & Performance')
                    ->description('Verification audit automatically scheduled for this property & tenant move-in.')
                    ->schema([
                        Select::make('audit_id')
                            ->label('Linked Move-In Audit')
                            ->options(function (Get $get) {
                                $propertyId = $get('property_id');
                                if (! $propertyId) {
                                    return Audit::pluck('audit_number', 'id');
                                }

                                return Audit::where('property_id', $propertyId)
                                    ->get()
                                    ->mapWithKeys(fn ($audit) => [
                                        $audit->id => "{$audit->audit_number} ({$audit->audit_type->getLabel()} - {$audit->status->value})",
                                    ]);
                            })
                            ->disabled()
                            ->dehydrated()
                            ->helperText('Move-In Audit auto-triggered for this property & tenant.'),

                        Placeholder::make('audit_action_card')
                            ->label('Move-In Audit Status & Perform Link')
                            ->content(function ($record) {
                                if (! $record || ! $record->audit) {
                                    return new HtmlString('<div style="padding: 16px; background-color: #f1f5f9; border-radius: 6px; color: #475569;">No Move-In Audit linked.</div>');
                                }
                                $audit = $record->audit;

                                $hasInspector = ! empty($audit->inspector_id);

                                if ($hasInspector) {
                                    try {
                                        $targetUrl = AuditResource::getUrl('inspect', ['record' => $audit]);
                                    } catch (\Throwable $e) {
                                        $targetUrl = url("/operations/audits/{$audit->id}/inspect");
                                    }
                                    $buttonLabel = 'Perform / Inspect Move-In Audit &rarr;';
                                    $buttonBg = '#2563eb';
                                    $inspectorText = 'Inspector: <strong>'.e($audit->inspector?->name ?? 'Assigned').'</strong>';
                                } else {
                                    try {
                                        $targetUrl = AuditResource::getUrl('edit', ['record' => $audit]);
                                    } catch (\Throwable $e) {
                                        $targetUrl = url("/operations/audits/{$audit->id}/edit");
                                    }
                                    $buttonLabel = 'Assign Inspector (Required) &rarr;';
                                    $buttonBg = '#d97706';
                                    $inspectorText = '<span style="color: #dc2626; font-weight: 600;">⚠️ Inspector Unassigned</span>';
                                }

                                return new HtmlString(
                                    '<div style="background-color: rgba(128, 128, 128, 0.03); border: 1px solid rgba(128, 128, 128, 0.2); padding: 16px; border-radius: 8px; display: flex; align-items: center; justify-content: space-between; color: inherit; flex-wrap: wrap; gap: 12px;">'.
                                        '<div>'.
                                        '<div style="font-weight: 700; font-size: 15px; color: inherit;">Move-In Audit: '.e($audit->audit_number).'</div>'.
                                        '<div style="font-size: 13px; color: rgba(128, 128, 128, 0.85); margin-top: 4px;">Type: <strong>'.e($audit->audit_type->getLabel()).'</strong> | Status: <span style="padding: 2px 8px; font-size: 11px; border-radius: 4px; font-weight: 600; background-color: #dbeafe; color: #1e40af; text-transform: uppercase;">'.e($audit->status->value).'</span></div>'.
                                        (function () use ($audit, $inspectorText) {
                                            $auditTenantParty = $audit->tenant;
                                            $auditTenantUrl = $auditTenantParty ? PartyResource::getUrl('edit', ['record' => $auditTenantParty]) : null;
                                            $auditTenantLinkHtml = $auditTenantUrl
                                                ? ' <a href="'.e($auditTenantUrl).'" target="_blank" title="View Tenant Party Profile" style="display: inline-flex; align-items: center; color: #2563eb; text-decoration: none; margin-left: 2px;"><svg style="width: 13px; height: 13px; display: inline-block; vertical-align: text-bottom;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg></a>'
                                                : '';

                                            return '<div style="font-size: 12px; color: rgba(128, 128, 128, 0.7); margin-top: 4px;">Linked Tenant: <strong>'.e($audit->tenant?->display_name ?? 'Primary Tenant').'</strong>'.$auditTenantLinkHtml.' &bull; '.$inspectorText.'</div>';
                                        })().
                                        '</div>'.
                                        '<a href="'.e($targetUrl).'" target="_blank" style="display: inline-flex; align-items: center; padding: 8px 16px; background-color: '.$buttonBg.'; color: #ffffff; font-weight: 600; font-size: 13px; border-radius: 6px; text-decoration: none; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">'.
                                        $buttonLabel.
                                        '</a>'.
                                        '</div>'
                                );
                            })
                            ->columnSpanFull(),
                    ]),

                Section::make('Renewal Lineage & History')
                    ->description('Linked prior and subsequent agreement terms for this lease lifecycle.')
                    ->visible(fn ($record) => $record && ($record->is_renewal || $record->renewals()->exists()))
                    ->schema([
                        Placeholder::make('renewal_lineage_card')
                            ->label('Agreement Lineage')
                            ->content(function ($record) {
                                if (! $record) {
                                    return '';
                                }

                                $html = '<div style="display: flex; flex-direction: column; gap: 8px;">';

                                if ($record->is_renewal && $record->previousAgreement) {
                                    $prev = $record->previousAgreement;
                                    $prevUrl = TenancyAgreementResource::getUrl('edit', ['record' => $prev]);
                                    $html .= '<div style="padding: 10px 14px; background-color: rgba(124, 58, 237, 0.06); border: 1px solid rgba(124, 58, 237, 0.2); border-radius: 6px; font-size: 13px;">'.
                                        '🔄 <strong>Renewed from Previous Agreement:</strong> <a href="'.e($prevUrl).'" style="color: #7c3aed; font-weight: 700; text-decoration: underline;">'.e($prev->code).'</a> '.
                                        '('.($prev->start_date ? $prev->start_date->format('d M Y') : '').' &ndash; '.($prev->end_date ? $prev->end_date->format('d M Y') : '').') &bull; Status: <span style="font-weight: 600; text-transform: uppercase;">'.e($prev->status).'</span>'.
                                    '</div>';
                                }

                                if ($record->renewals()->exists()) {
                                    foreach ($record->renewals as $ren) {
                                        $renUrl = TenancyAgreementResource::getUrl('edit', ['record' => $ren]);
                                        $html .= '<div style="padding: 10px 14px; background-color: rgba(16, 185, 129, 0.06); border: 1px solid rgba(16, 185, 129, 0.2); border-radius: 6px; font-size: 13px;">'.
                                            '⏩ <strong>Subsequent Renewal:</strong> <a href="'.e($renUrl).'" style="color: #059669; font-weight: 700; text-decoration: underline;">'.e($ren->code).'</a> '.
                                            '('.($ren->start_date ? $ren->start_date->format('d M Y') : '').' &ndash; '.($ren->end_date ? $ren->end_date->format('d M Y') : '').') &bull; Status: <span style="font-weight: 600; text-transform: uppercase;">'.e($ren->status).'</span>'.
                                        '</div>';
                                    }
                                }

                                $html .= '</div>';

                                return new HtmlString($html);
                            })
                            ->columnSpanFull(),
                    ]),

                Section::make('2. Agreement Documentation Fee & Invoicing')
                    ->description('One-time administrative documentation & legal execution fee invoiced to tenant.')
                    ->schema([
                        Placeholder::make('documentation_invoicing_card')
                            ->label('Documentation Fee Invoicing Status')
                            ->content(function ($record) {
                                if (! $record) {
                                    return '';
                                }

                                $charge = (float) ($record->documentation_charge ?? ($record->is_renewal ? 1000.00 : 1500.00));
                                $formattedCharge = '₹'.number_format($charge, 2);

                                $invoice = $record->documentationInvoice;

                                if ($invoice) {
                                    $invoiceStatus = $invoice->status instanceof InvoiceStatus
                                        ? $invoice->status->value
                                        : (string) ($invoice->status ?? 'posted');

                                    $statusColor = match ($invoiceStatus) {
                                        'paid' => '#10b981',
                                        'sent', 'posted' => '#3b82f6',
                                        'draft' => '#f59e0b',
                                        default => '#6b7280',
                                    };

                                    $statusBg = match ($invoiceStatus) {
                                        'paid' => '#d1fae5',
                                        'sent', 'posted' => '#dbeafe',
                                        'draft' => '#fef3c7',
                                        default => '#f3f4f6',
                                    };

                                    return new HtmlString(
                                        '<div style="background-color: rgba(128, 128, 128, 0.03); border: 1px solid rgba(128, 128, 128, 0.2); padding: 16px; border-radius: 8px; display: flex; align-items: center; justify-content: space-between; color: inherit; flex-wrap: wrap; gap: 12px;">'.
                                            '<div>'.
                                                '<div style="font-weight: 700; font-size: 15px; color: inherit;">Documentation Fee Invoice: '.e($invoice->invoice_number).'</div>'.
                                                '<div style="font-size: 13px; color: rgba(128, 128, 128, 0.85); margin-top: 4px;">Amount: <strong>'.$formattedCharge.'</strong> | Status: <span style="padding: 2px 8px; font-size: 11px; border-radius: 4px; font-weight: 600; background-color: '.$statusBg.'; color: '.$statusColor.'; text-transform: uppercase;">'.e($invoiceStatus).'</span> | Balance Due: <strong>₹'.number_format($invoice->balance_due ?? 0, 2).'</strong></div>'.
                                                '<div style="font-size: 12px; color: rgba(128, 128, 128, 0.7); margin-top: 4px;">Posted to General Ledger (DR: Tenant Accounts Receivable &bull; CR: Agreement Documentation Fee Income)</div>'.
                                            '</div>'.
                                            '<a href="'.url("/accounting/sales/invoices/{$invoice->id}").'" target="_blank" style="display: inline-flex; align-items: center; padding: 8px 16px; background-color: #2563eb; color: #ffffff; font-weight: 600; font-size: 13px; border-radius: 6px; text-decoration: none; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">'.
                                                'View Sales Invoice &rarr;'.
                                            '</a>'.
                                        '</div>'
                                    );
                                }

                                return new HtmlString(
                                    '<div style="background-color: rgba(245, 158, 11, 0.05); border: 1px solid rgba(245, 158, 11, 0.25); padding: 16px; border-radius: 8px; display: flex; align-items: center; justify-content: space-between; color: inherit; flex-wrap: wrap; gap: 12px;">'.
                                        '<div>'.
                                            '<div style="font-weight: 700; font-size: 15px; color: #b45309;">Documentation Charge: '.$formattedCharge.' (Uninvoiced)</div>'.
                                            '<div style="font-size: 13px; color: rgba(128, 128, 128, 0.85); margin-top: 4px;">Fee is automatically invoiced and posted upon agreement activation, or you can generate it now.</div>'.
                                        '</div>'.
                                        '<button type="button" wire:click="mountAction(\'generateDocInvoiceHeader\')" style="display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; background-color: #0284c7; color: #ffffff; font-weight: 600; font-size: 13px; border-radius: 6px; border: none; cursor: pointer; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">'.
                                            '<svg style="width: 15px; height: 15px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>'.
                                            'Generate Documentation Invoice Now'.
                                        '</button>'.
                                    '</div>'
                                );
                            })
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function configureTermsForm(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Commercial Terms & Lease Period')
                    ->description('Set primary rental rates, deposits, lease commencement dates, and utility identifiers.')
                    ->schema([
                        Select::make('property_id')
                            ->relationship('property', 'building_name')
                            ->getOptionLabelFromRecordUsing(function (Property $record) {
                                $name = $record->building_name ?? $record->address_line_1 ?? 'Property #'.$record->id;

                                return $record->code ? "{$record->code} - {$name}" : $name;
                            })
                            ->disabled()
                            ->dehydrated()
                            ->columnSpan(['default' => 1, 'sm' => 2]),

                        DatePicker::make('start_date')
                            ->label('Agreement Start Date')
                            ->required()
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Get $get, Set $set, ?string $state) {
                                if ($state) {
                                    if (! $get('end_date')) {
                                        try {
                                            $startDate = Carbon::parse($state);
                                            $set('end_date', $startDate->copy()->addMonths(11)->subDay()->format('Y-m-d'));
                                        } catch (\Throwable $e) {
                                            // ignore
                                        }
                                    }
                                    $rent = $get('rent_amount');
                                    if ($rent) {
                                        $set('first_month_rent', static::calculateProRatedFirstMonthRent($state, $rent));
                                    }
                                }
                            })
                            ->columnSpan(1),

                        DatePicker::make('end_date')
                            ->label('Agreement End Date')
                            ->required()
                            ->columnSpan(1),

                        TextInput::make('rent_amount')
                            ->label('Monthly License Fee (Rent)')
                            ->numeric()
                            ->prefix('₹')
                            ->required()
                            ->default(function ($record) {
                                if ($record && (float) $record->rent_amount > 0) {
                                    return $record->rent_amount;
                                }

                                return $record?->property?->pricingVersions()?->latest('effective_from')?->first()?->rent;
                            })
                            ->helperText('Auto-fetched from Property Pricing Model.')
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Get $get, Set $set, ?string $state) {
                                if ($state && ! $get('security_deposit')) {
                                    $set('security_deposit', (float) $state * 2);
                                }
                                $startDate = $get('start_date');
                                if ($startDate && $state) {
                                    $set('first_month_rent', static::calculateProRatedFirstMonthRent($startDate, $state));
                                }
                            })
                            ->columnSpan(1),

                        TextInput::make('security_deposit')
                            ->label('Security Deposit')
                            ->numeric()
                            ->prefix('₹')
                            ->required()
                            ->default(function ($record) {
                                if ($record && (float) $record->security_deposit > 0) {
                                    return $record->security_deposit;
                                }
                                $pricing = $record?->property?->pricingVersions()?->latest('effective_from')?->first();

                                return $pricing?->security_deposit ?? ($pricing?->rent ? $pricing->rent * 2 : null);
                            })
                            ->helperText('Defaults to 2 months rent')
                            ->columnSpan(1),

                        TextInput::make('booking_amount')
                            ->label('Booking Token Amount')
                            ->numeric()
                            ->prefix('₹')
                            ->required()
                            ->default(function ($record) {
                                if ($record && ! is_null($record->booking_amount)) {
                                    return $record->booking_amount;
                                }

                                return $record?->property?->pricingVersions()?->latest('effective_from')?->first()?->booking_amount ?? 0.00;
                            })
                            ->helperText('Token collected at initiation')
                            ->columnSpan(1),

                        TextInput::make('first_month_rent')
                            ->label('First Month Rent (Prorated)')
                            ->numeric()
                            ->prefix('₹')
                            ->required()
                            ->live(onBlur: true)
                            ->default(function ($record, Get $get) {
                                if ($record && ! is_null($record->first_month_rent)) {
                                    return $record->first_month_rent;
                                }
                                $startDate = $get('start_date') ?? $record?->start_date?->format('Y-m-d');
                                $rent = $get('rent_amount') ?? $record?->rent_amount;

                                return static::calculateProRatedFirstMonthRent($startDate, $rent);
                            })
                            ->helperText('Auto-calculated for mid-month move-in')
                            ->columnSpan(1),

                        TextInput::make('lock_in_period_months')
                            ->label('Lock-in Period (Months)')
                            ->numeric()
                            ->required()
                            ->default(6)
                            ->columnSpan(1),

                        TextInput::make('notice_period_days')
                            ->label('Notice Period (Days)')
                            ->numeric()
                            ->required()
                            ->default(30)
                            ->columnSpan(1),

                        TextInput::make('documentation_charge')
                            ->label('Documentation & Legal Fee')
                            ->numeric()
                            ->prefix('₹')
                            ->required()
                            ->default(fn ($record) => $record?->is_renewal ? 1000.00 : 1500.00)
                            ->helperText('Standard administrative documentation fee (₹1,500 fresh / ₹1,000 renewal)')
                            ->columnSpan(1),

                        Select::make('electricity_provider_id')
                            ->label('Electricity Provider')
                            ->options(function () {
                                return UtilityProvider::whereHas('utilityType', function ($query) {
                                    $query->where('slug', 'electricity');
                                })->pluck('name', 'id');
                            })
                            ->searchable()
                            ->required()
                            ->columnSpan(1),

                        TextInput::make('apdcl_consumer_id')
                            ->label('Electricity Connection / APDCL No.')
                            ->placeholder('e.g. 100234567')
                            ->required()
                            ->columnSpan(1),

                        Section::make('Mid-Month Proration Basis & Calculation Proof')
                            ->description(fn (Get $get, ?TenancyAgreement $record) => static::isFormStateProrated($get, $record)
                                ? 'Mandatory for mid-month or custom rent: Provide justification remarks and calculation proof.'
                                : 'Optional proration justification and approval screenshots')
                            ->collapsible()
                            ->collapsed(fn (Get $get, ?TenancyAgreement $record) => ! static::isFormStateProrated($get, $record))
                            ->schema([
                                Textarea::make('first_month_rent_notes')
                                    ->label('Manual Adjustment Remarks / Proration Basis')
                                    ->placeholder('Specify reason for custom/prorated first-month rent if applicable...')
                                    ->rows(2)
                                    ->required(fn (Get $get, ?TenancyAgreement $record) => static::isFormStateProrated($get, $record))
                                    ->helperText(fn (Get $get, ?TenancyAgreement $record) => static::isFormStateProrated($get, $record)
                                        ? 'Required: Explain calculation basis for prorated rent.'
                                        : 'Optional notes on rent calculation.')
                                    ->columnSpan(['default' => 1, 'sm' => 2]),

                                SpatieMediaLibraryFileUpload::make('first_month_rent_proof')
                                    ->label('Proration Basis & Adjustment Proof')
                                    ->collection('first_month_rent_proof')
                                    ->multiple()
                                    ->downloadable()
                                    ->openable()
                                    ->acceptedFileTypes(['image/*', 'application/pdf'])
                                    ->required(fn (Get $get, ?TenancyAgreement $record) => static::isFormStateProrated($get, $record))
                                    ->helperText(fn (Get $get, ?TenancyAgreement $record) => static::isFormStateProrated($get, $record)
                                        ? 'Required: Upload chat screenshots or calculation proof.'
                                        : 'Optional chat screenshots or approval notes')
                                    ->columnSpan(['default' => 1, 'sm' => 2]),
                            ])
                            ->columns(['default' => 1, 'sm' => 2, 'lg' => 4])
                            ->columnSpanFull(),

                        Textarea::make('special_terms')
                            ->label('Special Terms & Conditions')
                            ->placeholder('Optional special conditions to include in Leave & License agreement')
                            ->rows(2)
                            ->columnSpanFull(),
                    ])
                    ->columns(['default' => 1, 'sm' => 2, 'lg' => 4]),

                Section::make('Banking & Settlement Accounts')
                    ->description('Licensor collection details (Annexure I) and Tenant security deposit refund account (Annexure II).')
                    ->schema([
                        Section::make('Annexure I – Collection Account Details (Licensor / Dwelly)')
                            ->description('Pre-fetched from verified MOU for rent & deposit collection (View Only)')
                            ->schema([
                                Placeholder::make('annexure_i_beneficiary_name')
                                    ->label('Beneficiary Name')
                                    ->content(fn ($record, Get $get) => static::getAnnexureIBankDetails($record, $get('property_id'))['beneficiary_name']),

                                Placeholder::make('annexure_i_bank_name')
                                    ->label('Bank Name')
                                    ->content(fn ($record, Get $get) => static::getAnnexureIBankDetails($record, $get('property_id'))['bank_name']),

                                Placeholder::make('annexure_i_bank_address')
                                    ->label('Branch Address')
                                    ->content(fn ($record, Get $get) => static::getAnnexureIBankDetails($record, $get('property_id'))['bank_address']),

                                Placeholder::make('annexure_i_account_number')
                                    ->label('Bank Account No')
                                    ->content(fn ($record, Get $get) => static::getAnnexureIBankDetails($record, $get('property_id'))['account_number']),

                                Placeholder::make('annexure_i_account_type')
                                    ->label('Account Type')
                                    ->content(fn ($record, Get $get) => static::getAnnexureIBankDetails($record, $get('property_id'))['account_type']),

                                Placeholder::make('annexure_i_ifsc_code')
                                    ->label('IFSC Code')
                                    ->content(fn ($record, Get $get) => static::getAnnexureIBankDetails($record, $get('property_id'))['ifsc_code']),
                            ])
                            ->columns(['default' => 1, 'sm' => 2, 'lg' => 3])
                            ->columnSpanFull(),

                        Section::make('Annexure II – Tenant Security Deposit Refund Account Details')
                            ->description('Captured for automatic refund of security deposit at move-out (Mandatory)')
                            ->schema([
                                TextInput::make('tenant_bank_details.account_holder_name')
                                    ->label('Beneficiary Name')
                                    ->required()
                                    ->columnSpan(['default' => 1, 'sm' => 2, 'lg' => 1]),

                                TextInput::make('tenant_bank_details.bank_name')
                                    ->label('Bank Name')
                                    ->required()
                                    ->columnSpan(['default' => 1, 'sm' => 2, 'lg' => 1]),

                                TextInput::make('tenant_bank_details.bank_address')
                                    ->label('Branch Address')
                                    ->required()
                                    ->columnSpan(['default' => 1, 'sm' => 2, 'lg' => 2]),

                                TextInput::make('tenant_bank_details.account_number')
                                    ->label('Account Number')
                                    ->required()
                                    ->columnSpan(1),

                                Select::make('tenant_bank_details.account_type')
                                    ->label('Account Type')
                                    ->options(['Saving' => 'Savings Account', 'Current' => 'Current Account'])
                                    ->default('Saving')
                                    ->required()
                                    ->columnSpan(1),

                                TextInput::make('tenant_bank_details.ifsc_code')
                                    ->label('IFSC Code')
                                    ->required()
                                    ->columnSpan(1),

                                TextInput::make('tenant_bank_details.pan_number')
                                    ->label('PAN Number')
                                    ->required()
                                    ->columnSpan(1),
                            ])
                            ->columns(['default' => 1, 'sm' => 2, 'lg' => 4])
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function configureTenantKycAndOccupantsForm(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Tabs::make('TenantsAndKycTabs')
                    ->tabs([
                        Tabs\Tab::make('Primary Tenant KYC')
                            ->icon('heroicon-m-user-circle')
                            ->schema([
                                Section::make('👤 Primary Tenant Personal Profile')
                                    ->description('View and update primary tenant personal information, contact numbers, and permanent address.')
                                    ->headerActions([
                                        Action::make('viewPartyProfile')
                                            ->label('View Full Party Profile')
                                            ->icon('heroicon-m-arrow-top-right-on-square')
                                            ->color('gray')
                                            ->size('sm')
                                            ->url(function (?TenancyAgreement $record) {
                                                $party = $record?->primaryTenant?->party ?? $record?->tenants()->first();
                                                return $party ? PartyResource::getUrl('edit', ['record' => $party]) : null;
                                            })
                                            ->openUrlInNewTab()
                                            ->visible(fn (?TenancyAgreement $record) => (bool) ($record?->primaryTenant?->party ?? $record?->tenants()->first())),
                                    ])
                                    ->schema([
                                        Grid::make(['default' => 1, 'sm' => 2, 'lg' => 3])
                                            ->schema([
                                                TextInput::make('new_tenant.display_name')
                                                    ->label('Tenant Full Name')
                                                    ->required()
                                                    ->placeholder('e.g. Rahul Sharma')
                                                    ->formatStateUsing(fn (?TenancyAgreement $record, $state) => $state ?: ($record?->primaryTenant?->party?->display_name ?? $record?->tenants()->first()?->display_name))
                                                    ->default(fn (?TenancyAgreement $record) => $record?->primaryTenant?->party?->display_name ?? $record?->tenants()->first()?->display_name),

                                                TextInput::make('new_tenant.phone')
                                                    ->label('Phone Number')
                                                    ->tel()
                                                    ->required()
                                                    ->placeholder('e.g. 9876543210')
                                                    ->formatStateUsing(fn (?TenancyAgreement $record, $state) => $state ?: ($record?->primaryTenant?->party?->phone ?? $record?->tenants()->first()?->phone))
                                                    ->default(fn (?TenancyAgreement $record) => $record?->primaryTenant?->party?->phone ?? $record?->tenants()->first()?->phone),

                                                TextInput::make('new_tenant.email')
                                                    ->label('Email Address')
                                                    ->email()
                                                    ->placeholder('e.g. rahul@example.com')
                                                    ->formatStateUsing(fn (?TenancyAgreement $record, $state) => $state ?: ($record?->primaryTenant?->party?->email ?? $record?->tenants()->first()?->email))
                                                    ->default(fn (?TenancyAgreement $record) => $record?->primaryTenant?->party?->email ?? $record?->tenants()->first()?->email),

                                                TextInput::make('new_tenant.parent_name')
                                                    ->label("Father's / Care-Of Name")
                                                    ->placeholder('e.g. S/o Late Rajesh Sharma')
                                                    ->formatStateUsing(fn (?TenancyAgreement $record, $state) => $state ?: ($record?->primaryTenant?->party?->individual?->parent_name ?? $record?->tenants()->first()?->individual?->parent_name))
                                                    ->default(fn (?TenancyAgreement $record) => $record?->primaryTenant?->party?->individual?->parent_name ?? $record?->tenants()->first()?->individual?->parent_name),

                                                TextInput::make('new_tenant.address_line_1')
                                                    ->label('Permanent Residence Address')
                                                    ->placeholder('Permanent residence address')
                                                    ->formatStateUsing(function (?TenancyAgreement $record, $state) {
                                                        if ($state) {
                                                            return $state;
                                                        }
                                                        $party = $record?->primaryTenant?->party ?? $record?->tenants()->first();
                                                        return $party?->addresses()->where('is_primary', true)->first()?->address_line_1 ?? $party?->addresses?->first()?->address_line_1;
                                                    })
                                                    ->default(function (?TenancyAgreement $record) {
                                                        $party = $record?->primaryTenant?->party ?? $record?->tenants()->first();
                                                        return $party?->addresses()->where('is_primary', true)->first()?->address_line_1 ?? $party?->addresses?->first()?->address_line_1;
                                                    })
                                                    ->columnSpan(['default' => 1, 'sm' => 2, 'lg' => 2]),
                                            ]),
                                    ])
                                    ->columnSpanFull(),

                                Section::make('📁 Tenant KYC & Verification Documents')
                                    ->description('Attach official identity, photo, and banking verification documents for the primary tenant.')
                                    ->headerActions([
                                        static::getTenantKycUploadAction(),
                                    ])
                                    ->schema([
                                        Grid::make(['default' => 1, 'md' => 2])
                                            ->schema(static::getDynamicTenantKycFields()),

                                        SpatieMediaLibraryFileUpload::make('kyc_documents')
                                            ->label('Additional Tenant KYC Attachments')
                                            ->collection('kyc_documents')
                                            ->multiple()
                                            ->reorderable()
                                            ->downloadable()
                                            ->openable()
                                            ->columnSpanFull()
                                            ->visible(fn (?TenancyAgreement $record, Get $get) => ($record && $record->hasMedia('kyc_documents')) || ! empty($get('kyc_documents'))),
                                    ])
                                    ->columnSpanFull(),
                            ]),

                        Tabs\Tab::make('Secondary Tenants & Family Members')
                            ->icon('heroicon-m-users')
                            ->badge(fn (?TenancyAgreement $record) => count($record?->secondary_tenants ?? []) ?: null)
                            ->schema([
                                Section::make('Secondary Tenants & Family Members')
                                    ->description('Manage family members or co-tenants living with the primary tenant, along with identity details and KYC documents.')
                                    ->schema([
                                        Repeater::make('secondary_tenants')
                                            ->label('Secondary Tenants / Family Members')
                                            ->default([])
                                            ->addActionLabel('Add Family Member / Co-Tenant')
                                            ->collapsible()
                                            ->collapsed(fn (array $state): bool => ! empty($state['name']))
                                            ->itemLabel(fn (array $state): ?string => ! empty($state['name'])
                                                ? ($state['name'].(! empty($state['relationship']) ? ' • '.$state['relationship'] : ''))
                                                : 'New Family Member'
                                            )
                                            ->deleteAction(fn ($action) => $action->label('Remove Member')->icon('heroicon-o-trash'))
                                            ->reorderable(false)
                                            ->schema([
                                                Section::make('👤 Personal Information')
                                                    ->compact()
                                                    ->schema([
                                                        Grid::make(4)
                                                            ->schema([
                                                                TextInput::make('name')
                                                                    ->label('Full Name (Required)')
                                                                    ->required()
                                                                    ->placeholder('e.g. Rahul Sharma'),

                                                                Select::make('relationship')
                                                                    ->label('Relationship (Required)')
                                                                    ->options([
                                                                        'Spouse' => 'Spouse',
                                                                        'Son' => 'Son',
                                                                        'Daughter' => 'Daughter',
                                                                        'Father' => 'Father',
                                                                        'Mother' => 'Mother',
                                                                        'Brother' => 'Brother',
                                                                        'Sister' => 'Sister',
                                                                        'Co-Tenant' => 'Co-Tenant / Roommate',
                                                                        'Relative' => 'Relative',
                                                                        'Other' => 'Other',
                                                                    ])
                                                                    ->required(),

                                                                TextInput::make('phone')
                                                                    ->label('Phone (Optional)')
                                                                    ->tel()
                                                                    ->placeholder('e.g. 9876543210'),

                                                                TextInput::make('email')
                                                                    ->label('Email (Optional)')
                                                                    ->email()
                                                                    ->placeholder('e.g. rahul@example.com'),
                                                            ]),
                                                    ]),

                                                Section::make('📂 KYC & Verification Documents')
                                                    ->description('Attach official identity, photo, and verification documents for this co-tenant.')
                                                    ->headerActions([
                                                        Action::make('uploadSecondaryDocumentModal')
                                                            ->label('Upload Additional Document')
                                                            ->icon('heroicon-m-plus')
                                                            ->color('primary')
                                                            ->size('sm')
                                                            ->modalHeading('Upload Additional Document for Co-Tenant')
                                                            ->modalDescription('Select document type to attach for this family member / co-tenant.')
                                                            ->form([
                                                                Select::make('document_type')
                                                                    ->label('Document Type')
                                                                    ->options(fn () => static::getDocumentTypeOptions('secondary_tenant'))
                                                                    ->required()
                                                                    ->searchable()
                                                                    ->live(),
                                                                TextInput::make('document_number')
                                                                    ->label(function (Get $get) {
                                                                        $code = $get('document_type');
                                                                        if ($code) {
                                                                            $docType = KycDocumentType::where('code', $code)->first();
                                                                            if ($docType && $docType->has_number) {
                                                                                return $docType->number_label ?: "{$docType->name} Number";
                                                                            }
                                                                        }
                                                                        return 'Document Number';
                                                                    })
                                                                    ->placeholder(function (Get $get) {
                                                                        $code = $get('document_type');
                                                                        if ($code) {
                                                                            $docType = KycDocumentType::where('code', $code)->first();
                                                                            return 'Enter '.strtolower($docType?->name ?? 'document').' number...';
                                                                        }
                                                                        return 'Enter document number...';
                                                                    })
                                                                    ->visible(function (Get $get) {
                                                                        $code = $get('document_type');
                                                                        if (! $code) {
                                                                            return false;
                                                                        }
                                                                        $docType = KycDocumentType::where('code', $code)->first();
                                                                        return (bool) ($docType?->has_number);
                                                                    }),
                                                                FileUpload::make('files')
                                                                    ->label('Files (Images / PDF)')
                                                                    ->directory('tenancy-secondary-kyc')
                                                                    ->preserveFilenames()
                                                                    ->required(),
                                                            ])
                                                            ->action(function (array $data, Set $set, Get $get) {
                                                                $docType = $data['document_type'];
                                                                $targetFileField = match ($docType) {
                                                                    'passport_photo' => 'photo_file',
                                                                    'aadhaar' => 'aadhaar_file',
                                                                    'pan' => 'pan_file',
                                                                    'voter_id' => 'voter_id_file',
                                                                    'other', 'other_kyc' => 'other_kyc_files',
                                                                    default => "{$docType}_file",
                                                                };
                                                                $targetNumberField = match ($docType) {
                                                                    'aadhaar' => 'aadhaar_number',
                                                                    'pan' => 'pan_number',
                                                                    'voter_id' => 'voter_id',
                                                                    default => "{$docType}_number",
                                                                };

                                                                $uploadedFiles = (array) ($data['files'] ?? []);
                                                                if (in_array($docType, ['other', 'other_kyc'])) {
                                                                    $existing = (array) ($get('other_kyc_files') ?? []);
                                                                    $set('other_kyc_files', array_values(array_unique(array_merge($existing, $uploadedFiles))));
                                                                } else {
                                                                    if (! empty($uploadedFiles)) {
                                                                        $set($targetFileField, is_array($uploadedFiles) ? reset($uploadedFiles) : $uploadedFiles);
                                                                    }
                                                                    if (! empty($data['document_number'])) {
                                                                        $set($targetNumberField, $data['document_number']);
                                                                    }
                                                                }

                                                                Notification::make()
                                                                    ->title('Document Attached')
                                                                    ->body('File and details added to this secondary tenant.')
                                                                    ->success()
                                                                    ->send();
                                                            }),
                                                    ])
                                                    ->schema([
                                                        Grid::make(['default' => 1, 'md' => 2])
                                                            ->schema(static::getDynamicSecondaryTenantKycFields()),

                                                        FileUpload::make('other_kyc_files')
                                                            ->label('Additional Supporting Documents / Other Attachments')
                                                            ->helperText('Other supporting documents (Police Verification, Relationship Proof, etc.)')
                                                            ->directory('tenancy-secondary-kyc')
                                                            ->multiple()
                                                            ->reorderable()
                                                            ->downloadable()
                                                            ->openable()
                                                            ->columnSpanFull(),
                                                    ]),
                                            ])
                                            ->columnSpanFull(),
                                    ]),
                            ]),
                    ]),
            ]);
    }

    public static function configureSecondaryTenantsForm(Schema $schema): Schema
    {
        return static::configureTenantKycAndOccupantsForm($schema);
    }

    public static function configureTenantKycForm(Schema $schema): Schema
    {
        return static::configureTenantKycAndOccupantsForm($schema);
    }

    public static function configureDocumentsForm(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Draft Document Management & Actions')
                    ->description('Compile Leave & License Agreement PDF and Word (.docx) drafts with Annexures I, II, and III (pulled automatically from Move-In Audit).')
                    ->schema([
                        Placeholder::make('draft_workflow_hub')
                            ->hiddenLabel()
                            ->columnSpanFull()
                            ->content(fn (?TenancyAgreement $record) => view('tenancy-agreements.draft-workflow-hub', [
                                'record' => $record,
                            ])),

                        Placeholder::make('draft_version_history')
                            ->label('Document History & Versions')
                            ->columnSpanFull()
                            ->content(fn (?TenancyAgreement $record) => view('tenancy-agreements.version-history', [
                                'record' => $record,
                            ])),
                    ]),

                Section::make('Executed & Signed Agreement')
                    ->description('Upload signed Leave & License agreement with tenant and owner signatures (Mandatory).')
                    ->schema([
                        DatePicker::make('signed_at')
                            ->label('Date Executed / Signed')
                            ->required(),

                        Toggle::make('signed_by_tenant')
                            ->label('Agreement Executed & Signed by Tenant and Owner')
                            ->required()
                            ->default(false)
                            ->live(),

                        SpatieMediaLibraryFileUpload::make('signed_agreement')
                            ->label('Executed Leave & License Agreement (PDF with E-Stamp & Signatures)')
                            ->collection('signed_agreement')
                            ->acceptedFileTypes(['application/pdf'])
                            ->required()
                            ->downloadable()
                            ->openable()
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }

    public static function configureActivationForm(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Key Handover Verification')
                    ->description('Confirm handover of keys, access badges, and move-in meter readings (Mandatory).')
                    ->schema([
                        Toggle::make('keys_handed_over')
                            ->label('Keys Handed Over to Tenant')
                            ->required()
                            ->default(false)
                            ->live(),

                        DatePicker::make('keys_handed_over_at')
                            ->label('Key Handover Date')
                            ->required(),

                        Textarea::make('key_handover_notes')
                            ->label('Key Handover Notes & Instructions')
                            ->placeholder('e.g. Handed over 2 main door keys, 2 bedroom keys, 1 balcony key, 1 society gate access badge.')
                            ->columnSpanFull(),

                        SpatieMediaLibraryFileUpload::make('key_handover_attachments')
                            ->label('Key Handover Photo / Acknowledgement Receipt')
                            ->collection('key_handover_attachments')
                            ->multiple()
                            ->downloadable()
                            ->openable()
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }

    public static function canActivateTenancy(?TenancyAgreement $record): bool
    {
        return empty(static::getPendingActivationRequirements($record));
    }

    public static function getPendingActivationRequirements(?TenancyAgreement $record): array
    {
        if (! $record) {
            return ['No tenancy agreement record found.'];
        }

        if ($record->status === 'active') {
            return [];
        }

        $pending = [];

        $audit = $record->audit;
        $auditStatusValue = $audit?->status instanceof AuditStatus
            ? $audit->status->value
            : (string) ($audit?->status ?? '');
        $s1 = (bool) ($audit && in_array($auditStatusValue, ['approved', 'completed']));
        if (! $s1) {
            $pending[] = 'Move-In Audit must be approved or completed.';
        }

        if (! static::areTermsComplete($record)) {
            if (static::isRecordProrated($record) && (empty($record->first_month_rent_notes) || ! $record->hasMedia('first_month_rent_proof'))) {
                $pending[] = 'Agreement terms are incomplete (Mid-month proration remarks and calculation proof are mandatory).';
            } else {
                $pending[] = 'Agreement terms and tenant bank details must be fully filled.';
            }
        }

        $hasSignedPdf = $record->hasMedia('signed_agreement');
        if (! $record->signed_by_tenant || ! $hasSignedPdf || empty($record->signed_at)) {
            if (! $record->signed_by_tenant && ! $hasSignedPdf) {
                $pending[] = 'Agreement must be marked as signed/executed AND executed agreement PDF uploaded.';
            } elseif (! $record->signed_by_tenant) {
                $pending[] = 'Agreement must be marked as signed/executed by tenant and owner.';
            } elseif (! $hasSignedPdf) {
                $pending[] = 'Executed Leave & License Agreement (PDF) must be uploaded.';
            } else {
                $pending[] = 'Agreement execution/signed date must be recorded.';
            }
        }

        if (! $record->keys_handed_over || empty($record->keys_handed_over_at)) {
            if (! $record->keys_handed_over) {
                $pending[] = 'Keys must be marked as handed over to tenant.';
            } else {
                $pending[] = 'Key handover date must be specified.';
            }
        }

        return $pending;
    }

    public static function areTermsComplete(?TenancyAgreement $record): bool
    {
        if (! $record) {
            return false;
        }

        $bankDetails = $record->tenant_bank_details ?? [];

        $baseComplete = (float) ($record->rent_amount ?? 0) > 0
            && ! is_null($record->first_month_rent) && (float) $record->first_month_rent >= 0
            && (float) ($record->security_deposit ?? 0) > 0
            && ! is_null($record->booking_amount) && (float) $record->booking_amount >= 0
            && ! empty($record->start_date)
            && ! empty($record->end_date)
            && ! is_null($record->lock_in_period_months) && $record->lock_in_period_months !== ''
            && ! is_null($record->notice_period_days) && $record->notice_period_days !== ''
            && ! empty($record->electricity_provider_id)
            && ! empty($record->apdcl_consumer_id)
            && ! empty($bankDetails['account_holder_name'] ?? null)
            && ! empty($bankDetails['bank_name'] ?? null)
            && ! empty($bankDetails['bank_address'] ?? null)
            && ! empty($bankDetails['account_number'] ?? null)
            && ! empty($bankDetails['account_type'] ?? null)
            && ! empty($bankDetails['ifsc_code'] ?? null)
            && ! empty($bankDetails['pan_number'] ?? null);

        if (! $baseComplete) {
            return false;
        }

        if (static::isRecordProrated($record)) {
            if (empty($record->first_month_rent_notes) || ! $record->hasMedia('first_month_rent_proof')) {
                return false;
            }
        }

        return true;
    }

    public static function isFormStateProrated(Get $get, ?TenancyAgreement $record = null): bool
    {
        $startDate = $get('start_date') ?? $record?->start_date?->format('Y-m-d');
        $monthlyRent = (float) ($get('rent_amount') ?? $record?->rent_amount ?? 0);
        $firstMonthRent = (float) ($get('first_month_rent') ?? $record?->first_month_rent ?? 0);

        if (! $startDate) {
            return false;
        }

        try {
            if (Carbon::parse($startDate)->day !== 1) {
                return true;
            }
        } catch (\Throwable $e) {
            // ignore
        }

        if ($monthlyRent > 0 && $firstMonthRent > 0 && abs($firstMonthRent - $monthlyRent) > 0.01) {
            return true;
        }

        return false;
    }

    public static function isRecordProrated(?TenancyAgreement $record): bool
    {
        if (! $record || ! $record->start_date) {
            return false;
        }

        try {
            if ($record->start_date->day !== 1) {
                return true;
            }
        } catch (\Throwable $e) {
            // ignore
        }

        $monthlyRent = (float) ($record->rent_amount ?? 0);
        $firstMonthRent = (float) ($record->first_month_rent ?? 0);

        if ($monthlyRent > 0 && $firstMonthRent > 0 && abs($firstMonthRent - $monthlyRent) > 0.01) {
            return true;
        }

        return false;
    }

    public static function calculateProRatedFirstMonthRent(?string $startDate, float|int|string|null $monthlyRent): float
    {
        if (! $startDate || ! (float) $monthlyRent) {
            return (float) ($monthlyRent ?? 0);
        }

        try {
            $carbonStart = Carbon::parse($startDate);
            $dayOfMonth = $carbonStart->day;
            $totalDaysInMonth = $carbonStart->daysInMonth;

            if ($dayOfMonth === 1) {
                return round((float) $monthlyRent, 2);
            }

            $daysActive = $totalDaysInMonth - $dayOfMonth + 1;
            $proRated = ((float) $monthlyRent / $totalDaysInMonth) * $daysActive;

            return round($proRated, 2);
        } catch (\Throwable $e) {
            return round((float) ($monthlyRent ?? 0), 2);
        }
    }

    public static function getAnnexureIBankDetails($record, ?string $propertyId): array
    {
        $property = $record?->property ?? ($propertyId ? Property::find($propertyId) : null);
        $mou = $property?->mous()?->latest()->first();

        if ($mou && ! empty($mou->bank_details)) {
            return [
                'beneficiary_name' => $mou->bank_details['beneficiary_name'] ?? $mou->bank_details['account_holder_name'] ?? 'ASSAM ALAY',
                'bank_name' => $mou->bank_details['bank_name'] ?? 'IndusInd Bank',
                'bank_address' => $mou->bank_details['bank_address'] ?? 'Beltola, Guwahati',
                'account_number' => $mou->bank_details['account_number'] ?? '201025429005',
                'account_type' => $mou->bank_details['account_type'] ?? 'Current',
                'ifsc_code' => $mou->bank_details['ifsc_code'] ?? 'INDB0000662',
            ];
        }

        return [
            'beneficiary_name' => 'ASSAM ALAY',
            'bank_name' => 'IndusInd Bank',
            'bank_address' => 'Beltola, Guwahati',
            'account_number' => '201025429005',
            'account_type' => 'Current',
            'ifsc_code' => 'INDB0000662',
        ];
    }

    public static function getRenewalFormSchema(?TenancyAgreement $record): array
    {
        $prevEndDate = $record?->end_date;
        $defaultStart = $prevEndDate ? Carbon::parse($prevEndDate)->addDay()->format('Y-m-d') : Carbon::today()->format('Y-m-d');
        $defaultEnd = Carbon::parse($defaultStart)->addMonths(11)->subDay()->format('Y-m-d');
        $currentRent = (float) ($record?->rent_amount ?? 0);
        $escalatedRent = round($currentRent * 1.05);

        return [
            Grid::make(2)
                ->schema([
                    DatePicker::make('start_date')
                        ->label('Renewal Start Date')
                        ->default($defaultStart)
                        ->required()
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (Get $get, Set $set, ?string $state) {
                            if ($state) {
                                try {
                                    $set('end_date', Carbon::parse($state)->addMonths(11)->subDay()->format('Y-m-d'));
                                } catch (\Throwable $e) {
                                    // ignore
                                }
                            }
                        }),

                    DatePicker::make('end_date')
                        ->label('Renewal End Date (11-Month Term)')
                        ->default($defaultEnd)
                        ->required(),

                    TextInput::make('rent_amount')
                        ->label('Renewal Monthly Rent (₹)')
                        ->numeric()
                        ->prefix('₹')
                        ->default($escalatedRent ?: $currentRent)
                        ->helperText('Current: ₹'.number_format($currentRent, 2).' | 5% Escalated: ₹'.number_format($escalatedRent, 2))
                        ->required(),

                    TextInput::make('security_deposit')
                        ->label('Security Deposit Carried Forward (₹)')
                        ->numeric()
                        ->prefix('₹')
                        ->default($record?->security_deposit ?? 0)
                        ->helperText('Carried forward from current lease')
                        ->required(),

                    TextInput::make('documentation_charge')
                        ->label('Renewal Documentation Fee (₹)')
                        ->numeric()
                        ->prefix('₹')
                        ->default(1000.00)
                        ->helperText('Standard renewal paperwork fee (₹1,000.00)')
                        ->required(),

                    Textarea::make('renewal_notes')
                        ->label('Renewal Remarks / Escalation Terms')
                        ->placeholder('e.g. 5% annual escalation applied upon mutual agreement')
                        ->default('Renewed for 11-month lease term upon mutual agreement.')
                        ->columnSpanFull(),
                ]),
        ];
    }

    public static function getDocumentTypeOptions(string $context = 'primary_tenant'): array
    {
        try {
            $query = KycDocumentType::query()->where('is_active', true);
            if ($context === 'secondary_tenant') {
                $query->where('tenancy_secondary_requirement', '!=', KycRequirementLevel::DISABLED);
            } else {
                $query->where('tenancy_primary_requirement', '!=', KycRequirementLevel::DISABLED);
            }
            $options = $query->orderBy('sort_order')->pluck('name', 'code')->toArray();
            if (! empty($options)) {
                if ($context === 'secondary_tenant' && ! isset($options['other'])) {
                    $options['other'] = 'Other Supporting Document';
                }

                return $options;
            }
        } catch (\Throwable $e) {
        }

        $options = collect(DocumentType::cases())
            ->mapWithKeys(fn ($case) => [$case->value => $case->getLabel()])
            ->toArray();

        if ($context === 'secondary_tenant' && ! isset($options['other'])) {
            $options['other'] = 'Other Supporting Document';
        }

        return $options;
    }

    public static function getTenantKycUploadAction(): Action
    {
        return Action::make('uploadDocumentModal')
            ->label('Upload Additional Document')
            ->icon('heroicon-m-plus')
            ->color('primary')
            ->size('sm')
            ->modalHeading('Upload Additional Document')
            ->modalDescription('Select document type to attach and configure for the primary tenant.')
            ->form([
                Select::make('document_type')
                    ->label('Document Type')
                    ->options(fn () => static::getDocumentTypeOptions('primary_tenant'))
                    ->required()
                    ->searchable()
                    ->live(),
                TextInput::make('document_number')
                    ->label(function (Get $get) {
                        $code = $get('document_type');
                        if ($code) {
                            $docType = KycDocumentType::where('code', $code)->first();
                            if ($docType && $docType->has_number) {
                                return $docType->number_label ?: "{$docType->name} Number";
                            }
                        }
                        return 'Document Number';
                    })
                    ->placeholder(function (Get $get) {
                        $code = $get('document_type');
                        if ($code) {
                            $docType = KycDocumentType::where('code', $code)->first();
                            return 'Enter '.strtolower($docType?->name ?? 'document').' number...';
                        }
                        return 'Enter document number...';
                    })
                    ->visible(function (Get $get) {
                        $code = $get('document_type');
                        if (! $code) {
                            return false;
                        }
                        $docType = KycDocumentType::where('code', $code)->first();
                        return (bool) ($docType?->has_number);
                    }),
                FileUpload::make('files')
                    ->label('Files (Images / PDF)')
                    ->multiple()
                    ->preserveFilenames()
                    ->required(),
            ])
            ->action(function (array $data, ?TenancyAgreement $record, ?Set $set = null, ?Get $get = null) {
                $docCode = $data['document_type'];
                $collection = match ($docCode) {
                    'passport_photo', 'tenant_photo' => 'tenant_photo',
                    'aadhaar', 'tenant_aadhaar' => 'tenant_aadhaar',
                    'pan', 'tenant_pan' => 'tenant_pan',
                    'cancelled_cheque' => 'cancelled_cheque',
                    'voter_id' => 'tenant_voter_id',
                    'passport' => 'tenant_passport',
                    'driving_license' => 'tenant_driving_license',
                    default => "tenant_{$docCode}",
                };

                $docNumber = $data['document_number'] ?? null;

                if ($record && $record->exists) {
                    foreach ($data['files'] as $path) {
                        $fullPath = Storage::disk(config('filament.default_filesystem_disk'))->path($path);
                        if (! file_exists($fullPath)) {
                            $fullPath = Storage::disk('public')->path($path);
                        }

                        $record->addMedia($fullPath)
                            ->withCustomProperties([
                                'document_type' => $docCode,
                            ])
                            ->toMediaCollection($collection);
                    }

                    if (! empty($docNumber)) {
                        $primaryTenant = $record->primaryTenant?->party;
                        if ($primaryTenant) {
                            $update = [];
                            if ($docCode === 'aadhaar') {
                                $update['aadhaar_number'] = $docNumber;
                            } elseif ($docCode === 'pan') {
                                $update['pan_number'] = $docNumber;
                            } elseif ($docCode === 'voter_id') {
                                $update['voter_id'] = $docNumber;
                            }
                            if (! empty($update)) {
                                $primaryTenant->individual()->updateOrCreate([], $update);
                            }
                        }
                        if ($set) {
                            if ($docCode === 'aadhaar') {
                                $set('new_tenant.aadhaar_number', $docNumber);
                            } elseif ($docCode === 'pan') {
                                $set('new_tenant.pan_number', $docNumber);
                            } elseif ($docCode === 'voter_id') {
                                $set('new_tenant.voter_id', $docNumber);
                            }
                            $set("new_tenant.{$docCode}_number", $docNumber);
                        }
                    }

                    $record->refresh();
                    Notification::make()->title('Document Uploaded Successfully')->success()->send();
                } else {
                    $existing = $get ? ($get($collection) ?? []) : [];
                    if (is_string($existing)) {
                        $existing = [$existing];
                    }
                    $merged = array_values(array_unique(array_merge((array) $existing, (array) $data['files'])));
                    if ($set) {
                        $set($collection, $merged);
                    }

                    if (! empty($docNumber) && $set) {
                        if ($docCode === 'aadhaar') {
                            $set('new_tenant.aadhaar_number', $docNumber);
                        } elseif ($docCode === 'pan') {
                            $set('new_tenant.pan_number', $docNumber);
                        } elseif ($docCode === 'voter_id') {
                            $set('new_tenant.voter_id', $docNumber);
                        }
                        $set("new_tenant.{$docCode}_number", $docNumber);
                        $set("kyc_documents.tenant.{$docCode}.number", $docNumber);
                    }

                    Notification::make()
                        ->title('Document Attached')
                        ->body('File added to form. It will be saved when you submit the Tenancy Agreement.')
                        ->success()
                        ->send();
                }
            });
    }

    public static function getDynamicTenantKycFields(): array
    {
        $fields = [];

        try {
            $docs = KycDocumentType::query()
                ->where('is_active', true)
                ->where('tenancy_primary_requirement', '!=', KycRequirementLevel::DISABLED)
                ->orderBy('sort_order')
                ->get();
        } catch (\Throwable $e) {
            $docs = collect();
        }

        if ($docs->isEmpty()) {
            return [
                Section::make('Passport Photo *')
                    ->icon('heroicon-m-user-circle')
                    ->compact()
                    ->schema([
                        SpatieMediaLibraryFileUpload::make('tenant_photo')
                            ->collection('tenant_photo')
                            ->label('Passport Photo *')
                            ->hintIcon('heroicon-m-information-circle', tooltip: 'Passport-sized photograph')
                            ->image()->avatar()->previewable()
                            ->required(fn (Get $get, ?string $operation) => $operation === 'create' && (bool) $get('create_new_tenant')),
                    ]),
                Section::make('Aadhaar Card *')
                    ->icon('heroicon-m-identification')
                    ->compact()
                    ->schema([
                        TextInput::make('new_tenant.aadhaar_number')
                            ->label('Aadhaar Number *')
                            ->placeholder('12-digit Aadhaar number')
                            ->formatStateUsing(fn (?TenancyAgreement $record, $state) => $state ?: $record?->primaryTenant?->party?->individual?->aadhaar_number)
                            ->default(fn (?TenancyAgreement $record) => $record?->primaryTenant?->party?->individual?->aadhaar_number)
                            ->required(fn (Get $get, ?string $operation) => $operation === 'create' && (bool) $get('create_new_tenant')),
                        SpatieMediaLibraryFileUpload::make('tenant_aadhaar')
                            ->collection('tenant_aadhaar')
                            ->label('Tenant Aadhaar Card *')
                            ->hintIcon('heroicon-m-information-circle', tooltip: 'Front & Back image or PDF')
                            ->openable()->downloadable()->previewable()
                            ->required(fn (Get $get, ?string $operation) => $operation === 'create' && (bool) $get('create_new_tenant')),
                    ]),
                Section::make('PAN Card *')
                    ->icon('heroicon-m-credit-card')
                    ->compact()
                    ->schema([
                        TextInput::make('new_tenant.pan_number')
                            ->label('PAN Number *')
                            ->placeholder('10-character PAN')
                            ->formatStateUsing(fn (?TenancyAgreement $record, $state) => $state ?: $record?->primaryTenant?->party?->individual?->pan_number)
                            ->default(fn (?TenancyAgreement $record) => $record?->primaryTenant?->party?->individual?->pan_number)
                            ->required(fn (Get $get, ?string $operation) => $operation === 'create' && (bool) $get('create_new_tenant')),
                        SpatieMediaLibraryFileUpload::make('tenant_pan')
                            ->collection('tenant_pan')
                            ->label('Tenant PAN Card (Optional)')
                            ->hintIcon('heroicon-m-information-circle', tooltip: 'Clear image or PDF')
                            ->openable()->downloadable()->previewable(),
                    ]),
                Section::make('Cancelled Cheque / Bank Proof')
                    ->icon('heroicon-m-banknotes')
                    ->compact()
                    ->schema([
                        SpatieMediaLibraryFileUpload::make('cancelled_cheque')
                            ->collection('cancelled_cheque')
                            ->label('Cancelled Cheque / Bank Proof (Optional)')
                            ->hintIcon('heroicon-m-information-circle', tooltip: 'Cancelled Cheque or Bank Passbook copy')
                            ->openable()->downloadable()->previewable(),
                    ]),
            ];
        }

        foreach ($docs as $doc) {
            $collectionName = match ($doc->code) {
                'passport_photo' => 'tenant_photo',
                'cancelled_cheque' => 'cancelled_cheque',
                default => "tenant_{$doc->code}",
            };
            $isMandatory = $doc->isTenancyPrimaryMandatory();
            $badge = ($isMandatory || in_array($doc->code, ['aadhaar', 'pan'])) ? ' *' : '';
            $tooltip = $doc->description ?: "Attach {$doc->name} image or PDF";

            $numberFieldStateName = match ($doc->code) {
                'aadhaar' => 'new_tenant.aadhaar_number',
                'pan' => 'new_tenant.pan_number',
                'voter_id' => 'new_tenant.voter_id',
                default => "new_tenant.{$doc->code}_number",
            };

            $isVisible = function (?TenancyAgreement $record, Get $get) use ($doc, $isMandatory, $collectionName, $numberFieldStateName): bool {
                if ($isMandatory || in_array($doc->code, ['aadhaar', 'pan', 'cancelled_cheque', 'passport_photo'])) {
                    return true;
                }

                return (bool) (
                    ($record && $record->hasMedia($collectionName)) ||
                    ! empty($get($collectionName)) ||
                    ! empty($get($numberFieldStateName)) ||
                    ! empty($get("kyc_documents.tenant.{$doc->code}.number")) ||
                    ($record && $doc->code === 'voter_id' && ! empty($record->primaryTenant?->party?->individual?->voter_id))
                );
            };

            $icon = $doc->icon ?: match ($doc->code) {
                'passport_photo' => 'heroicon-m-user-circle',
                'aadhaar' => 'heroicon-m-identification',
                'pan' => 'heroicon-m-credit-card',
                'cancelled_cheque' => 'heroicon-m-banknotes',
                'passport' => 'heroicon-m-globe-alt',
                'driving_license' => 'heroicon-m-truck',
                'voter_id' => 'heroicon-m-identification',
                default => 'heroicon-m-document-text',
            };

            $cardFields = [];

            if ($doc->has_number) {
                $numberLabel = ($doc->number_label ?: "{$doc->name} Number") . $badge;
                $fileLabel = "{$doc->name} File" . ($isMandatory ? ' *' : ' (Optional)');

                $numberField = TextInput::make($numberFieldStateName)
                    ->label($numberLabel)
                    ->placeholder("Enter {$doc->name} number...")
                    ->formatStateUsing(function (?TenancyAgreement $record, $state) use ($doc) {
                        if ($state !== null && $state !== '') {
                            return $state;
                        }
                        if ($doc->code === 'aadhaar') {
                            return $record?->primaryTenant?->party?->individual?->aadhaar_number;
                        }
                        if ($doc->code === 'pan') {
                            return $record?->primaryTenant?->party?->individual?->pan_number;
                        }
                        if ($doc->code === 'voter_id') {
                            return $record?->primaryTenant?->party?->individual?->voter_id;
                        }
                        return null;
                    })
                    ->default(function (?TenancyAgreement $record, Get $get) use ($doc) {
                        if ($doc->code === 'aadhaar') {
                            return $record?->primaryTenant?->party?->individual?->aadhaar_number;
                        }
                        if ($doc->code === 'pan') {
                            return $record?->primaryTenant?->party?->individual?->pan_number;
                        }
                        if ($doc->code === 'voter_id') {
                            return $record?->primaryTenant?->party?->individual?->voter_id;
                        }
                        return null;
                    })
                    ->required(function (Get $get, ?string $operation) use ($isMandatory, $doc) {
                        if ($operation === 'create') {
                            if (! (bool) $get('create_new_tenant')) {
                                return false;
                            }
                            return $isMandatory || in_array($doc->code, ['aadhaar', 'pan']);
                        }
                        return false;
                    });

                if (! empty($doc->number_regex)) {
                    $numberField->regex($doc->number_regex);
                }

                $cardFields[] = $numberField;
            } else {
                $fileLabel = $doc->name . ($isMandatory ? ' *' : ' (Optional)');
            }

            $fieldName = $doc->code === 'passport_photo' ? 'tenant_photo' : $collectionName;
            $fileField = SpatieMediaLibraryFileUpload::make($fieldName)
                ->collection($collectionName)
                ->label($fileLabel)
                ->hintIcon('heroicon-m-information-circle', tooltip: $tooltip)
                ->openable()
                ->downloadable()
                ->previewable()
                ->required(function (Get $get, ?string $operation) use ($isMandatory) {
                    if ($operation === 'create') {
                        if (! (bool) $get('create_new_tenant')) {
                            return false;
                        }
                        return $isMandatory;
                    }
                    return false;
                });

            if ($doc->code === 'passport_photo') {
                $fileField->image()->avatar();
            }

            $cardFields[] = $fileField;

            $card = Section::make($doc->name . $badge)
                ->icon($icon)
                ->compact()
                ->schema($cardFields);

            if (! $isMandatory && ! in_array($doc->code, ['aadhaar', 'pan', 'cancelled_cheque'])) {
                $card->visible($isVisible);
            }

            $fields[] = $card;
        }

        return $fields;
    }

    public static function getDynamicSecondaryTenantKycFields(): array
    {
        $fields = [];

        try {
            $docs = KycDocumentType::query()
                ->where('is_active', true)
                ->where('tenancy_secondary_requirement', '!=', KycRequirementLevel::DISABLED)
                ->orderBy('sort_order')
                ->get();
        } catch (\Throwable $e) {
            $docs = collect();
        }

        if ($docs->isEmpty()) {
            return [
                Section::make('Passport Photo *')
                    ->icon('heroicon-m-user-circle')
                    ->compact()
                    ->schema([
                        FileUpload::make('photo_file')
                            ->label('Passport Photo *')
                            ->hintIcon('heroicon-m-information-circle', tooltip: 'Passport-sized photograph')
                            ->image()->avatar()->previewable()
                            ->directory('tenancy-secondary-kyc')
                            ->downloadable()->openable(),
                    ]),
                Section::make('Aadhaar Card')
                    ->icon('heroicon-m-identification')
                    ->compact()
                    ->schema([
                        TextInput::make('aadhaar_number')
                            ->label('Aadhaar Number (Optional)')
                            ->placeholder('12-digit Aadhaar number'),
                        FileUpload::make('aadhaar_file')
                            ->label('Aadhaar Card (Optional)')
                            ->hintIcon('heroicon-m-information-circle', tooltip: 'Front & Back image or PDF')
                            ->directory('tenancy-secondary-kyc')
                            ->downloadable()->openable()->previewable(),
                    ]),
                Section::make('PAN Card')
                    ->icon('heroicon-m-credit-card')
                    ->compact()
                    ->schema([
                        TextInput::make('pan_number')
                            ->label('PAN Number (Optional)')
                            ->placeholder('10-character PAN'),
                        FileUpload::make('pan_file')
                            ->label('PAN Card (Optional)')
                            ->hintIcon('heroicon-m-information-circle', tooltip: 'Clear image or PDF')
                            ->directory('tenancy-secondary-kyc')
                            ->downloadable()->openable()->previewable(),
                    ]),
                Section::make('Voter ID Card')
                    ->icon('heroicon-m-identification')
                    ->compact()
                    ->schema([
                        TextInput::make('voter_id')
                            ->label('Voter Card Number (Optional)')
                            ->placeholder('Voter Card / EPIC Number'),
                        FileUpload::make('voter_id_file')
                            ->label('Voter Card (Optional)')
                            ->hintIcon('heroicon-m-information-circle', tooltip: 'Clear image or PDF of Voter Card')
                            ->directory('tenancy-secondary-kyc')
                            ->downloadable()->openable()->previewable(),
                    ]),
            ];
        }

        foreach ($docs as $doc) {
            $isMandatory = $doc->isTenancySecondaryMandatory();
            $badge = $isMandatory ? ' *' : '';
            $tooltip = $doc->description ?: "Attach {$doc->name} image or PDF";

            $fileFieldName = match ($doc->code) {
                'passport_photo' => 'photo_file',
                'aadhaar' => 'aadhaar_file',
                'pan' => 'pan_file',
                'voter_id' => 'voter_id_file',
                default => "{$doc->code}_file",
            };

            $numberFieldName = match ($doc->code) {
                'aadhaar' => 'aadhaar_number',
                'pan' => 'pan_number',
                'voter_id' => 'voter_id',
                default => "{$doc->code}_number",
            };

            $icon = $doc->icon ?: match ($doc->code) {
                'passport_photo' => 'heroicon-m-user-circle',
                'aadhaar' => 'heroicon-m-identification',
                'pan' => 'heroicon-m-credit-card',
                'cancelled_cheque' => 'heroicon-m-banknotes',
                'passport' => 'heroicon-m-globe-alt',
                'driving_license' => 'heroicon-m-truck',
                'voter_id' => 'heroicon-m-identification',
                default => 'heroicon-m-document-text',
            };

            $cardFields = [];

            if ($doc->has_number) {
                $numberLabel = ($doc->number_label ?: "{$doc->name} Number") . ($isMandatory ? ' *' : ' (Optional)');
                $fileLabel = "{$doc->name} File" . ($isMandatory ? ' *' : ' (Optional)');

                $numberField = TextInput::make($numberFieldName)
                    ->label($numberLabel)
                    ->placeholder("Enter {$doc->name} number...");

                if (! empty($doc->number_regex)) {
                    $numberField->regex($doc->number_regex);
                }

                $cardFields[] = $numberField;
            } else {
                $fileLabel = $doc->name . ($isMandatory ? ' *' : ' (Optional)');
            }

            $fileField = FileUpload::make($fileFieldName)
                ->label($fileLabel)
                ->hintIcon('heroicon-m-information-circle', tooltip: $tooltip)
                ->directory('tenancy-secondary-kyc')
                ->openable()
                ->downloadable()
                ->previewable();

            if ($doc->code === 'passport_photo') {
                $fileField->image()->avatar();
            }

            $cardFields[] = $fileField;

            $card = Section::make($doc->name . $badge)
                ->icon($icon)
                ->compact()
                ->schema($cardFields);

            if (! $isMandatory && ! in_array($doc->code, ['passport_photo', 'aadhaar', 'pan', 'voter_id'])) {
                $card->visible(function (Get $get) use ($fileFieldName, $numberFieldName, $doc) {
                    return ! empty($get($fileFieldName)) || ($doc->has_number && ! empty($get($numberFieldName)));
                });
            }

            $fields[] = $card;
        }

        return $fields;
    }
}
