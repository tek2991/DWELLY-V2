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
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
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
        return $schema
            ->components([
                Tabs::make('MaintenanceDetails')
                    ->columnSpanFull()
                    ->tabs([
                        Tab::make('Ticket Overview')
                            ->icon('heroicon-o-information-circle')
                            ->schema([
                                Section::make('Ticket & Property Overview')
                                    ->columns(2)
                                    ->schema([
                                        TextInput::make('ticket_number')
                                            ->disabled()
                                            ->dehydrated(false)
                                            ->placeholder('Auto-generated (e.g. MNT-2026-00001)'),

                                        Select::make('property_id')
                                            ->label('Target Property')
                                            ->options(fn () => Property::all()->mapWithKeys(fn ($p) => [
                                                $p->id => ($p->code ? "{$p->code} - " : '') . ($p->building_name ?: "Property #{$p->id}")
                                            ]))
                                            ->searchable()
                                            ->required()
                                            ->reactive(),

                                        Select::make('priority')
                                            ->options(MaintenancePriority::class)
                                            ->default('medium')
                                            ->required(),

                                        Select::make('reporter_type')
                                            ->options([
                                                'tenant' => 'Tenant',
                                                'owner' => 'Owner',
                                                'staff' => 'Dwelly Staff',
                                            ])
                                            ->default('staff')
                                            ->required(),

                                        TextInput::make('title')
                                            ->label('Issue Title')
                                            ->required()
                                            ->columnSpanFull(),

                                        Textarea::make('description')
                                            ->label('Detailed Problem Description')
                                            ->rows(3)
                                            ->required()
                                            ->columnSpanFull(),

                                        Select::make('vendor_party_id')
                                            ->label('Assigned Service Vendor')
                                            ->options(function () {
                                                return Party::whereHas('vendorProfile', function ($q) {
                                                    $q->where('onboarding_status', VendorOnboardingStatus::VERIFIED->value)
                                                      ->orWhere('onboarding_status', VendorOnboardingStatus::VERIFIED);
                                                })->pluck('display_name', 'id');
                                            })
                                            ->searchable()
                                            ->helperText('Only active & verified vendors can be assigned.'),

                                        Select::make('assigned_inspector_id')
                                            ->label('Assigned Inspector / Executive')
                                            ->options(fn () => \App\Models\User::pluck('name', 'id'))
                                            ->searchable()
                                            ->preload()
                                            ->required()
                                            ->default(auth()->id())
                                            ->helperText('Staff member assigned to conduct or review the post-repair verification audit.'),

                                        Placeholder::make('status_display')
                                            ->label('Current Status')
                                            ->content(function ($record) {
                                                $status = $record?->status ?? MaintenanceStatus::SUBMITTED;
                                                $label = e($status->getLabel());
                                                return new HtmlString("<span class=\"inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-primary-100 text-primary-800 dark:bg-primary-900 dark:text-primary-200\">{$label}</span> <span class=\"text-xs text-gray-500 ml-1\">(Automated by Workflow)</span>");
                                            }),
                                    ]),
                            ]),

                        Tab::make('Repaired Items & Issue Photos')
                            ->icon('heroicon-o-wrench-screwdriver')
                            ->visible(fn (string $operation) => $operation !== 'create')
                            ->schema([
                                Section::make('Select Items Needing Repair & Issue Media Evidence')
                                    ->description('Add items requiring maintenance and upload issue photos/videos for each item.')
                                    ->schema([
                                        Repeater::make('items')
                                            ->relationship('items')
                                            ->defaultItems(1)
                                            ->columns(2)
                                            ->schema([
                                                Select::make('itemable_type')
                                                    ->label('Item Category')
                                                    ->placeholder('Select Item Category')
                                                    ->options([
                                                        PropertyRoom::class => 'Room',
                                                        PropertyInventory::class => 'Inventory Item',
                                                        PropertyUtility::class => 'Utility',
                                                    ])
                                                    ->required()
                                                    ->reactive(),

                                                Select::make('itemable_id')
                                                    ->label('Select Specific Item')
                                                    ->placeholder('Select Specific Item')
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
                                                    ->label('Cost (₹)')
                                                    ->numeric()
                                                    ->prefix('₹')
                                                    ->default(0.00)
                                                    ->required(),

                                                SpatieMediaLibraryFileUpload::make('issue_photos')
                                                    ->collection('issue_photos')
                                                    ->multiple()
                                                    ->required()
                                                    ->label('Defect Photos / Videos (Before Repair)'),

                                                SpatieMediaLibraryFileUpload::make('repaired_photos')
                                                    ->collection('repaired_photos')
                                                    ->multiple()
                                                    ->required()
                                                    ->label('Repaired Photos / Videos (After Repair)'),
                                            ]),
                                    ]),
                            ]),

                        Tab::make('Financial Settlement & Receipts')
                            ->icon('heroicon-o-currency-rupee')
                            ->visible(fn (string $operation) => $operation !== 'create')
                            ->schema([
                                Section::make('Financial Settlement & Repair Completion Proofs')
                                    ->columns(2)
                                    ->schema([
                                        Select::make('payer_type')
                                            ->label('Payment Scenario & Financial Responsibility')
                                            ->options(PayerType::class)
                                            ->required()
                                            ->reactive()
                                            ->columnSpanFull()
                                            ->helperText('Select whether Owner/Tenant paid directly, or if Dwelly is purchasing the service and invoicing.'),

                                        TextInput::make('vendor_cost')
                                            ->label('Vendor Payout Amount (₹)')
                                            ->numeric()
                                            ->prefix('₹')
                                            ->default(0.00)
                                            ->required()
                                            ->reactive()
                                            ->visible(function (Get $get) {
                                                $type = $get('payer_type');
                                                return in_array($type, [
                                                    PayerType::DWELLY_INVOICE_OWNER->value,
                                                    PayerType::DWELLY_INVOICE_TENANT->value,
                                                    PayerType::DWELLY_INVOICE_OWNER,
                                                    PayerType::DWELLY_INVOICE_TENANT,
                                                ]);
                                            })
                                            ->helperText('Actual amount paid by Dwelly to the service vendor.'),

                                        TextInput::make('total_cost')
                                            ->label(function (Get $get) {
                                                $type = $get('payer_type');
                                                $isSingleInvoiced = in_array($type, [
                                                    PayerType::DWELLY_INVOICE_OWNER->value,
                                                    PayerType::DWELLY_INVOICE_TENANT->value,
                                                    PayerType::DWELLY_INVOICE_OWNER,
                                                    PayerType::DWELLY_INVOICE_TENANT,
                                                ]);
                                                return $isSingleInvoiced ? 'Customer Billed Amount (₹)' : 'Total Maintenance Cost (₹)';
                                            })
                                            ->numeric()
                                            ->prefix('₹')
                                            ->default(0.00)
                                            ->required()
                                            ->reactive()
                                            ->helperText(function (Get $get) {
                                                $type = $get('payer_type');
                                                $isSingleInvoiced = in_array($type, [
                                                    PayerType::DWELLY_INVOICE_OWNER->value,
                                                    PayerType::DWELLY_INVOICE_TENANT->value,
                                                    PayerType::DWELLY_INVOICE_OWNER,
                                                    PayerType::DWELLY_INVOICE_TENANT,
                                                ]);

                                                if (!$isSingleInvoiced) {
                                                    return 'Total maintenance cost to be split or recorded.';
                                                }

                                                $billed = (float) ($get('total_cost') ?? 0);
                                                $vendor = (float) ($get('vendor_cost') ?? 0);
                                                $margin = $billed - $vendor;
                                                $formatted = number_format($margin, 2);
                                                return "Amount charged to customer. Dwelly Margin: ₹{$formatted}";
                                            }),

                                        Grid::make(3)
                                            ->visible(fn (Get $get) => $get('payer_type') === PayerType::DWELLY_INVOICE_SPLIT->value || $get('payer_type') === PayerType::DWELLY_INVOICE_SPLIT)
                                            ->schema([
                                                TextInput::make('owner_amount')
                                                    ->label('Owner Share (₹)')
                                                    ->numeric()
                                                    ->prefix('₹')
                                                    ->default(0.00)
                                                    ->required()
                                                    ->reactive()
                                                    ->helperText('Amount to invoice the Property Owner.'),

                                                TextInput::make('tenant_amount')
                                                    ->label('Tenant Share (₹)')
                                                    ->numeric()
                                                    ->prefix('₹')
                                                    ->default(0.00)
                                                    ->required()
                                                    ->reactive()
                                                    ->helperText('Amount to invoice the Tenant.'),

                                                TextInput::make('dwelly_amount')
                                                    ->label('Dwelly Share (₹)')
                                                    ->numeric()
                                                    ->prefix('₹')
                                                    ->default(0.00)
                                                    ->required()
                                                    ->reactive()
                                                    ->helperText('Amount absorbed internally by Dwelly.'),
                                            ]),

                                        TextInput::make('direct_payment_reference')
                                            ->label('Direct Payment Receipt / Ref #')
                                            ->required()
                                            ->visible(fn (Get $get) => in_array($get('payer_type'), [PayerType::OWNER_DIRECT->value, PayerType::TENANT_DIRECT->value, PayerType::OWNER_DIRECT, PayerType::TENANT_DIRECT]))
                                            ->placeholder('e.g. UPI Ref / Cash Receipt #'),

                                        Textarea::make('direct_payment_notes')
                                            ->label('Direct Payment Notes')
                                            ->required()
                                            ->visible(fn (Get $get) => in_array($get('payer_type'), [PayerType::OWNER_DIRECT->value, PayerType::TENANT_DIRECT->value, PayerType::OWNER_DIRECT, PayerType::TENANT_DIRECT]))
                                            ->rows(2)
                                            ->columnSpanFull(),

                                        SpatieMediaLibraryFileUpload::make('direct_payment_receipts')
                                            ->collection('direct_payment_receipts')
                                            ->multiple()
                                            ->required()
                                            ->label('Payment Receipts / Vendor Invoices')
                                            ->columnSpanFull(),
                                    ]),
                            ]),

                        Tab::make('Verification Audit')
                            ->icon('heroicon-o-clipboard-document-check')
                            ->visible(fn (string $operation) => $operation !== 'create')
                            ->schema([
                                Section::make('Linked Audit & Document References')
                                    ->columns(2)
                                    ->headerActions([
                                        Action::make('triggerAudit')
                                            ->label('Trigger Post-Repair Audit')
                                            ->icon('heroicon-o-clipboard-document-check')
                                            ->color('info')
                                            ->requiresConfirmation()
                                            ->modalHeading('Trigger Verification Audit')
                                            ->modalDescription('This will create a post-repair Audit containing all selected items from this request.')
                                            ->hidden(fn ($record) => filled($record?->triggered_audit_id))
                                            ->action(function ($record, $livewire) {
                                                if (!$record) return;

                                                $service = app(MaintenanceAuditTriggerService::class);
                                                $errors = $service->validateForAuditTrigger($record);

                                                if (!empty($errors)) {
                                                    $bulletList = implode("<br>&bull; ", $errors);
                                                    Notification::make()
                                                        ->title('Cannot Trigger Verification Audit')
                                                        ->body(new HtmlString("Please complete all mandatory information across the tabs:<br>&bull; {$bulletList}"))
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
                                                            '<div style="font-size: 13px; color: rgba(128, 128, 128, 0.85); margin-top: 4px;">Complete all mandatory ticket details and click the blue <strong>"Trigger Post-Repair Audit"</strong> button in the section header above to generate an inspection audit.</div>' .
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
                                                        '<a href="' . e($editUrl) . '" target="_blank" style="display: inline-flex; align-items: center; padding: 8px 14px; background-color: rgba(37, 99, 235, 0.1); color: #2563eb; font-weight: 600; font-size: 13px; border-radius: 6px; text-decoration: none;">View Details &rarr;</a>' .
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
