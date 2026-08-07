<?php

namespace App\Filament\Resources\Operations\MaintenanceRequestResource\Schemas;

use App\Domain\Maintenance\Enums\MaintenancePriority;
use App\Domain\Maintenance\Enums\MaintenanceStatus;
use App\Domain\Maintenance\Enums\PayerType;
use App\Domain\Party\Enums\VendorOnboardingStatus;
use App\Domain\Party\Models\Party;
use App\Domain\Property\Models\Property;
use App\Domain\Property\Models\PropertyInventory;
use App\Domain\Property\Models\PropertyRoom;
use App\Domain\Property\Models\PropertyUtility;
use App\Domain\Maintenance\Services\MaintenanceAuditTriggerService;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class MaintenanceRequestForm
{
    public static function configure(Schema $schema): Schema
    {
        $overviewGrid = Grid::make(3)
            ->columnSpanFull()
            ->schema([
                // Main Left Column (2 Cols)
                Grid::make(1)
                    ->columnSpan(2)
                    ->schema([
                        // 📍 Property Section
                        Section::make('📍 Target Property & Context')
                            ->schema([
                                Select::make('property_id')
                                    ->label('Target Property *')
                                    ->options(fn () => Property::all()->mapWithKeys(fn ($p) => [
                                        $p->id => ($p->code ? "{$p->code} - " : '') . ($p->building_name ?: "Property #{$p->id}")
                                    ]))
                                    ->searchable()
                                    ->required()
                                    ->reactive()
                                    ->helperText('Select the property where maintenance is required.'),

                                Placeholder::make('property_summary')
                                    ->label('')
                                    ->columnSpanFull()
                                    ->content(function (Get $get) {
                                        $propertyId = $get('property_id');
                                        if (!$propertyId) {
                                            return new HtmlString(
                                                '<div style="background-color: rgba(128, 128, 128, 0.05); border: 1px dashed rgba(128, 128, 128, 0.25); border-radius: 8px; padding: 14px; text-align: center; color: rgba(128, 128, 128, 0.8); font-size: 13px;">' .
                                                '📍 Select a target property to view owner, tenant, and active ticket context.' .
                                                '</div>'
                                            );
                                        }

                                        $property = Property::with(['mous.party', 'agreements.tenants', 'maintenanceRequests'])->find($propertyId);
                                        if (!$property) return '';

                                        $ownerName = $property->mous->first()?->party?->display_name ?? 'Not Specified';
                                        $latestAgreement = $property->agreements()->where('status', 'active')->first();
                                        $tenantName = $latestAgreement?->tenants->first()?->display_name ?? 'Vacant / No Active Tenant';
                                        $openTicketsCount = $property->maintenanceRequests()->whereNotIn('status', ['closed', 'cancelled'])->count();
                                        $code = $property->code ? e($property->code) . ' - ' : '';
                                        $buildingName = e($property->building_name ?: 'Property #' . $property->id);

                                        return new HtmlString(
                                            '<div style="background-color: rgba(37, 99, 235, 0.04); border: 1px solid rgba(37, 99, 235, 0.15); border-radius: 8px; padding: 16px; font-size: 13px; color: inherit;">' .
                                            '<div style="font-weight: 700; font-size: 14px; margin-bottom: 8px; color: inherit; display: flex; align-items: center; justify-content: space-between;">' .
                                            '<span>📍 Property Context Panel</span>' .
                                            '<span style="padding: 2px 8px; font-size: 10px; border-radius: 4px; background: #2563eb; color: #fff; font-weight: 600;">ACTIVE</span>' .
                                            '</div>' .
                                            '<div style="display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px;">' .
                                            '<div><span style="color: rgba(128, 128, 128, 0.8); font-size: 11px;">Property</span><br><strong>' . $code . $buildingName . '</strong></div>' .
                                            '<div><span style="color: rgba(128, 128, 128, 0.8); font-size: 11px;">Owner</span><br><strong>' . e($ownerName) . '</strong></div>' .
                                            '<div><span style="color: rgba(128, 128, 128, 0.8); font-size: 11px;">Tenant</span><br><strong>' . e($tenantName) . '</strong></div>' .
                                            '<div><span style="color: rgba(128, 128, 128, 0.8); font-size: 11px;">Open Maintenance</span><br><strong style="color: #d97706;">' . $openTicketsCount . ' Active Ticket(s)</strong></div>' .
                                            '</div>' .
                                            '</div>'
                                        );
                                    }),
                            ]),

                        // 🛠 Issue Details Section
                        Section::make('🛠 Issue Details')
                            ->columns(2)
                            ->schema([
                                TextInput::make('title')
                                    ->label('Issue Title *')
                                    ->placeholder('e.g. Kitchen Pipe Leakage / Master Bedroom AC Not Cooling')
                                    ->required()
                                    ->columnSpanFull(),

                                Select::make('priority')
                                    ->label('Priority *')
                                    ->options([
                                        'low' => '🟢 Low',
                                        'medium' => '🟡 Medium',
                                        'high' => '🟠 High',
                                        'emergency' => '🔴 Emergency',
                                    ])
                                    ->default('medium')
                                    ->required(),

                                Select::make('reporter_type')
                                    ->label('Reported By *')
                                    ->options([
                                        'tenant' => 'Tenant',
                                        'owner' => 'Owner',
                                        'staff' => 'Dwelly Staff',
                                    ])
                                    ->default('staff')
                                    ->required(),

                                Textarea::make('description')
                                    ->label('Detailed Description *')
                                    ->placeholder('Describe the issue, specific damage symptoms, affected areas, or emergency instructions...')
                                    ->rows(6)
                                    ->required()
                                    ->columnSpanFull(),
                            ]),
                    ]),

                // Right Sidebar (1 Col)
                Grid::make(1)
                    ->columnSpan(1)
                    ->schema([
                        // 💰 Repair Decision Section
                        Section::make('💰 Repair Decision')
                            ->description('Can be specified now or decided later during triage.')
                            ->schema([
                                Select::make('payer_type')
                                    ->label('Who Pays?')
                                    ->placeholder('Decide Later')
                                    ->options([
                                        'owner' => '👤 Owner',
                                        'tenant' => '🏠 Tenant',
                                        'split' => '🤝 Split (Owner & Tenant)',
                                        'dwelly' => '🏢 Dwelly (Internal Absorbed)',
                                    ])
                                    ->nullable()
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, Set $set) {
                                        if (in_array($state, ['dwelly', \App\Domain\Maintenance\Enums\PayerType::DWELLY->value, \App\Domain\Maintenance\Enums\PayerType::DWELLY_DIRECT_ABSORBED->value])) {
                                            $set('is_direct_vendor', 0);
                                        } elseif (blank($state)) {
                                            $set('is_direct_vendor', null);
                                        }
                                    })
                                    ->helperText('Select financial responsibility.'),

                                Placeholder::make('decide_later_notice')
                                    ->label('')
                                    ->visible(fn (Get $get) => blank($get('payer_type')))
                                    ->content(new HtmlString('<div style="font-size: 12px; color: rgba(128, 128, 128, 0.8); font-style: italic; background-color: rgba(128, 128, 128, 0.04); border-left: 3px solid rgba(128, 128, 128, 0.3); padding: 8px 12px; border-radius: 4px;">ℹ️ Vendor contact method will be configured once financial responsibility is decided.</div>')),

                                Radio::make('is_direct_vendor')
                                    ->label('Vendor Contact Method')
                                    ->visible(fn (Get $get) => filled($get('payer_type')))
                                    ->options(function (Get $get) {
                                        $payer = $get('payer_type');
                                        if (in_array($payer, ['dwelly', \App\Domain\Maintenance\Enums\PayerType::DWELLY->value, \App\Domain\Maintenance\Enums\PayerType::DWELLY_DIRECT_ABSORBED->value])) {
                                            return [
                                                0 => 'Dwelly Coordinates (Internal Expense)',
                                            ];
                                        }
                                        return [
                                            0 => 'Dwelly Coordinates',
                                            1 => 'Owner/Tenant Contacts Vendor Directly',
                                        ];
                                    })
                                    ->default(0)
                                    ->nullable()
                                    ->reactive()
                                    ->helperText('Choose who facilitates vendor contact.'),
                            ]),

                        // 👤 Internal Assignment Section
                        Section::make('👤 Internal Assignment & Status')
                            ->schema([
                                Select::make('assigned_inspector_id')
                                    ->label('Assigned Staff *')
                                    ->options(fn () => \App\Models\User::pluck('name', 'id'))
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->default(auth()->id())
                                    ->helperText('Staff executive responsible for inspection review.'),

                                Placeholder::make('status_header_badge')
                                    ->label('Status')
                                    ->content(function ($record) {
                                        $status = $record?->status ?? MaintenanceStatus::SUBMITTED;
                                        $label = e($status->getLabel());
                                        return new HtmlString("<span class=\"inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-primary-100 text-primary-800 dark:bg-primary-900 dark:text-primary-200\">{$label}</span>");
                                    }),

                                Placeholder::make('ticket_info')
                                    ->label('')
                                    ->content(function ($record) {
                                        $ticketNum = $record?->ticket_number ?? 'Generated after creation';
                                        return new HtmlString("<div class=\"text-xs text-gray-500\">Ticket #: <strong>{$ticketNum}</strong></div>");
                                    }),
                            ]),
                    ]),
            ]);

        $operation = $schema->getOperation();

        if ($operation === 'create') {
            return $schema->components([$overviewGrid]);
        }

        return $schema
            ->components([
                Tabs::make('MaintenanceSteps')
                    ->columnSpanFull()
                    ->tabs([
                        Tab::make('Ticket Overview')
                            ->icon('heroicon-o-information-circle')
                            ->schema([$overviewGrid]),

                        Tab::make('Photos & Repair Items')
                            ->icon('heroicon-o-wrench-screwdriver')
                            ->schema([
                                Section::make('🛠 Defect Photos & Items Needing Repair')
                                    ->description('Log repair items and attach issue photos/videos.')
                                    ->schema([
                                        Repeater::make('items')
                                            ->relationship('items')
                                            ->defaultItems(1)
                                            ->columns(2)
                                            ->schema([
                                                Select::make('itemable_type')
                                                    ->label('Item Category')
                                                    ->placeholder('Select Category')
                                                    ->options([
                                                        PropertyRoom::class => 'Room',
                                                        PropertyInventory::class => 'Inventory Item',
                                                        PropertyUtility::class => 'Utility',
                                                    ])
                                                    ->required()
                                                    ->reactive(),

                                                Select::make('itemable_id')
                                                    ->label('Specific Item')
                                                    ->placeholder('Select Item')
                                                    ->options(function (Get $get) {
                                                        $propertyId = $get('../../../property_id') ?? $get('../../property_id') ?? $get('property_id');
                                                        $type = $get('itemable_type');

                                                        if (!$propertyId || !$type) {
                                                            return [];
                                                        }

                                                        if ($type === PropertyRoom::class) {
                                                            return PropertyRoom::where('property_id', $propertyId)
                                                                ->get()
                                                                ->mapWithKeys(fn ($r) => [
                                                                    $r->id => $r->custom_name ?: ($r->roomDefinition?->name ?? "Room #{$r->id}")
                                                                ]);
                                                        }

                                                        if ($type === PropertyInventory::class) {
                                                            return PropertyInventory::where('property_id', $propertyId)
                                                                ->get()
                                                                ->mapWithKeys(fn ($i) => [
                                                                    $i->id => ($i->inventoryType?->name ?? "Item #{$i->id}") . " (Qty: {$i->count})"
                                                                ]);
                                                        }

                                                        if ($type === PropertyUtility::class) {
                                                            return PropertyUtility::where('property_id', $propertyId)
                                                                ->get()
                                                                ->mapWithKeys(fn ($u) => [
                                                                    $u->id => ($u->utilityType?->name ?? "Utility #{$u->id}") . " (Paid by: {$u->paid_by})"
                                                                ]);
                                                        }

                                                        return [];
                                                    })
                                                    ->required()
                                                    ->searchable(),

                                                Textarea::make('issue_description')
                                                    ->label('Specific Defect / Issue')
                                                    ->rows(2)
                                                    ->required()
                                                    ->columnSpanFull(),

                                                TextInput::make('repair_action')
                                                    ->label('Action Required')
                                                    ->placeholder('e.g. Repair, Replace, Service')
                                                    ->required(),

                                                TextInput::make('actual_cost')
                                                    ->label('Estimated / Actual Item Cost (₹)')
                                                    ->numeric()
                                                    ->prefix('₹')
                                                    ->default(0.00)
                                                    ->required(),

                                                SpatieMediaLibraryFileUpload::make('issue_photos')
                                                    ->collection('issue_photos')
                                                    ->multiple()
                                                    ->required()
                                                    ->label('Defect Photos / Videos (Before Repair)')
                                                    ->columnSpanFull(),

                                                SpatieMediaLibraryFileUpload::make('repaired_photos')
                                                    ->collection('repaired_photos')
                                                    ->multiple()
                                                    ->label('Repaired Photos / Videos (After Repair)')
                                                    ->columnSpanFull(),
                                            ]),
                                    ]),
                            ]),

                        Tab::make('Financial & Audit Summary')
                            ->icon('heroicon-o-currency-rupee')
                            ->schema([
                                Section::make('Vendor Quotation & Repair Facilitation')
                                    ->columns(2)
                                    ->schema([
                                        Select::make('vendor_party_id')
                                            ->label('Assigned Service Vendor')
                                            ->options(function () {
                                                return Party::whereHas('vendorProfile', function ($q) {
                                                    $q->where('onboarding_status', VendorOnboardingStatus::VERIFIED->value)
                                                      ->orWhere('onboarding_status', VendorOnboardingStatus::VERIFIED);
                                                })->pluck('display_name', 'id');
                                            })
                                            ->searchable()
                                            ->columnSpanFull()
                                            ->helperText('Select verified vendor assigned to execute the repair.'),

                                        Section::make('Repair Quotation Upload & Approval (Dwelly Facilitated)')
                                            ->visible(fn (Get $get) => !$get('is_direct_vendor'))
                                            ->columns(2)
                                            ->schema([
                                                TextInput::make('quotation_amount')
                                                    ->label('Quotation Amount (₹)')
                                                    ->numeric()
                                                    ->prefix('₹')
                                                    ->placeholder('0.00'),

                                                Placeholder::make('quotation_status_badge')
                                                    ->label('Quotation Approval Status')
                                                    ->content(function ($record) {
                                                        $status = $record?->quotation_status ?? 'pending';
                                                        $color = match($status) {
                                                            'approved' => 'green',
                                                            'rejected' => 'red',
                                                            default => 'orange',
                                                        };
                                                        return new HtmlString("<span style=\"padding: 4px 10px; border-radius: 6px; font-weight: 600; text-transform: uppercase; font-size: 11px; background-color: {$color}; color: #ffffff;\">" . strtoupper($status) . "</span>");
                                                    }),

                                                Textarea::make('quotation_notes')
                                                    ->label('Quotation Terms & Scope Notes')
                                                    ->rows(2)
                                                    ->columnSpanFull(),

                                                SpatieMediaLibraryFileUpload::make('quotation_files')
                                                    ->collection('quotation_files')
                                                    ->multiple()
                                                    ->label('Vendor Quotation Document / Estimate Photos')
                                                    ->columnSpanFull(),

                                                Section::make('Approval Proof Details')
                                                    ->description('Mandatory proof of approval from owner/tenant (e.g. Email screenshot, WhatsApp screenshot, or signed quotation).')
                                                    ->schema([
                                                        Textarea::make('quotation_approval_notes')
                                                            ->label('Approval Notes / Confirmation Remarks')
                                                            ->rows(2),

                                                        SpatieMediaLibraryFileUpload::make('quotation_approval_proofs')
                                                            ->collection('quotation_approval_proofs')
                                                            ->multiple()
                                                            ->label('Quotation Approval Proof (Screenshot / Signature Document)'),
                                                    ])->columnSpanFull(),
                                            ]),

                                        Section::make('Direct Repair Tracking (Owner / Tenant Direct)')
                                            ->visible(fn (Get $get) => (bool) $get('is_direct_vendor'))
                                            ->columns(2)
                                            ->schema([
                                                Placeholder::make('direct_notice')
                                                    ->columnSpanFull()
                                                    ->content(new HtmlString('<div style="background-color: rgba(37, 99, 235, 0.05); border-left: 4px solid #2563eb; padding: 12px; border-radius: 4px; font-size: 13px;"><strong>Direct Repair Mode Active:</strong> Owner/Tenant is contacting the vendor directly. Dwelly tracks repairs and conducts the verification audit upon completion.</div>')),

                                                TextInput::make('direct_payment_reference')
                                                    ->label('Direct Vendor Ref / Contact Info')
                                                    ->placeholder('e.g. Vendor Name / UPI Ref / Contact #'),

                                                Textarea::make('direct_payment_notes')
                                                    ->label('Direct Repair Tracking Notes')
                                                    ->rows(2)
                                                    ->columnSpanFull(),

                                                SpatieMediaLibraryFileUpload::make('direct_payment_receipts')
                                                    ->collection('direct_payment_receipts')
                                                    ->multiple()
                                                    ->label('Direct Payment Receipts / Vendor Bills (Optional)')
                                                    ->columnSpanFull(),
                                            ]),
                                    ]),

                                Section::make('Linked Verification Audit & Invoicing')
                                    ->columns(2)
                                    ->headerActions([
                                        Action::make('triggerAudit')
                                            ->label('Trigger Post-Repair Audit')
                                            ->icon('heroicon-o-clipboard-document-check')
                                            ->color('info')
                                            ->requiresConfirmation()
                                            ->modalHeading('Trigger Verification Audit')
                                            ->modalDescription('This will create a post-repair Audit containing all repair items from this ticket.')
                                            ->hidden(fn ($record) => filled($record?->triggered_audit_id))
                                            ->action(function ($record, $livewire) {
                                                if (!$record) return;

                                                $service = app(MaintenanceAuditTriggerService::class);
                                                $errors = $service->validateForAuditTrigger($record);

                                                if (!empty($errors)) {
                                                    $bulletList = implode("<br>&bull; ", $errors);
                                                    Notification::make()
                                                        ->title('Cannot Trigger Verification Audit')
                                                        ->body(new HtmlString("Please complete mandatory information:<br>&bull; {$bulletList}"))
                                                        ->danger()
                                                        ->persistent()
                                                        ->send();
                                                    return;
                                                }

                                                $audit = $service->triggerAudit($record);
                                                Notification::make()
                                                    ->title('Audit Triggered')
                                                    ->body("Created Audit #{$audit->audit_number}")
                                                    ->success()
                                                    ->send();
                                                $livewire->refreshFormData(['triggered_audit_id', 'status']);
                                            }),
                                    ])
                                    ->schema([
                                        Placeholder::make('linked_audit')
                                            ->label('')
                                            ->columnSpanFull()
                                            ->content(function ($record) {
                                                if (!$record || !$record->triggered_audit_id || !$record->triggeredAudit) {
                                                    return new HtmlString(
                                                        '<div style="background-color: rgba(128, 128, 128, 0.03); border: 1px solid rgba(128, 128, 128, 0.2); padding: 18px; border-radius: 8px; color: inherit; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">' .
                                                            '<div>' .
                                                            '<div style="font-weight: 700; font-size: 15px; color: inherit;">No Verification Audit Triggered Yet</div>' .
                                                            '<div style="font-size: 13px; color: rgba(128, 128, 128, 0.85); margin-top: 4px;">Click <strong>"Trigger Post-Repair Audit"</strong> above after repairs are completed to perform inspection.</div>' .
                                                            '</div>' .
                                                            '</div>'
                                                    );
                                                }

                                                $audit = $record->triggeredAudit;
                                                $statusLabel = e($audit->status?->getLabel() ?? (is_string($audit->status) ? ucfirst($audit->status) : ($audit->status?->value ?? 'Pending')));
                                                $auditNumber = e($audit->audit_number);
                                                $typeLabel = e($audit->audit_type?->getLabel() ?? 'Maintenance Verification');
                                                $inspectorName = e($audit->inspector?->name ?? $record->assignedInspector?->name ?? 'Unassigned');

                                                try {
                                                    $inspectUrl = \App\Filament\Resources\Operations\AuditResource::getUrl('inspect', ['record' => $audit]);
                                                } catch (\Throwable $e) {
                                                    $inspectUrl = url("/operations/audits/{$audit->id}/inspect");
                                                }

                                                try {
                                                    $editUrl = \App\Filament\Resources\Operations\AuditResource::getUrl('edit', ['record' => $audit]);
                                                } catch (\Throwable $e) {
                                                    $editUrl = url("/operations/audits/{$audit->id}/edit");
                                                }

                                                return new HtmlString(
                                                    '<div style="background-color: rgba(128, 128, 128, 0.03); border: 1px solid rgba(128, 128, 128, 0.2); padding: 16px; border-radius: 8px; display: flex; align-items: center; justify-content: space-between; color: inherit; flex-wrap: wrap; gap: 12px;">' .
                                                        '<div>' .
                                                        '<div style="font-weight: 700; font-size: 15px; color: inherit;">Post-Repair Verification Audit: ' . $auditNumber . '</div>' .
                                                        '<div style="font-size: 13px; color: rgba(128, 128, 128, 0.85); margin-top: 4px;">Type: <strong>' . $typeLabel . '</strong> | Status: <span style="padding: 2px 8px; font-size: 11px; border-radius: 4px; font-weight: 600; background-color: #dbeafe; color: #1e40af; text-transform: uppercase;">' . $statusLabel . '</span></div>' .
                                                        '<div style="font-size: 12px; color: rgba(128, 128, 128, 0.7); margin-top: 4px;">Assigned Inspector: <strong>' . $inspectorName . '</strong> &bull; Property: <strong>' . e($record->property?->building_name ?? 'Property') . '</strong></div>' .
                                                        '</div>' .
                                                        '<div style="display: flex; gap: 8px; align-items: center;">' .
                                                        '<a href="' . e($editUrl) . '" target="_blank" style="display: inline-flex; align-items: center; padding: 8px 14px; background-color: rgba(37, 99, 235, 0.1); color: #2563eb; font-weight: 600; font-size: 13px; border-radius: 6px; text-decoration: none;">View Audit &rarr;</a>' .
                                                        '<a href="' . e($inspectUrl) . '" target="_blank" style="display: inline-flex; align-items: center; padding: 8px 16px; background-color: #2563eb; color: #ffffff; font-weight: 600; font-size: 13px; border-radius: 6px; text-decoration: none; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">Perform Inspection &rarr;</a>' .
                                                        '</div>' .
                                                        '</div>'
                                                );
                                            }),
                                    ]),
                            ]),
                    ]),
            ]);
    }
}
