<?php

namespace App\Filament\Resources\Operations\TenantDeboardingResource\Schemas;

use App\Domain\Agreement\Enums\DeboardingStatus;
use App\Domain\Agreement\Models\TenancyAgreement;
use App\Domain\Agreement\Models\TenantDeboarding;
use App\Domain\Audit\Enums\AuditStatus;
use App\Domain\Maintenance\Enums\MaintenancePriority;
use App\Filament\Resources\Operations\AuditResource;
use App\Filament\Resources\Operations\MaintenanceRequestResource;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class TenantDeboardingForm
{
    public static function configure(Schema $schema): Schema
    {
        $operation = $schema->getOperation();

        if ($operation === 'create') {
            return static::configureCreationSchema($schema);
        }

        return static::configureNoticeForm($schema);
    }

    public static function configureCreationSchema(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Select Active Tenancy Agreement')
                    ->description('Select the tenancy agreement to initiate move-out exit deboarding.')
                    ->columnSpanFull()
                    ->schema([
                        Select::make('tenancy_agreement_id')
                            ->label('Active Tenancy Agreement')
                            ->options(function () {
                                return TenancyAgreement::with(['property', 'primaryTenant.party'])
                                    ->whereIn('status', ['active', 'deboarding_initiated'])
                                    ->whereDoesntHave('deboarding', function ($q) {
                                        $q->where('status', DeboardingStatus::COMPLETED);
                                    })
                                    ->get()
                                    ->mapWithKeys(function (TenancyAgreement $t) {
                                        $tenantName = $t->primaryTenant?->party?->display_name ?? 'Tenant';
                                        $propCode = $t->property?->code ?? $t->property?->building_name ?? 'Property';
                                        return [$t->id => "{$t->code} — {$propCode} ({$tenantName})"];
                                    });
                            })
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (Set $set, $state) {
                                if (! $state) {
                                    return;
                                }
                                $agreement = TenancyAgreement::with('property')->find($state);
                                if ($agreement) {
                                    $set('property_id', $agreement->property_id);
                                    $set('tenant_id', $agreement->primaryTenant?->party_id);
                                    $set('security_deposit_held', $agreement->security_deposit ?? 0.00);
                                    $set('notice_date', $agreement->notice_date?->toDateString() ?? now()->toDateString());
                                    $set('target_vacating_date', $agreement->vacating_date?->toDateString() ?? now()->addDays(30)->toDateString());
                                    $set('reason', $agreement->deboarding_reason ?? 'Agreement Expiry');
                                    $set('notes', $agreement->deboarding_notes);
                                }
                            }),

                        DatePicker::make('notice_date')
                            ->label('Notice Date')
                            ->default(now()->toDateString())
                            ->required(),

                        DatePicker::make('target_vacating_date')
                            ->label('Target Vacating Date')
                            ->default(now()->addDays(30)->toDateString())
                            ->required(),

                        Select::make('reason')
                            ->label('Reason for Deboarding')
                            ->options([
                                'Agreement Expiry' => 'Agreement Expiry',
                                'Tenant Early Termination' => 'Tenant Early Termination',
                                'Owner Request' => 'Owner Request / Non-renewal',
                                'Eviction' => 'Eviction',
                                'Mutual Agreement' => 'Mutual Agreement',
                                'Other' => 'Other',
                            ])
                            ->default('Agreement Expiry')
                            ->required(),

                        Textarea::make('notes')
                            ->label('Deboarding Notes & Special Remarks')
                            ->rows(2)
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }

    /**
     * 1. Notice & Overview Form
     */
    public static function configureNoticeForm(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('1. Notice & Commencement of Exit')
                    ->description('Record notice dates, reason for exit, and general exit remarks.')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                DatePicker::make('notice_date')
                                    ->label('Notice Date')
                                    ->required(),

                                DatePicker::make('target_vacating_date')
                                    ->label('Target Vacating Date')
                                    ->required(),

                                DatePicker::make('actual_vacating_date')
                                    ->label('Actual Vacating Date'),
                            ]),

                        Select::make('reason')
                            ->label('Deboarding Reason')
                            ->options([
                                'Agreement Expiry' => 'Agreement Expiry',
                                'Tenant Early Termination' => 'Tenant Early Termination',
                                'Owner Request' => 'Owner Request / Non-renewal',
                                'Eviction' => 'Eviction',
                                'Mutual Agreement' => 'Mutual Agreement',
                                'Other' => 'Other',
                            ]),

                        Textarea::make('notes')
                            ->label('Deboarding Notes & Exit Remarks')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /**
     * 2. Exit Audit Form
     */
    public static function configureAuditForm(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('2. Move-Out Exit Verification Audit')
                    ->description('Comprehensive inspection comparing current property condition against the baseline Move-In audit.')
                    ->columnSpanFull()
                    ->schema([
                        Placeholder::make('audit_card')
                            ->hiddenLabel()
                            ->content(function (?TenantDeboarding $record) {
                                if (! $record) {
                                    return '';
                                }

                                $audit = $record->moveOutAudit;
                                if (! $audit) {
                                    return new HtmlString(
                                        '<div style="padding: 14px; background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 8px; color: #1e40af; font-size: 13px;">' .
                                            'ℹ️ No Move-Out audit is linked yet. Use the header action to trigger or re-assign the exit inspection.' .
                                        '</div>'
                                    );
                                }

                                $auditStatus = $audit->status;
                                $statusLabel = is_object($auditStatus) && method_exists($auditStatus, 'getLabel') ? $auditStatus->getLabel() : (string) $auditStatus;
                                $inspectUrl = AuditResource::getUrl('inspect', ['record' => $audit->id]);
                                $reviewUrl = AuditResource::getUrl('review', ['record' => $audit->id]);
                                $editUrl = AuditResource::getUrl('edit', ['record' => $audit->id]);

                                $isApproved = in_array((string) ($auditStatus instanceof AuditStatus ? $auditStatus->value : $auditStatus), ['approved', 'completed']);
                                $statusBadgeColor = $isApproved ? 'background: #dcfce7; color: #15803d; border-color: #bbf7d0;' : 'background: #fef3c7; color: #b45309; border-color: #fde68a;';

                                return new HtmlString(
                                    '<div style="display: flex; flex-direction: column; gap: 12px; padding: 18px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px;">' .
                                        '<div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">' .
                                            '<div>' .
                                                '<div style="display: flex; align-items: center; gap: 8px;">' .
                                                    '<span style="font-weight: 700; font-size: 15px; color: #0f172a;">Move-Out Audit #' . e($audit->audit_number) . '</span>' .
                                                    '<span style="display: inline-flex; padding: 2px 8px; font-size: 11px; font-weight: 700; border-radius: 6px; border: 1px solid; ' . $statusBadgeColor . '">' . e($statusLabel) . '</span>' .
                                                '</div>' .
                                                '<div style="font-size: 13px; color: #64748b; margin-top: 4px;">' .
                                                    'Inspector: <strong>' . e($audit->inspector?->name ?? 'Unassigned') . '</strong> | Scheduled: ' . e($audit->scheduled_at?->format('d M Y') ?? 'Not Scheduled') .
                                                    ($record->tenancyAgreement?->audit ? ' | Referenced Baseline: #' . e($record->tenancyAgreement->audit->audit_number) : '') .
                                                '</div>' .
                                            '</div>' .
                                            '<div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">' .
                                                '<a href="' . e($inspectUrl) . '" target="_blank" style="display: inline-flex; align-items: center; gap: 4px; padding: 6px 12px; background: #3b82f6; color: #ffffff; border-radius: 6px; font-size: 12px; font-weight: 600; text-decoration: none;">' .
                                                    '🔍 Perform Inspection →' .
                                                '</a>' .
                                                '<a href="' . e($reviewUrl) . '" target="_blank" style="display: inline-flex; align-items: center; gap: 4px; padding: 6px 12px; background: #6366f1; color: #ffffff; border-radius: 6px; font-size: 12px; font-weight: 600; text-decoration: none;">' .
                                                    '📋 Review & Approve →' .
                                                '</a>' .
                                                '<a href="' . e($editUrl) . '" target="_blank" style="display: inline-flex; align-items: center; gap: 4px; padding: 6px 12px; background: #e2e8f0; color: #334155; border-radius: 6px; font-size: 12px; font-weight: 600; text-decoration: none;">' .
                                                    '⚙️ Audit Settings' .
                                                '</a>' .
                                            '</div>' .
                                        '</div>' .
                                    '</div>'
                                );
                            })
                            ->columnSpanFull(),

                        Toggle::make('damages_identified')
                            ->label('Damages / Property Defects Identified during Move-Out Audit')
                            ->helperText('Enable if damages were observed that require maintenance, cleaning, painting, or security deposit deductions.')
                            ->live(),

                        Textarea::make('damage_notes')
                            ->label('Damage Assessment & Findings Notes')
                            ->placeholder('Document tenant damages, missing items, deep cleaning requirements, or wall painting defects...')
                            ->rows(3)
                            ->visible(fn (Get $get) => (bool) $get('damages_identified'))
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /**
     * 3. Maintenance & Damages Form
     */
    public static function configureMaintenanceForm(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('3. Maintenance & Damage Resolution')
                    ->description('Manage maintenance tickets and cost allocations for damages identified during exit inspection.')
                    ->columnSpanFull()
                    ->headerActions([
                        \Filament\Actions\Action::make('createMaintenanceTicket')
                            ->label('Raise Maintenance Request')
                            ->icon('heroicon-o-wrench-screwdriver')
                            ->color('warning')
                            ->disabled(fn (?TenantDeboarding $record) => (bool) ($record && $record->status === DeboardingStatus::COMPLETED))
                            ->modalHeading('Raise Maintenance Request for Exit Damages')
                            ->modalDescription('Create a new maintenance request directly linked to this deboarding process.')
                            ->form([
                                TextInput::make('title')
                                    ->label('Issue / Repair Title')
                                    ->placeholder('e.g. Living room wall repainting & bathroom tile repair')
                                    ->required(),

                                Textarea::make('description')
                                    ->label('Detailed Defect Description')
                                    ->placeholder('Describe specific damages noted during move-out exit audit...')
                                    ->rows(2)
                                    ->required(),

                                Grid::make(3)
                                    ->schema([
                                        Select::make('priority')
                                            ->label('Priority')
                                            ->options(MaintenancePriority::class)
                                            ->default(MaintenancePriority::MEDIUM)
                                            ->required(),

                                        Select::make('payer_type')
                                            ->label('Payer Decision')
                                            ->options([
                                                'tenant' => '🏠 Tenant (Deduct from Security Deposit)',
                                                'split' => '🤝 Split (Owner & Tenant)',
                                                'owner' => '👤 Owner',
                                                'dwelly' => '🏢 Dwelly Internal',
                                            ])
                                            ->default('tenant')
                                            ->live()
                                            ->required(),

                                        Select::make('assigned_inspector_id')
                                            ->label('Assigned Inspector / Staff')
                                            ->options(fn () => \App\Models\User::pluck('name', 'id'))
                                            ->default(fn () => auth()->id())
                                            ->required(),
                                    ]),

                                Grid::make(2)
                                    ->schema([
                                        TextInput::make('estimated_cost')
                                            ->label('Total Estimated Repair Cost (₹)')
                                            ->numeric()
                                            ->prefix('₹')
                                            ->default(0.00)
                                            ->live()
                                            ->afterStateUpdated(function (Set $set, Get $get, $state) {
                                                $payer = $get('payer_type');
                                                if ($payer === 'tenant') {
                                                    $set('tenant_amount', $state);
                                                    $set('owner_amount', 0.00);
                                                } elseif ($payer === 'owner') {
                                                    $set('owner_amount', $state);
                                                    $set('tenant_amount', 0.00);
                                                }
                                            })
                                            ->required(),

                                        TextInput::make('tenant_amount')
                                            ->label('Tenant Payable Share (₹)')
                                            ->numeric()
                                            ->prefix('₹')
                                            ->default(0.00)
                                            ->helperText('This amount will be deducted from the security deposit.')
                                            ->required(),
                                    ]),
                            ])
                            ->action(function (array $data, ?TenantDeboarding $record, $livewire) {
                                if (! $record) {
                                    return;
                                }
                                $service = app(\App\Domain\Agreement\Services\TenancyDeboardingService::class);
                                $maint = $service->createMaintenanceForDeboarding($record, $data, auth()->user());

                                \Filament\Notifications\Notification::make()
                                    ->title('Maintenance Request Created')
                                    ->body("Maintenance ticket #{$maint->ticket_number} created and tenant liability updated.")
                                    ->success()
                                    ->send();

                                if (method_exists($livewire, 'fillForm')) {
                                    $livewire->fillForm();
                                }
                            }),
                    ])
                    ->schema([
                        Placeholder::make('maintenance_summary_card')
                            ->hiddenLabel()
                            ->content(function (?TenantDeboarding $record) {
                                if (! $record) {
                                    return '';
                                }

                                $requests = $record->maintenanceRequests;

                                if ($requests->isEmpty()) {
                                    return new HtmlString(
                                        '<div style="display: flex; align-items: center; justify-content: space-between; padding: 18px; background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 8px;">' .
                                            '<div style="font-size: 13px; color: #64748b;">' .
                                                'No maintenance requests linked yet. If the exit inspection revealed repairs or painting needed, click <strong>Raise Maintenance Request</strong> in the top header.' .
                                            '</div>' .
                                        '</div>'
                                    );
                                }

                                $rows = '';
                                foreach ($requests as $m) {
                                    $mUrl = MaintenanceRequestResource::getUrl('edit', ['record' => $m->id]);
                                    $pLabel = $m->priority instanceof MaintenancePriority ? $m->priority->getLabel() : (string) $m->priority;
                                    $sLabel = $m->status instanceof \App\Domain\Maintenance\Enums\MaintenanceStatus ? $m->status->getLabel() : (string) $m->status;

                                    $rows .= '<tr style="border-bottom: 1px solid #f1f5f9;">' .
                                        '<td style="padding: 10px 12px; font-weight: 600;"><a href="' . e($mUrl) . '" target="_blank" style="color: #2563eb; text-decoration: none;">' . e($m->ticket_number) . '</a></td>' .
                                        '<td style="padding: 10px 12px;">' . e($m->title) . '</td>' .
                                        '<td style="padding: 10px 12px;"><span style="display: inline-flex; padding: 2px 6px; font-size: 11px; font-weight: 600; border-radius: 4px; background: #f1f5f9; color: #475569;">' . e($pLabel) . '</span></td>' .
                                        '<td style="padding: 10px 12px;"><span style="display: inline-flex; padding: 2px 6px; font-size: 11px; font-weight: 600; border-radius: 4px; background: #eff6ff; color: #1e40af;">' . e($sLabel) . '</span></td>' .
                                        '<td style="padding: 10px 12px; font-weight: 600; text-align: right;">₹' . number_format((float) $m->total_cost, 2) . '</td>' .
                                        '<td style="padding: 10px 12px; font-weight: 700; color: #b91c1c; text-align: right;">₹' . number_format((float) $m->tenant_amount, 2) . '</td>' .
                                        '<td style="padding: 10px 12px; text-align: center;"><a href="' . e($mUrl) . '" target="_blank" style="font-size: 12px; color: #4f46e5; font-weight: 500;">View →</a></td>' .
                                    '</tr>';
                                }

                                return new HtmlString(
                                    '<div style="border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; margin-bottom: 12px;">' .
                                        '<table style="width: 100%; border-collapse: collapse; font-size: 13px;">' .
                                            '<thead>' .
                                                '<tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0; text-align: left; color: #64748b; font-weight: 600;">' .
                                                    '<th style="padding: 8px 12px;">Ticket #</th>' .
                                                    '<th style="padding: 8px 12px;">Title</th>' .
                                                    '<th style="padding: 8px 12px;">Priority</th>' .
                                                    '<th style="padding: 8px 12px;">Status</th>' .
                                                    '<th style="padding: 8px 12px; text-align: right;">Total Cost</th>' .
                                                    '<th style="padding: 8px 12px; text-align: right;">Tenant Share</th>' .
                                                    '<th style="padding: 8px 12px; text-align: center;">Action</th>' .
                                                '</tr>' .
                                            '</thead>' .
                                            '<tbody>' .
                                                $rows .
                                            '</tbody>' .
                                        '</table>' .
                                    '</div>'
                                );
                            })
                            ->columnSpanFull(),

                        Grid::make(3)
                            ->schema([
                                TextInput::make('total_repair_cost')
                                    ->label('Total Repair Cost (₹)')
                                    ->numeric()
                                    ->prefix('₹')
                                    ->disabled()
                                    ->dehydrated(),

                                TextInput::make('tenant_repair_share')
                                    ->label('Tenant Damage Share (₹)')
                                    ->numeric()
                                    ->prefix('₹')
                                    ->disabled()
                                    ->dehydrated()
                                    ->helperText('Deducted from security deposit in settlement.'),

                                TextInput::make('owner_repair_share')
                                    ->label('Owner Absorption Share (₹)')
                                    ->numeric()
                                    ->prefix('₹')
                                    ->disabled()
                                    ->dehydrated(),
                            ]),
                    ]),
            ]);
    }

    /**
     * 4. Keys Handover Form
     */
    public static function configureKeysForm(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('4. Key & Society Badge Handover')
                    ->description('Record physical keys and society RFID tags returned by the vacating tenant.')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                Toggle::make('keys_returned')
                                    ->label('Keys & Badges Returned')
                                    ->helperText('Tenant has formally handed over all physical keys and RFID cards.')
                                    ->live(),

                                DateTimePicker::make('keys_returned_at')
                                    ->label('Key Return Date & Time')
                                    ->default(now()),

                                Select::make('keys_received_by_id')
                                    ->label('Received By (Staff Member)')
                                    ->relationship('keysReceivedBy', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->default(fn () => auth()->id()),
                            ]),

                        Textarea::make('key_handover_remarks')
                            ->label('Key Return Checklist & Remarks')
                            ->placeholder('e.g. 2 Main Door Keys, 1 Master Bedroom Key, 1 Society Gate RFID Card returned in good order.')
                            ->rows(2)
                            ->columnSpanFull(),

                        SpatieMediaLibraryFileUpload::make('key_return_photos')
                            ->label('Key Return Photo Evidence')
                            ->collection('key_return_photos')
                            ->multiple()
                            ->downloadable()
                            ->openable()
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /**
     * 5. Security Deposit Settlement Form
     */
    public static function configureSettlementForm(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('5. Security Deposit Settlement & Deductions Breakdown')
                    ->description('Calculate deductions and determine the net security deposit refund balance.')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextInput::make('security_deposit_held')
                                    ->label('Security Deposit Held (₹)')
                                    ->numeric()
                                    ->prefix('₹')
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(fn ($state, Set $set, Get $get) => static::recalculateFormSettlement($set, $get)),

                                TextInput::make('unpaid_rent_deduction')
                                    ->label('Unpaid Rent / Invoice Dues (₹)')
                                    ->numeric()
                                    ->prefix('₹')
                                    ->default(0.00)
                                    ->live()
                                    ->afterStateUpdated(fn ($state, Set $set, Get $get) => static::recalculateFormSettlement($set, $get)),

                                TextInput::make('maintenance_deduction')
                                    ->label('Maintenance & Repair Deduction (₹)')
                                    ->numeric()
                                    ->prefix('₹')
                                    ->default(0.00)
                                    ->live()
                                    ->afterStateUpdated(fn ($state, Set $set, Get $get) => static::recalculateFormSettlement($set, $get)),

                                TextInput::make('utility_deduction')
                                    ->label('Utility / Society Bill Dues (₹)')
                                    ->numeric()
                                    ->prefix('₹')
                                    ->default(0.00)
                                    ->live()
                                    ->afterStateUpdated(fn ($state, Set $set, Get $get) => static::recalculateFormSettlement($set, $get)),

                                TextInput::make('other_deductions')
                                    ->label('Other Miscellaneous Deductions (₹)')
                                    ->numeric()
                                    ->prefix('₹')
                                    ->default(0.00)
                                    ->live()
                                    ->afterStateUpdated(fn ($state, Set $set, Get $get) => static::recalculateFormSettlement($set, $get)),

                                TextInput::make('total_deductions')
                                    ->label('Total Deductions (₹)')
                                    ->numeric()
                                    ->prefix('₹')
                                    ->disabled()
                                    ->dehydrated(),
                            ]),

                        Textarea::make('other_deductions_notes')
                            ->label('Notes on Other / Miscellaneous Deductions')
                            ->rows(1)
                            ->columnSpanFull(),

                        Placeholder::make('settlement_result_card')
                            ->hiddenLabel()
                            ->content(function (Get $get) {
                                $deposit = (float) $get('security_deposit_held');
                                $totalDed = (float) $get('total_deductions');
                                $netRefund = (float) $get('net_deposit_refund');
                                $excess = (float) $get('excess_due_from_tenant');

                                if ($excess > 0) {
                                    return new HtmlString(
                                        '<div style="padding: 16px; background: #fee2e2; border: 1px solid #fca5a5; border-radius: 8px; display: flex; justify-content: space-between; align-items: center;">' .
                                            '<div>' .
                                                '<div style="font-weight: 700; color: #991b1b; font-size: 15px;">⚠️ Total Deductions Exceed Held Deposit</div>' .
                                                '<div style="font-size: 13px; color: #7f1d1d; margin-top: 2px;">Tenant owes balance dues. An invoice will be raised for the excess amount.</div>' .
                                            '</div>' .
                                            '<div style="font-size: 1.35rem; font-weight: 800; color: #991b1b;">Excess Due: ₹' . number_format($excess, 2) . '</div>' .
                                        '</div>'
                                    );
                                }

                                return new HtmlString(
                                    '<div style="padding: 16px; background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 8px; display: flex; justify-content: space-between; align-items: center;">' .
                                        '<div>' .
                                                    '<div style="font-weight: 700; color: #065f46; font-size: 15px;">✅ Net Refundable to Tenant</div>' .
                                                    '<div style="font-size: 13px; color: #047857; margin-top: 2px;">Deposit Held (₹' . number_format($deposit, 2) . ') − Total Deductions (₹' . number_format($totalDed, 2) . ')</div>' .
                                                '</div>' .
                                                '<div style="font-size: 1.35rem; font-weight: 800; color: #065f46;">Net Refund: ₹' . number_format($netRefund, 2) . '</div>' .
                                            '</div>'
                                );
                            })
                            ->columnSpanFull(),

                        TextInput::make('net_deposit_refund')
                            ->hidden()
                            ->dehydrated(),

                        TextInput::make('excess_due_from_tenant')
                            ->hidden()
                            ->dehydrated(),
                    ]),

                Section::make('💳 Refund Disbursement & Bank Details')
                    ->description('Record refund payment details and upload bank transaction receipts.')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                Select::make('settlement_status')
                                    ->label('Deposit Settlement Status')
                                    ->options([
                                        'pending' => 'Pending Settlement',
                                        'refunded' => 'Refunded to Tenant',
                                        'balance_due' => 'Balance Due from Tenant',
                                        'settled' => 'Fully Settled / Closed',
                                    ])
                                    ->default('pending')
                                    ->required(),

                                Select::make('refund_payment_mode')
                                    ->label('Payment Mode')
                                    ->options([
                                        'Bank Transfer (NEFT / IMPS / RTGS)' => 'Bank Transfer (NEFT / IMPS / RTGS)',
                                        'UPI' => 'UPI',
                                        'Cheque' => 'Cheque',
                                        'Cash' => 'Cash',
                                        'Adjusted against Outstanding' => 'Adjusted against Outstanding',
                                    ]),

                                TextInput::make('refund_transaction_reference')
                                    ->label('Transaction Reference / UTR No.')
                                    ->placeholder('e.g. UTR123456789 / Cheque No.'),

                                DateTimePicker::make('refunded_at')
                                    ->label('Refund Disbursed At'),
                            ]),

                        SpatieMediaLibraryFileUpload::make('refund_payment_proofs')
                            ->label('Refund Payment Receipt / Transaction Screenshot')
                            ->collection('refund_payment_proofs')
                            ->multiple()
                            ->downloadable()
                            ->openable()
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /**
     * 6. Handover & Completion Form
     */
    public static function configureCompletionForm(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('6. Destination Property Status & Final Handover')
                    ->description('Set property destination status and finalize deboarding workflow.')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('new_property_status')
                                    ->label('Destination Property Status after Deboarding')
                                    ->options([
                                        'vacant' => 'Vacant & Ready for New Onboarding',
                                        'under_maintenance' => 'Under Maintenance (Further deep repairs / renovations)',
                                    ])
                                    ->default('vacant')
                                    ->required(),

                                Select::make('status')
                                    ->label('Deboarding Workflow Stage')
                                    ->options(DeboardingStatus::class)
                                    ->required(),
                            ]),
                    ]),
            ]);
    }

    public static function recalculateFormSettlement(Set $set, Get $get): void
    {
        $deposit = (float) ($get('security_deposit_held') ?? 0.00);
        $unpaidRent = (float) ($get('unpaid_rent_deduction') ?? 0.00);
        $maintenance = (float) ($get('maintenance_deduction') ?? 0.00);
        $utility = (float) ($get('utility_deduction') ?? 0.00);
        $other = (float) ($get('other_deductions') ?? 0.00);

        $totalDeductions = $unpaidRent + $maintenance + $utility + $other;
        $set('total_deductions', round($totalDeductions, 2));

        $net = $deposit - $totalDeductions;
        if ($net >= 0) {
            $set('net_deposit_refund', round($net, 2));
            $set('excess_due_from_tenant', 0.00);
        } else {
            $set('net_deposit_refund', 0.00);
            $set('excess_due_from_tenant', round(abs($net), 2));
        }
    }
}
