<?php

namespace App\Filament\Resources\Billing\MaintenanceQuotationResource\Schemas;

use App\Domain\Maintenance\Enums\MaintenanceStatus;
use App\Domain\Maintenance\Models\MaintenanceClientQuote;
use App\Domain\Maintenance\Models\MaintenanceClientQuoteItem;
use App\Domain\Maintenance\Models\MaintenanceRequest;
use App\Domain\Maintenance\Models\MaintenanceRequestItem;
use App\Domain\Maintenance\Models\MaintenanceVendorQuote;
use App\Domain\Maintenance\Services\MaintenanceBillingService;
use App\Domain\Maintenance\Services\MaintenanceQuotationPdfService;
use App\Domain\Maintenance\Services\MaintenanceWorkOrderPdfService;
use App\Domain\Party\Enums\VendorOnboardingStatus;
use App\Domain\Party\Models\Party;
use App\Domain\Party\Models\VendorTrade;
use App\Domain\Party\Services\PartyService;
use App\Domain\Shared\Services\SettingService;
use App\Filament\Resources\Operations\AuditResource;
use App\Filament\Resources\Operations\MaintenanceRequestResource;
use App\Filament\Resources\Parties\PartyResource;
use App\Filament\Resources\Properties\PropertyResource;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class MaintenanceQuotationForm
{
    /**
     * Default schema configuration (falls back to vendor quotes)
     */
    public static function configure(Schema $schema): Schema
    {
        return static::configureVendorQuotesForm($schema);
    }

    /**
     * Page 1: Multi-Vendor Bids & Trade Estimates
     */
    public static function configureVendorQuotesForm(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('📋 Multi-Vendor Bids & Trade Estimates')
                ->description('Collect, compare, and upload estimates from trade contractors for the ticket defect items.')
                ->columnSpanFull()
                ->schema([
                    Repeater::make('vendorQuotes')
                        ->relationship('vendorQuotes')
                        ->columns(3)
                        ->defaultItems(0)
                        ->disabled(fn ($record) => $record?->status === 'approved')
                        ->addable(fn ($record) => $record?->status !== 'approved')
                        ->deletable(fn ($record) => $record?->status !== 'approved')
                        ->reorderable(fn ($record) => $record?->status !== 'approved')
                        ->schema([
                            Select::make('maintenance_request_item_ids')
                                ->label('Target Defect Items')
                                ->placeholder('Select Defect Items from Ticket')
                                ->multiple()
                                ->required()
                                ->minItems(1)
                                ->options(function (Get $get, $record, $livewire) {
                                    $ticketId = $record?->maintenance_request_id
                                        ?? $livewire->record?->maintenance_request_id
                                        ?? $get('maintenance_request_id');

                                    if (! $ticketId) {
                                        return [];
                                    }

                                    $items = MaintenanceRequestItem::where('maintenance_request_id', $ticketId)
                                        ->with(['itemable'])
                                        ->get();

                                    return $items->mapWithKeys(function ($item) {
                                        $name = '';
                                        if ($item->itemable instanceof \App\Domain\Property\Models\PropertyRoom) {
                                            $name = '🚪 '.($item->itemable->custom_name ?: ($item->itemable->roomDefinition?->name ?? "Room #{$item->itemable->id}"));
                                        } elseif ($item->itemable instanceof \App\Domain\Property\Models\PropertyInventory) {
                                            $name = '📦 '.($item->itemable->inventoryType?->name ?? "Item #{$item->itemable->id}");
                                        } elseif ($item->itemable instanceof \App\Domain\Property\Models\PropertyUtility) {
                                            $name = '⚡ '.($item->itemable->utilityType?->name ?? "Utility #{$item->itemable->id}");
                                        } else {
                                            $name = '🏢 General Property Area';
                                        }

                                        $desc = $item->issue_description ? ' - '.Str::limit($item->issue_description, 35) : '';
                                        $action = $item->repair_action ? " [{$item->repair_action}]" : '';

                                        return [$item->id => "{$name}{$desc}{$action}"];
                                    });
                                })
                                ->searchable()
                                ->preload()
                                ->reactive()
                                ->afterStateUpdated(function ($state, Set $set, Get $get, $record, $livewire) {
                                    $ticketId = $record?->maintenance_request_id
                                        ?? $livewire->record?->maintenance_request_id
                                        ?? $get('maintenance_request_id');

                                    if (empty($get('trade_title')) && ! empty($state) && $ticketId) {
                                        $firstItem = MaintenanceRequestItem::with(['itemable'])->find($state[0] ?? null);
                                        if ($firstItem) {
                                            $title = $firstItem->repair_action ?: $firstItem->issue_description;
                                            if ($title) {
                                                $set('trade_title', Str::limit($title, 40));
                                            }
                                        }
                                    }
                                })
                                ->columnSpan(1),

                            TextInput::make('trade_title')
                                ->label('Trade Work Title')
                                ->placeholder('e.g. Wall Masonry & Plastering')
                                ->required()
                                ->columnSpan(1),

                            Select::make('vendor_party_id')
                                ->label('Assigned Service Vendor')
                                ->options(function () {
                                    return Party::whereHas('vendorProfile', function ($q) {
                                        $q->where('onboarding_status', VendorOnboardingStatus::VERIFIED->value)
                                            ->orWhere('onboarding_status', VendorOnboardingStatus::VERIFIED);
                                    })
                                        ->with(['vendorProfile.trade'])
                                        ->get()
                                        ->mapWithKeys(function ($party) {
                                            $tradeName = $party->vendorProfile?->trade?->name ?? 'General';

                                            return [$party->id => "{$party->display_name} ({$tradeName})"];
                                        });
                                })
                                ->searchable()
                                ->preload()
                                ->required()
                                ->createOptionAction(fn ($action) => $action
                                    ->modalHeading('Quick Create Vendor / Artisan')
                                    ->modalDescription('Add an individual artisan or vendor organization to immediately assign to this estimate.')
                                    ->modalWidth('lg')
                                )
                                ->createOptionForm([
                                    TextInput::make('display_name')
                                        ->label('Vendor / Artisan Name')
                                        ->placeholder('e.g. Ramesh Kumar (Plumber) / Apex Services')
                                        ->required(),

                                    Select::make('party_type')
                                        ->label('Vendor Type')
                                        ->options([
                                            'individual' => '👤 Individual Artisan / Technician',
                                            'organization' => '🏢 Contractor / Agency / Company',
                                        ])
                                        ->default('individual')
                                        ->required(),

                                    Select::make('vendor_trade_id')
                                        ->label('Primary Trade / Skill')
                                        ->options(fn () => VendorTrade::where('is_active', true)->pluck('name', 'id'))
                                        ->searchable()
                                        ->preload()
                                        ->nullable(),

                                    TextInput::make('phone')
                                        ->label('Phone / Contact Number')
                                        ->tel()
                                        ->placeholder('e.g. +91 9876543210')
                                        ->nullable(),

                                    TextInput::make('email')
                                        ->label('Email Address')
                                        ->email()
                                        ->placeholder('Optional email')
                                        ->nullable(),

                                    TextInput::make('pan')
                                        ->label('PAN / Tax ID (Optional)')
                                        ->placeholder('e.g. ABCDE1234F')
                                        ->nullable(),

                                    TextInput::make('gstin')
                                        ->label('GSTIN Number (Optional)')
                                        ->placeholder('e.g. 29ABCDE1234F1Z5')
                                        ->nullable(),
                                ])
                                ->createOptionUsing(function (array $data) {
                                    $isOrg = ($data['party_type'] ?? 'individual') === 'organization';

                                    $party = app(PartyService::class)->createParty([
                                        'party_type' => $data['party_type'] ?? 'individual',
                                        'display_name' => $data['display_name'],
                                        'phone' => $data['phone'] ?? null,
                                        'email' => $data['email'] ?? null,
                                        'is_tax_registered' => filled($data['gstin'] ?? null),
                                        'individual_data' => ! $isOrg ? array_filter([
                                            'name' => $data['display_name'],
                                            'pan_number' => $data['pan'] ?? null,
                                            'gstin' => $data['gstin'] ?? null,
                                        ]) : [],
                                        'organization_data' => $isOrg ? array_filter([
                                            'legal_name' => $data['display_name'],
                                            'pan' => $data['pan'] ?? null,
                                            'gstin' => $data['gstin'] ?? null,
                                        ]) : [],
                                        'vendor_data' => [
                                            'vendor_trade_id' => $data['vendor_trade_id'] ?? null,
                                            'onboarding_status' => VendorOnboardingStatus::VERIFIED->value,
                                            'is_preferred' => true,
                                            'verification_notes' => 'Quick-created from Maintenance Quotation',
                                        ],
                                    ], ['vendor']);

                                    return $party->id;
                                })
                                ->columnSpan(1),

                            TextInput::make('vendor_quote_number')
                                ->label('Vendor Quote / Estimate Ref #')
                                ->placeholder('e.g. EST-2026-081 (Optional)')
                                ->nullable()
                                ->columnSpan(1),

                            DatePicker::make('vendor_quote_date')
                                ->label('Quotation Date')
                                ->default(now())
                                ->required()
                                ->columnSpan(1),

                            TextInput::make('quoted_cost')
                                ->label('Vendor Quoted Cost (₹)')
                                ->numeric()
                                ->prefix('₹')
                                ->required()
                                ->live(debounce: 500)
                                ->columnSpan(1),

                            SpatieMediaLibraryFileUpload::make('vendor_quote_files')
                                ->collection('vendor_quote_files')
                                ->label('Vendor Official Quotation PDF / Estimate Sheet')
                                ->multiple()
                                ->openable()
                                ->downloadable()
                                ->previewable()
                                ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png', 'image/webp'])
                                ->maxSize(10240)
                                ->helperText('Attach official contractor quote, rate card, or estimate screenshot.')
                                ->columnSpanFull(),
                        ]),
                ]),
        ]);
    }

    /**
     * Page 2: Client Quotation Builder & Pricing
     */
    public static function configurePricingForm(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('💵 Quotation Pricing & Margins')
                ->description('Configure Dwelly margin markup %, tax rates, validity date, and view financial summary.')
                ->columnSpanFull()
                ->schema([
                    Grid::make(3)
                        ->schema([
                            TextInput::make('margin_percentage')
                                ->label('Dwelly Coordination / Margin Markup (%)')
                                ->numeric()
                                ->suffix('%')
                                ->default(fn () => (float) SettingService::get('financials.default_margin_percentage', 10.00))
                                ->afterStateHydrated(function (TextInput $component, $state) {
                                    if (blank($state)) {
                                        $component->state((float) SettingService::get('financials.default_margin_percentage', 10.00));
                                    }
                                })
                                ->live(debounce: 500)
                                ->disabled(fn ($record) => $record?->status === 'approved')
                                ->afterStateUpdated(fn (Get $get, Set $set) => static::recalculateQuotationTotals($get, $set))
                                ->columnSpan(1),

                            TextInput::make('gst_percentage')
                                ->label('GST / Tax Rate (%)')
                                ->numeric()
                                ->suffix('%')
                                ->default(fn () => (float) SettingService::get('financials.default_gst_percentage', 18.00))
                                ->afterStateHydrated(function (TextInput $component, $state) {
                                    if (blank($state)) {
                                        $component->state((float) SettingService::get('financials.default_gst_percentage', 18.00));
                                    }
                                })
                                ->live(debounce: 500)
                                ->disabled(fn ($record) => $record?->status === 'approved')
                                ->afterStateUpdated(fn (Get $get, Set $set) => static::recalculateQuotationTotals($get, $set))
                                ->columnSpan(1),

                            DatePicker::make('valid_until')
                                ->label('Quotation Valid Until')
                                ->default(fn () => now()->addDays((int) SettingService::get('financials.default_quotation_validity_days', 14)))
                                ->afterStateHydrated(function (DatePicker $component, $state) {
                                    if (blank($state)) {
                                        $component->state(now()->addDays((int) SettingService::get('financials.default_quotation_validity_days', 14))->format('Y-m-d'));
                                    }
                                })
                                ->required()
                                ->disabled(fn ($record) => $record?->status === 'approved')
                                ->columnSpan(1),
                        ]),

                    // Financial Summary Card
                    Placeholder::make('financial_summary_card')
                        ->label('')
                        ->columnSpanFull()
                        ->content(function (Get $get, $record) {
                            $subtotal = (float) ($get('subtotal_amount') ?? $record?->subtotal_amount ?? 0);
                            $margin = (float) ($get('margin_amount') ?? $record?->margin_amount ?? 0);
                            $marginPct = (float) ($get('margin_percentage') ?? $record?->margin_percentage ?? SettingService::get('financials.default_margin_percentage', 10));
                            $tax = (float) ($get('tax_amount') ?? $record?->tax_amount ?? 0);
                            $taxPct = (float) ($get('gst_percentage') ?? $record?->gst_percentage ?? SettingService::get('financials.default_gst_percentage', 18));
                            $total = (float) ($get('total_amount') ?? $record?->total_amount ?? 0);

                            return new HtmlString('
                                <div style="background-color: rgba(37, 99, 235, 0.04); border: 1px solid rgba(37, 99, 235, 0.2); border-radius: 8px; padding: 18px; margin-top: 10px;">
                                    <div style="font-weight: 700; font-size: 15px; margin-bottom: 12px; color: #1e3a8a;">📊 Quotation Financial Breakdown</div>
                                    <div style="display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 16px; font-size: 13px;">
                                        <div>
                                            <span style="color: gray;">Vendor / Base Cost:</span><br>
                                            <strong style="font-size: 16px;">₹'.number_format($subtotal, 2).'</strong>
                                        </div>
                                        <div>
                                            <span style="color: gray;">Dwelly Markup ('.$marginPct.'%):</span><br>
                                            <strong style="font-size: 16px; color: #16a34a;">+ ₹'.number_format($margin, 2).'</strong>
                                        </div>
                                        <div>
                                            <span style="color: gray;">GST / Tax ('.$taxPct.'%):</span><br>
                                            <strong style="font-size: 16px; color: #6b7280;">+ ₹'.number_format($tax, 2).'</strong>
                                        </div>
                                        <div style="background-color: rgba(37, 99, 235, 0.1); padding: 8px 12px; border-radius: 6px; border-left: 4px solid #2563eb;">
                                            <span style="color: #1e40af; font-weight: 600;">Total Client Quotation:</span><br>
                                            <strong style="font-size: 20px; color: #1e3a8a;">₹'.number_format($total, 2).'</strong>
                                        </div>
                                    </div>
                                </div>
                            ');
                        }),
                ]),

            Section::make('📝 Itemized Line Items')
                ->description('Individual items, materials, and Dwelly service fees presented on the formal customer quotation.')
                ->columnSpanFull()
                ->headerActions([
                    Action::make('importFromVendorQuotes')
                        ->label('Import Vendor Quotes')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('info')
                        ->button()
                        ->size('sm')
                        ->visible(fn ($record) => $record?->status !== 'approved')
                        ->action(function (Get $get, Set $set, $record, $livewire) {
                            $ticketId = $record?->maintenance_request_id
                                ?? $livewire->record?->maintenance_request_id
                                ?? $get('maintenance_request_id');
                            $quotes = [];

                            if ($ticketId) {
                                $dbQuotes = MaintenanceVendorQuote::where('maintenance_request_id', $ticketId)->get();
                                foreach ($dbQuotes as $q) {
                                    $targetItemId = null;
                                    if (! empty($q->maintenance_request_item_ids) && is_array($q->maintenance_request_item_ids)) {
                                        $targetItemId = $q->maintenance_request_item_ids[0] ?? null;
                                    } elseif ($q->maintenance_request_item_id) {
                                        $targetItemId = $q->maintenance_request_item_id;
                                    }

                                    if (! $targetItemId) {
                                        $ticketItemCount = MaintenanceRequestItem::where('maintenance_request_id', $ticketId)->count();
                                        if ($ticketItemCount === 1) {
                                            $targetItemId = MaintenanceRequestItem::where('maintenance_request_id', $ticketId)->value('id');
                                        }
                                    }

                                    $quotes[] = [
                                        'vendor_quote_id' => $q->id,
                                        'maintenance_request_item_id' => $targetItemId,
                                        'description' => $q->trade_title ?: 'Trade Work',
                                        'quantity' => 1,
                                        'unit' => 'job',
                                        'unit_rate' => (float) $q->quoted_cost,
                                        'unit_price' => (float) $q->quoted_cost,
                                        'total_cost' => (float) $q->quoted_cost,
                                        'total_price' => (float) $q->quoted_cost,
                                    ];
                                }
                            }

                            if (! empty($quotes)) {
                                $currentItems = $get('items') ?? [];
                                $filteredCurrentItems = array_filter($currentItems, function ($it) {
                                    return ! empty($it['description']) || ! empty($it['unit_rate']) || ! empty($it['total_cost']) || ! empty($it['maintenance_request_item_id']);
                                });
                                $set('items', array_values(array_merge($filteredCurrentItems, $quotes)));

                                static::recalculateQuotationTotals($get, $set);

                                Notification::make()
                                    ->title('Line Items Imported')
                                    ->body(count($quotes).' vendor trade quote items added to the line items table.')
                                    ->success()
                                    ->send();
                            } else {
                                Notification::make()
                                    ->title('No Vendor Quotes Found')
                                    ->body('Please add contractor estimates in Tab 1 first.')
                                    ->warning()
                                    ->send();
                            }
                        }),
                ])
                ->schema([
                    Repeater::make('items')
                        ->relationship('items')
                        ->columns(6)
                        ->defaultItems(1)
                        ->disabled(fn ($record) => $record?->status === 'approved')
                        ->addable(fn ($record) => $record?->status !== 'approved')
                        ->deletable(fn ($record) => $record?->status !== 'approved')
                        ->reorderable(fn ($record) => $record?->status !== 'approved')
                        ->schema([
                            Select::make('maintenance_request_item_id')
                                ->label('Defect Item')
                                ->placeholder('Map to Defect Item')
                                ->options(function (Get $get, $record, $livewire) {
                                    $ticketId = $record?->maintenance_request_id
                                        ?? $livewire->record?->maintenance_request_id
                                        ?? $get('maintenance_request_id');

                                    if (! $ticketId) {
                                        return [];
                                    }

                                    return MaintenanceRequestItem::where('maintenance_request_id', $ticketId)
                                        ->with(['itemable'])
                                        ->get()
                                        ->mapWithKeys(function ($item) {
                                            $name = '';
                                            if ($item->itemable instanceof \App\Domain\Property\Models\PropertyRoom) {
                                                $name = '🚪 '.($item->itemable->custom_name ?: ($item->itemable->roomDefinition?->name ?? "Room #{$item->itemable->id}"));
                                            } elseif ($item->itemable instanceof \App\Domain\Property\Models\PropertyInventory) {
                                                $name = '📦 '.($item->itemable->inventoryType?->name ?? "Item #{$item->itemable->id}");
                                            } elseif ($item->itemable instanceof \App\Domain\Property\Models\PropertyUtility) {
                                                $name = '⚡ '.($item->itemable->utilityType?->name ?? "Utility #{$item->itemable->id}");
                                            } else {
                                                $name = '🏢 General Property Area';
                                            }

                                            return [$item->id => "{$name} - ".Str::limit($item->issue_description, 25)];
                                        });
                                })
                                ->searchable()
                                ->preload()
                                ->nullable()
                                ->columnSpan(2),

                            TextInput::make('description')
                                ->label('Line Description / Scope')
                                ->placeholder('e.g. Wall Plaster & Primer Coating (Materials + Labor)')
                                ->required()
                                ->columnSpan(2),

                            TextInput::make('quantity')
                                ->label('Qty')
                                ->numeric()
                                ->default(1)
                                ->required()
                                ->live(debounce: 500)
                                ->afterStateUpdated(function (Get $get, Set $set) {
                                    $qty = (float) ($get('quantity') ?? 1);
                                    $rate = (float) ($get('unit_rate') ?? 0);
                                    $set('total_cost', round($qty * $rate, 2));
                                    static::recalculateQuotationTotals($get, $set);
                                })
                                ->columnSpan(1),

                            TextInput::make('unit_rate')
                                ->label('Unit Rate (₹)')
                                ->numeric()
                                ->prefix('₹')
                                ->required()
                                ->live(debounce: 500)
                                ->afterStateUpdated(function (Get $get, Set $set) {
                                    $qty = (float) ($get('quantity') ?? 1);
                                    $rate = (float) ($get('unit_rate') ?? 0);
                                    $set('total_cost', round($qty * $rate, 2));
                                    static::recalculateQuotationTotals($get, $set);
                                })
                                ->columnSpan(1),
                        ])
                        ->columnSpanFull(),
                ]),

            // PDF Preview & Generation Card
            Section::make('📄 Official Client Quotation PDF')
                ->description('Compile, preview, and download the official PDF estimate presented to the customer.')
                ->columnSpanFull()
                ->headerActions([
                    Action::make('generateQuotationInPdfCard')
                        ->label('Generate Quotation')
                        ->icon('heroicon-o-document-arrow-down')
                        ->color('primary')
                        ->button()
                        ->size('sm')
                        ->action(function ($record, $livewire) {
                            if (! $record) {
                                return;
                            }

                            try {
                                app(MaintenanceQuotationPdfService::class)->generatePdf($record);

                                Notification::make()
                                    ->title('Quotation PDF Generated')
                                    ->body('Official Client Quotation PDF has been generated.')
                                    ->success()
                                    ->send();

                                $livewire->refreshFormData(['quote_pdf']);
                            } catch (\Throwable $e) {
                                Notification::make()
                                    ->title('Quotation Generation Failed')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        }),
                ])
                ->schema([
                    Placeholder::make('quote_pdf_viewer')
                        ->label('')
                        ->columnSpanFull()
                        ->content(function ($record) {
                            if (! $record) {
                                return new HtmlString('<div style="font-size: 13px; color: gray;">Save quotation to generate official PDF.</div>');
                            }

                            $media = $record->getFirstMedia('quote_pdf');
                            if (! $media || ! file_exists($media->getPath())) {
                                return new HtmlString('
                                    <div style="padding: 16px; background-color: rgba(245, 158, 11, 0.08); border: 1px dashed #f59e0b; border-radius: 8px; font-size: 13px; color: #b45309;">
                                        ⚠️ <strong>PDF Not Generated:</strong> Click the "Generate Quotation" button above to generate the official branded quotation PDF.
                                    </div>
                                ');
                            }

                            $pdfUrl = route('filament.admin.resources.billing.maintenance-quotations.pdf', ['record' => $record->id]);

                            return new HtmlString('
                                <div style="border: 1px solid rgba(0,0,0,0.1); border-radius: 8px; overflow: hidden; background: #525659;">
                                    <div style="padding: 8px 14px; background: #323639; color: white; display: flex; justify-content: space-between; align-items: center; font-size: 12px;">
                                        <span>📄 <strong>Official Client Quotation PDF</strong> ('.$media->file_name.')</span>
                                        <a href="'.$pdfUrl.'" target="_blank" download style="color: #60a5fa; font-weight: 600; text-decoration: none;">Download PDF &darr;</a>
                                    </div>
                                    <iframe src="'.$pdfUrl.'#toolbar=1&navpanes=0" style="width: 100%; height: 500px; border: none;"></iframe>
                                </div>
                            ');
                        }),
                ]),
        ]);
    }

    /**
     * Page 3: Client Approval & Decision
     */
    public static function configureApprovalForm(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('✅ Client Approval & Decision (Mandatory for Work Authorization)')
                ->description('Enter customer authorization remarks and upload approval proof files directly below, then confirm approval.')
                ->columnSpanFull()
                ->headerActions([
                    Action::make('approveQuoteInTab')
                        ->label('Confirm Quotation Approval')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->button()
                        ->size('sm')
                        ->visible(fn ($record) => $record && in_array($record->status, ['draft', 'pending_approval']))
                        ->requiresConfirmation()
                        ->modalHeading('Approve Client Quotation')
                        ->modalDescription('Are you sure you want to approve this quotation? Please ensure you have entered the approval remarks and uploaded the proof files in this tab.')
                        ->modalSubmitActionLabel('Confirm Approval')
                        ->action(function ($record, $livewire) {
                            $notes = $livewire->data['approval_notes'] ?? $record->approval_notes;

                            if (blank($notes) || ! $record->hasMedia('approval_proof_files')) {
                                Notification::make()
                                    ->title('Approval Details Required')
                                    ->body('Please enter the Approval Confirmation Remarks and upload at least one Approval Proof document in this tab before confirming approval.')
                                    ->warning()
                                    ->persistent()
                                    ->send();

                                return;
                            }

                            $billingService = app(MaintenanceBillingService::class);
                            $billingService->recordClientApproval($record, [
                                'approved_by_type' => $livewire->data['approved_by_type'] ?? 'owner',
                                'approval_channel' => $livewire->data['approval_channel'] ?? 'written',
                                'approval_notes' => $notes,
                            ]);

                            Notification::make()
                                ->title('Quotation Approved Successfully')
                                ->body('Client quotation is approved! You may now award winning contractor quote(s) and issue official Work Orders in the Work Orders tab.')
                                ->success()
                                ->send();

                            $livewire->redirect(
                                \App\Filament\Resources\Billing\MaintenanceQuotationResource::getUrl('work-orders', ['record' => $record])
                            );
                        }),
                ])
                ->schema([
                    Grid::make(3)
                        ->schema([
                            Select::make('status')
                                ->label('Quotation Status')
                                ->options([
                                    'draft' => '📝 Draft',
                                    'pending_approval' => '⏳ Pending Client Approval',
                                    'approved' => '✅ Client Approved',
                                    'rejected' => '❌ Rejected by Client',
                                    'settled' => '💳 Settled & Paid',
                                ])
                                ->default('draft')
                                ->disabled()
                                ->columnSpan(1),

                            Select::make('approved_by_type')
                                ->label('Approving Party')
                                ->options([
                                    'owner' => 'Owner',
                                    'tenant' => 'Tenant',
                                    'dwelly' => 'Dwelly Internal Management',
                                ])
                                ->default('owner')
                                ->disabled(fn ($record) => $record?->status === 'approved')
                                ->columnSpan(1),

                            Select::make('approval_channel')
                                ->label('Approval Method / Channel')
                                ->options([
                                    'whatsapp' => '💬 WhatsApp Confirmation',
                                    'email' => '📧 Email Approval',
                                    'written' => '✍️ Physical / Signed Estimate',
                                    'portal' => '🌐 Customer Portal',
                                    'verbal' => '📞 Phone Call / Verbal Confirmation',
                                ])
                                ->default('whatsapp')
                                ->disabled(fn ($record) => $record?->status === 'approved')
                                ->columnSpan(1),
                        ]),

                    Grid::make(2)
                        ->schema([
                            DatePicker::make('approved_at')
                                ->label('Approval Date & Time')
                                ->default(now())
                                ->disabled(fn ($record) => $record?->status === 'approved')
                                ->columnSpan(1),

                            Textarea::make('approval_notes')
                                ->label('Approval Confirmation Remarks')
                                ->placeholder('e.g. Approved via WhatsApp message from Owner Mr. Ramesh on 19-Aug-2026. Authorized full scope.')
                                ->rows(3)
                                ->disabled(fn ($record) => $record?->status === 'approved')
                                ->columnSpan(1),
                        ]),

                    SpatieMediaLibraryFileUpload::make('approval_proof_files')
                        ->collection('approval_proof_files')
                        ->label('📎 Client Approval Proof (Screenshot, Email PDF, or Signed Document)')
                        ->multiple()
                        ->openable()
                        ->downloadable()
                        ->previewable()
                        ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png', 'image/webp'])
                        ->maxSize(10240)
                        ->disabled(fn ($record) => $record?->status === 'approved')
                        ->helperText('Upload screenshot of WhatsApp message, signed quotation scan, or client email approval.')
                        ->columnSpanFull(),

                    Textarea::make('rejection_reason')
                        ->label('Rejection Reason (If Client Declined)')
                        ->rows(2)
                        ->disabled()
                        ->visible(fn ($record) => $record?->status === 'rejected')
                        ->columnSpanFull(),
                ]),
        ]);
    }

    /**
     * Page 4: Contractor Work Orders & Awarding
     */
    public static function configureWorkOrdersForm(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('🛠 Contractor Work Orders & Awarding')
                ->description('Select winning contractor quotations below and click "Issue Work Order(s)" to authorize on-site repair work.')
                ->columnSpanFull()
                ->headerActions([
                    Action::make('issueWorkOrderInTab')
                        ->label('Issue Work Order(s)')
                        ->icon('heroicon-o-document-check')
                        ->color('primary')
                        ->button()
                        ->size('sm')
                        ->visible(fn ($record) => $record && $record->status === 'approved' && empty($record->awarded_vendor_quote_ids))
                        ->requiresConfirmation()
                        ->modalHeading('Issue Contractor Work Order(s)')
                        ->modalDescription('Confirm issuance of official Work Orders for the contractor quotation(s) selected in this tab. This will generate work order reference numbers and authorize technicians for on-site work.')
                        ->modalSubmitActionLabel('Confirm & Issue Work Orders')
                        ->action(function ($record, $livewire) {
                            $selectedIds = $livewire->data['awarded_vendor_quote_ids'] ?? $record->awarded_vendor_quote_ids ?? [];
                            $selectedIds = (array) $selectedIds;

                            if (empty($selectedIds)) {
                                Notification::make()
                                    ->title('Selection Required')
                                    ->body('Please check at least one vendor quote in the list below before issuing work orders.')
                                    ->warning()
                                    ->send();

                                return;
                            }

                            $billingService = app(MaintenanceBillingService::class);
                            $billingService->awardVendorQuotesAndIssueWorkOrders($record, $selectedIds);

                            Notification::make()
                                ->title('Work Orders Issued Successfully')
                                ->body(count($selectedIds).' contractor work order(s) generated. Operational ticket has been advanced to Vendor Assigned / In Progress.')
                                ->success()
                                ->send();

                            $livewire->refreshFormData(['awarded_vendor_quote_ids']);
                        }),
                ])
                ->schema([
                    Placeholder::make('work_order_gate_check')
                        ->label('')
                        ->columnSpanFull()
                        ->content(function ($record) {
                            if (! $record || $record->status !== 'approved') {
                                return new HtmlString('
                                    <div style="padding: 14px 18px; background-color: rgba(239, 68, 68, 0.06); border-left: 4px solid #ef4444; border-radius: 4px; font-size: 13px; color: #991b1b;">
                                        🔒 <strong>Work Orders Locked:</strong> Client quotation must be approved in Tab 3 (Client Approval) before contractor work orders can be awarded and issued.
                                    </div>
                                ');
                            }

                            return null;
                        }),

                    CheckboxList::make('awarded_vendor_quote_ids')
                        ->label('Select Winning Contractor Quotation(s) to Award')
                        ->options(function (Get $get, $record, $livewire) {
                            $ticketId = $record?->maintenance_request_id;
                            if (! $ticketId) {
                                return [];
                            }

                            $quotes = MaintenanceVendorQuote::where('maintenance_request_id', $ticketId)
                                ->with(['vendor', 'vendor.vendorProfile.trade'])
                                ->get();

                            return $quotes->mapWithKeys(function ($q) {
                                $vendorName = $q->vendor?->display_name ?? 'Contractor';
                                $tradeName = $q->vendor?->vendorProfile?->trade?->name ?? 'Trade';
                                $cost = number_format((float) $q->quoted_cost, 2);
                                $ref = $q->vendor_quote_number ? " [Ref: {$q->vendor_quote_number}]" : '';

                                return [
                                    $q->id => "🏆 {$q->trade_title} – {$vendorName} ({$tradeName}) – ₹{$cost}{$ref}",
                                ];
                            });
                        })
                        ->columns(1)
                        ->disabled(fn ($record) => $record && (! empty($record->awarded_vendor_quote_ids) || $record->status !== 'approved'))
                        ->helperText('Award winning bids. Only awarded contractors will be authorized to execute physical repairs.')
                        ->columnSpanFull(),

                    // Work Order Documents Display Card
                    Placeholder::make('work_order_documents_list')
                        ->label('')
                        ->columnSpanFull()
                        ->content(function ($record) {
                            if (! $record || empty($record->awarded_vendor_quote_ids)) {
                                return null;
                            }

                            $awardedIds = (array) $record->awarded_vendor_quote_ids;
                            $quotes = MaintenanceVendorQuote::whereIn('id', $awardedIds)->with(['vendor', 'media'])->get();

                            if ($quotes->isEmpty()) {
                                return null;
                            }

                            $html = '<div style="margin-top: 14px; background: rgba(16, 185, 129, 0.05); border: 1px solid rgba(16, 185, 129, 0.2); border-radius: 8px; padding: 18px;">';
                            $html .= '<div style="font-weight: 700; font-size: 15px; color: #047857; margin-bottom: 12px;">📑 Official Issued Contractor Work Orders</div>';
                            $html .= '<div style="display: flex; flex-direction: column; gap: 10px;">';

                            foreach ($quotes as $q) {
                                $woNumber = $q->work_order_number ?? ("WO-{$record->id}-{$q->id}");
                                $vendorName = e($q->vendor?->display_name ?? 'Contractor');
                                $trade = e($q->trade_title);
                                $cost = number_format((float) $q->quoted_cost, 2);

                                $pdfService = app(MaintenanceWorkOrderPdfService::class);
                                $media = $q->getFirstMedia('work_order_pdf');
                                if (! $media) {
                                    $media = $pdfService->generatePdf($q, $record);
                                }

                                $downloadUrl = $media ? route('filament.admin.resources.billing.maintenance-quotations.work-order-pdf', ['record' => $record->id, 'vendorQuoteId' => $q->id]) : '#';

                                $html .= '<div style="display: flex; align-items: center; justify-content: space-between; background: white; padding: 12px 16px; border-radius: 6px; border: 1px solid rgba(0,0,0,0.08);">';
                                $html .= '<div><strong>'.$woNumber.'</strong>: '.$trade.' &mdash; <span style="color: #2563eb;">'.$vendorName.'</span> &mdash; <strong>₹'.$cost.'</strong></div>';
                                $html .= '<div><a href="'.$downloadUrl.'" target="_blank" download style="padding: 6px 12px; background: #2563eb; color: white; border-radius: 4px; text-decoration: none; font-size: 12px; font-weight: 600;">Download Work Order PDF &darr;</a></div>';
                                $html .= '</div>';
                            }

                            $html .= '</div></div>';

                            return new HtmlString($html);
                        }),
                ]),
        ]);
    }

    /**
     * Page 5: Invoicing & Financial Settlement
     */
    public static function configureSettlementForm(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('📊 Accounting Bridge (Vendor Bills & Client Invoices)')
                ->description('Generate and sync accounting documents (Vendor Bills and Client Invoices) with the financial ledger.')
                ->columnSpanFull()
                ->headerActions([
                    Action::make('viewAuditInTab')
                        ->label(fn ($record) => $record?->maintenanceRequest?->triggeredAudit ? ('View Audit #'.$record->maintenanceRequest->triggeredAudit->audit_number) : 'View Verification Audit')
                        ->icon('heroicon-o-clipboard-document-check')
                        ->color('info')
                        ->button()
                        ->size('sm')
                        ->visible(fn ($record) => filled($record?->maintenanceRequest?->triggered_audit_id) && (bool) $record?->maintenanceRequest?->triggeredAudit)
                        ->url(fn ($record) => $record?->maintenanceRequest?->triggeredAudit ? AuditResource::getUrl('inspect', ['record' => $record->maintenanceRequest->triggeredAudit]) : null)
                        ->openUrlInNewTab(),

                    Action::make('viewTicketInTab5')
                        ->label(fn ($record) => $record?->maintenanceRequest ? ('View Ticket #'.$record->maintenanceRequest->ticket_number) : 'View Ticket')
                        ->icon('heroicon-o-arrow-top-right-on-square')
                        ->color('gray')
                        ->button()
                        ->size('sm')
                        ->visible(fn ($record) => filled($record?->maintenance_request_id))
                        ->url(fn ($record) => $record?->maintenanceRequest ? MaintenanceRequestResource::getUrl('edit', ['record' => $record->maintenanceRequest]) : null)
                        ->openUrlInNewTab(),
                ])
                ->schema([
                    Placeholder::make('accounting_summary')
                        ->label('')
                        ->columnSpanFull()
                        ->content(function ($record) {
                            if (! $record) {
                                return null;
                            }

                            $ticket = $record->maintenanceRequest;
                            $payer = $ticket?->payer_type?->getLabel() ?? ucfirst((string) ($ticket?->payer_type ?? 'N/A'));
                            $totalClient = number_format((float) $record->total_amount, 2);
                            $subtotalVendor = number_format((float) $record->subtotal_amount, 2);
                            $margin = number_format((float) $record->margin_amount, 2);
                            $isDirect = (bool) ($ticket?->is_direct_vendor ?? false);

                            return new HtmlString('
                                <div style="display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 16px; margin-bottom: 12px;">
                                    <div style="background: white; border: 1px solid rgba(0,0,0,0.1); border-radius: 8px; padding: 14px;">
                                        <span style="color: gray; font-size: 11px;">Receivable / Client Invoice</span><br>
                                        <strong style="font-size: 18px; color: #1e40af;">₹'.$totalClient.'</strong><br>
                                        <span style="font-size: 11px; color: #3b82f6;">Payer: '.$payer.'</span>
                                    </div>
                                    <div style="background: white; border: 1px solid rgba(0,0,0,0.1); border-radius: 8px; padding: 14px;">
                                        <span style="color: gray; font-size: 11px;">Payable / Vendor Bills</span><br>
                                        <strong style="font-size: 18px; color: #b91c1c;">₹'.$subtotalVendor.'</strong><br>
                                        <span style="font-size: 11px; color: #ef4444;">Route: '.($isDirect ? 'Direct Client Payment' : 'Dwelly Coordinated').'</span>
                                    </div>
                                    <div style="background: white; border: 1px solid rgba(0,0,0,0.1); border-radius: 8px; padding: 14px;">
                                        <span style="color: gray; font-size: 11px;">Net Dwelly Margin</span><br>
                                        <strong style="font-size: 18px; color: #16a34a;">₹'.$margin.'</strong><br>
                                        <span style="font-size: 11px; color: #10b981;">Markup: '.$record->margin_percentage.'%</span>
                                    </div>
                                </div>
                            ');
                        }),
                ]),
        ]);
    }

    /**
     * Top Lifecycle Workflow Header Banner
     */
    public static function getWorkflowHeaderHtml(?MaintenanceClientQuote $record): ?HtmlString
    {
        if (! $record) {
            return null;
        }

        $quoteNumber = $record->quote_number ?? ('QT-'.$record->id);
        $status = $record->status ?? 'draft';
        $ticket = $record->maintenanceRequest;

        $ticketNumber = $ticket?->ticket_number ?? 'N/A';
        $ticketTitle = $ticket?->title ?? 'Maintenance Ticket';
        $ticketUrl = $ticket ? MaintenanceRequestResource::getUrl('edit', ['record' => $ticket]) : '#';

        $property = $ticket?->property;
        $propertyName = $property?->building_name ?? $property?->code ?? ('Property #'.$property?->id);
        $propertyCode = $property?->code;
        $propertyUrl = $property ? PropertyResource::getUrl('edit', ['record' => $property]) : '#';

        $owner = $ticket?->owner;
        $ownerName = $owner?->display_name ?? 'N/A';
        $ownerUrl = $owner ? PartyResource::getUrl('edit', ['record' => $owner]) : null;

        $tenant = $ticket?->tenant;
        $tenantName = $tenant?->display_name ?? 'Vacant / None';
        $tenantUrl = $tenant ? PartyResource::getUrl('edit', ['record' => $tenant]) : null;

        $payerLabel = $ticket?->payer_type?->getLabel() ?? ucfirst((string) ($ticket?->payer_type ?? 'N/A'));

        $totalAmount = number_format((float) ($record->total_amount ?? 0), 2);
        $marginAmount = number_format((float) ($record->margin_amount ?? 0), 2);

        $vendorQuotesCount = $record->vendorQuotes()->count();
        $itemsCount = $record->items()->count();
        $isApproved = in_array($status, ['approved', 'settled', 'invoiced']);
        $hasWorkOrders = ! empty($record->awarded_vendor_quote_ids);

        $s1 = $vendorQuotesCount > 0;
        $s2 = $itemsCount > 0;
        $s3 = $isApproved;
        $s4 = $hasWorkOrders;

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

        $card1 = $getStepStyle($s1, '1. Vendor Estimates', $s1 ? "{$vendorQuotesCount} quotes recorded" : 'Add contractor trade quotes');
        $card2 = $getStepStyle($s2, '2. Client Pricing', $s2 ? "{$itemsCount} items (₹{$totalAmount})" : 'Set client line items & rates');
        $card3 = $getStepStyle($s3, '3. Client Approval', $s3 ? 'Customer approved & signed' : 'Pending client authorization');
        $card4 = $getStepStyle($s4, '4. Work Orders', $s4 ? 'Work order(s) awarded' : 'Award winning vendor quotes');

        $statusColor = match ($status) {
            'approved' => '#10b981',
            'settled' => '#059669',
            'pending_approval' => '#f59e0b',
            'rejected' => '#ef4444',
            default => '#3b82f6',
        };

        $statusLabel = match ($status) {
            'approved' => '✅ Client Approved',
            'settled' => '💳 Settled',
            'pending_approval' => '⏳ Pending Client Approval',
            'rejected' => '❌ Rejected',
            default => '📝 Draft Quotation',
        };

        $ownerLink = $ownerUrl
            ? '<a href="'.e($ownerUrl).'" target="_blank" style="color: #2563eb; text-decoration: none; font-weight: 600;">'.e($ownerName).'</a>'
            : '<span>'.e($ownerName).'</span>';

        $tenantLink = $tenantUrl
            ? '<a href="'.e($tenantUrl).'" target="_blank" style="color: #2563eb; text-decoration: none; font-weight: 600;">'.e($tenantName).'</a>'
            : '<span>'.e($tenantName).'</span>';

        $propertyLink = '<a href="'.e($propertyUrl).'" target="_blank" style="color: #2563eb; text-decoration: none; font-weight: 600;">'.e($propertyName).($propertyCode ? " ({$propertyCode})" : '').'</a>';
        $ticketLink = '<a href="'.e($ticketUrl).'" target="_blank" style="color: #2563eb; text-decoration: none; font-weight: 600;">#'.e($ticketNumber).': '.e($ticketTitle).'</a>';

        $html = '<div style="background-color: var(--fi-section-bg, #ffffff); border: 1px solid var(--fi-section-border, rgba(0,0,0,0.1)); border-radius: 0.75rem; padding: 1.25rem; margin-bottom: 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">';
        $html .= '<div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; margin-bottom: 1rem; border-bottom: 1px solid rgba(128,128,128,0.15); padding-bottom: 0.75rem;">';
        $html .= '<div>';
        $html .= '<span style="font-size: 1.25rem; font-weight: 800; letter-spacing: -0.02em;">Quotation '.e($quoteNumber).'</span>';
        $html .= '<span style="margin-left: 10px; display: inline-block; padding: 3px 10px; border-radius: 9999px; font-size: 12px; font-weight: 700; color: white; background-color: '.$statusColor.';">'.$statusLabel.'</span>';
        $html .= '</div>';
        $html .= '<div style="display: flex; align-items: center; gap: 16px; font-size: 13px;">';
        $html .= '<div><span style="color: gray;">Client Quote:</span> <strong style="font-size: 16px; color: #1e40af;">₹'.$totalAmount.'</strong></div>';
        $html .= '<div><span style="color: gray;">Margin:</span> <strong style="font-size: 16px; color: #16a34a;">₹'.$marginAmount.'</strong></div>';
        $html .= '</div>';
        $html .= '</div>';

        // Context grid
        $html .= '<div style="display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; font-size: 12px; margin-bottom: 1rem;">';
        $html .= '<div><span style="color: gray;">Linked Ticket:</span><br>'.$ticketLink.'</div>';
        $html .= '<div><span style="color: gray;">Property:</span><br>'.$propertyLink.'</div>';
        $html .= '<div><span style="color: gray;">Owner:</span><br>'.$ownerLink.'</div>';
        $html .= '<div><span style="color: gray;">Payer:</span><br><strong style="color: #2563eb;">'.$payerLabel.'</strong> (Tenant: '.$tenantLink.')</div>';
        $html .= '</div>';

        // Progress bar
        $html .= '<div style="margin-bottom: 1rem;">';
        $html .= '<div style="display: flex; justify-content: space-between; font-size: 11px; font-weight: 600; margin-bottom: 4px; color: gray;">';
        $html .= '<span>Quotation Workflow Progress</span>';
        $html .= '<span style="color: '.$progressColor.'; font-weight: 700;">'.$progress.'% Completed</span>';
        $html .= '</div>';
        $html .= '<div style="width: 100%; height: 6px; background-color: rgba(128,128,128,0.15); border-radius: 9999px; overflow: hidden;">';
        $html .= '<div style="width: '.$progress.'%; height: 100%; background-color: '.$progressColor.'; transition: width 0.3s ease;"></div>';
        $html .= '</div>';
        $html .= '</div>';

        // Step cards
        $html .= '<div style="display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 10px;">';
        $html .= $card1.$card2.$card3.$card4;
        $html .= '</div>';

        $html .= '</div>';

        return new HtmlString($html);
    }

    /**
     * Helper to recalculate quotation totals
     */
    protected static function recalculateQuotationTotals(Get $get, Set $set): void
    {
        $items = $get('items') ?? [];
        $subtotal = 0.0;

        foreach ($items as $item) {
            $subtotal += (float) ($item['total_cost'] ?? 0);
        }

        $marginPct = (float) ($get('margin_percentage') ?? SettingService::get('financials.default_margin_percentage', 10));
        $taxPct = (float) ($get('gst_percentage') ?? SettingService::get('financials.default_gst_percentage', 18));

        $marginAmount = round($subtotal * ($marginPct / 100), 2);
        $taxable = $subtotal + $marginAmount;
        $taxAmount = round($taxable * ($taxPct / 100), 2);
        $total = round($taxable + $taxAmount, 2);

        $set('subtotal_amount', $subtotal);
        $set('margin_amount', $marginAmount);
        $set('tax_amount', $taxAmount);
        $set('total_amount', $total);
    }
}
