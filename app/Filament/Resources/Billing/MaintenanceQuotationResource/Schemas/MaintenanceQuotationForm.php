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
use Filament\Forms\Components\Hidden;
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
                        ->disabled(fn ($record) => in_array($record?->status, ['approved', 'archived']))
                        ->addable(fn ($record) => ! in_array($record?->status, ['approved', 'archived']))
                        ->deletable(fn ($record) => ! in_array($record?->status, ['approved', 'archived']))
                        ->reorderable(fn ($record) => ! in_array($record?->status, ['approved', 'archived']))
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
                ->headerActions([
                    Action::make('applyPricingMargin')
                        ->label('Apply Markup to Line Items')
                        ->icon('heroicon-o-calculator')
                        ->color('primary')
                        ->button()
                        ->size('sm')
                        ->requiresConfirmation()
                        ->modalHeading('Apply Margin Markup to Line Items?')
                        ->modalDescription('This will recalculate the Client Unit Price for all line items based on their Vendor Quoted Rate and the current Margin Markup (%). Any custom values manually entered will be overwritten.')
                        ->modalSubmitActionLabel('Yes, Apply & Overwrite')
                        ->visible(fn ($record) => ! in_array($record?->status, ['approved', 'archived']))
                        ->action(function (Get $get, Set $set) {
                            $marginPct = (float) ($get('margin_percentage') ?? SettingService::get('financials.default_margin_percentage', 10.00));
                            $items = $get('items') ?? [];
                            $updatedCount = 0;

                            foreach ($items as $index => $item) {
                                $qty = (float) ($item['quantity'] ?? 1);
                                $vendorCost = (float) ($item['vendor_cost'] ?? 0);
                                if ($vendorCost <= 0 && ! empty($item['vendor_quote_id'])) {
                                    $vendorCost = (float) (MaintenanceVendorQuote::find($item['vendor_quote_id'])?->quoted_cost ?? 0);
                                    $items[$index]['vendor_cost'] = $vendorCost;
                                }

                                if ($vendorCost > 0) {
                                    $newClientUnitPrice = round($vendorCost * (1 + $marginPct / 100), 2);
                                    $newTotal = round($qty * $newClientUnitPrice, 2);
                                    $items[$index]['unit_price'] = $newClientUnitPrice;
                                    $items[$index]['unit_rate'] = $newClientUnitPrice;
                                    $items[$index]['total_price'] = $newTotal;
                                    $items[$index]['total_cost'] = $newTotal;
                                    $updatedCount++;
                                }
                            }

                            $set('items', $items);
                            static::recalculateQuotationTotals($get, $set);

                            Notification::make()
                                ->title('Margin Markup Applied')
                                ->body("Recalculated client prices for {$updatedCount} line items using {$marginPct}% margin markup.")
                                ->success()
                                ->send();
                        }),
                ])
                ->schema([
                    Grid::make(4)
                        ->schema([
                            TextInput::make('margin_percentage')
                                ->label('Dwelly Margin Markup (%)')
                                ->numeric()
                                ->suffix('%')
                                ->default(fn () => (float) SettingService::get('financials.default_margin_percentage', 10.00))
                                ->afterStateHydrated(function (TextInput $component, $state) {
                                    if (blank($state)) {
                                        $component->state((float) SettingService::get('financials.default_margin_percentage', 10.00));
                                    }
                                })
                                ->live(debounce: 500)
                                ->disabled(fn ($record) => in_array($record?->status, ['approved', 'archived']))
                                ->afterStateUpdated(fn (Get $get, Set $set) => static::recalculateQuotationTotals($get, $set))
                                ->columnSpan(1),

                            Select::make('tax_id')
                                ->label('Tax Component / Regime')
                                ->placeholder('Select Tax')
                                ->options(fn () => \Tek2991\Accounting\Models\Tax::where('is_active', true)->pluck('name', 'id'))
                                ->default(fn () => \Tek2991\Accounting\Models\Tax::where('name', 'like', '%18%')->value('id') ?? \Tek2991\Accounting\Models\Tax::first()?->id)
                                ->searchable()
                                ->preload()
                                ->live()
                                ->disabled(fn ($record) => in_array($record?->status, ['approved', 'archived']))
                                ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                    if ($state) {
                                        $tax = \Tek2991\Accounting\Models\Tax::with('components')->find($state);
                                        if ($tax) {
                                            $rate = (float) $tax->components->sum('rate');
                                            $set('gst_percentage', $rate > 0 ? $rate : 18.00);
                                        }
                                    }
                                    static::recalculateQuotationTotals($get, $set);
                                })
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
                                ->disabled(fn ($record) => in_array($record?->status, ['approved', 'archived']))
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
                                ->disabled(fn ($record) => in_array($record?->status, ['approved', 'archived']))
                                ->columnSpan(1),
                        ]),

                    Hidden::make('subtotal_amount')->dehydrated(),
                    Hidden::make('margin_amount')->dehydrated(),
                    Hidden::make('tax_amount')->dehydrated(),
                    Hidden::make('total_amount')->dehydrated(),

                    // Financial Summary Card
                    Placeholder::make('financial_summary_card')
                        ->label('')
                        ->columnSpanFull()
                        ->content(function (Get $get, $record) {
                            $items = $get('items') ?? [];
                            $subtotal = 0.0;
                            $vendorCost = 0.0;

                            if (! empty($items)) {
                                foreach ($items as $item) {
                                    $qty = (float) ($item['quantity'] ?? 1);
                                    $clientRate = (float) ($item['unit_price'] ?? $item['unit_rate'] ?? 0);
                                    $vCost = (float) ($item['vendor_cost'] ?? 0);
                                    $subtotal += round($qty * $clientRate, 2);
                                    $vendorCost += round($qty * $vCost, 2);
                                }
                            } elseif ($record && $record->items()->count() > 0) {
                                foreach ($record->items as $item) {
                                    $qty = (float) ($item->quantity ?? 1);
                                    $clientRate = (float) ($item->unit_price ?? $item->unit_rate ?? 0);
                                    $vCost = (float) ($item->vendor_cost ?? $item->vendorQuote?->quoted_cost ?? 0);
                                    $subtotal += (float) ($item->total_price ?? round($qty * $clientRate, 2));
                                    $vendorCost += round($qty * $vCost, 2);
                                }
                            } else {
                                $subtotal = (float) ($get('subtotal_amount') ?? $record?->subtotal_amount ?? 0);
                            }

                            if ($vendorCost <= 0 && $record) {
                                $vendorCost = (float) $record->vendorQuotes()->sum('quoted_cost');
                            }

                            $marginPct = (float) ($get('margin_percentage') ?? $record?->margin_percentage ?? SettingService::get('financials.default_margin_percentage', 10.00));
                            $taxPct = (float) ($get('gst_percentage') ?? $record?->gst_percentage ?? SettingService::get('financials.default_gst_percentage', 18.00));

                            $margin = ($vendorCost > 0 && $subtotal >= $vendorCost)
                                ? round($subtotal - $vendorCost, 2)
                                : round($subtotal * ($marginPct / 100), 2);

                            $tax = round($subtotal * ($taxPct / 100), 2);
                            $total = round($subtotal + $tax, 2);

                            $taxId = $get('tax_id') ?? $record?->tax_id;
                            $taxModel = $taxId ? \Tek2991\Accounting\Models\Tax::with('components')->find($taxId) : null;
                            $taxCompSummary = '';

                            if ($taxModel && $taxModel->components && $taxModel->components->isNotEmpty()) {
                                $comps = $taxModel->components->filter(fn ($c) => $c->type === \Tek2991\Accounting\Enums\TaxComponentType::Intrastate || $c->type === 'intrastate');
                                $compsToUse = $comps->isNotEmpty() ? $comps : $taxModel->components;
                                $compParts = [];
                                foreach ($compsToUse as $c) {
                                    $cRate = (float) $c->rate;
                                    $cAmount = round($subtotal * ($cRate / 100), 2);
                                    $compParts[] = "{$c->name} ({$cRate}%): + ₹".number_format($cAmount, 2);
                                }
                                $taxCompSummary = implode(' | ', $compParts);
                            } elseif ($taxPct > 0) {
                                $halfRate = round($taxPct / 2, 2);
                                $halfAmount = round($subtotal * ($halfRate / 100), 2);
                                $taxCompSummary = "CGST ({$halfRate}%): + ₹".number_format($halfAmount, 2)." | SGST ({$halfRate}%): + ₹".number_format($halfAmount, 2);
                            }

                            return new HtmlString('
                                <div style="background-color: rgba(37, 99, 235, 0.04); border: 1px solid rgba(37, 99, 235, 0.2); border-radius: 8px; padding: 18px; margin-top: 10px;">
                                    <div style="font-weight: 700; font-size: 15px; margin-bottom: 12px; color: #1e3a8a;">📊 Quotation Financial Breakdown (Vendor Cost vs Client Price)</div>
                                    <div style="display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 16px; font-size: 13px;">
                                        <div>
                                            <span style="color: gray;">Vendor / Base Cost:</span><br>
                                            <strong style="font-size: 16px; color: #475569;">₹'.number_format($vendorCost, 2).'</strong>
                                            <div style="font-size: 11px; color: #94a3b8;">Payable to contractor(s)</div>
                                        </div>
                                        <div>
                                            <span style="color: gray;">Client Subtotal (Before Tax):</span><br>
                                            <strong style="font-size: 16px; color: #2563eb;">₹'.number_format($subtotal, 2).'</strong>
                                            <div style="font-size: 11px; color: #16a34a; font-weight: 600;">Dwelly Margin: + ₹'.number_format($margin, 2).'</div>
                                        </div>
                                        <div>
                                            <span style="color: gray;">GST / Tax ('.$taxPct.'%):</span><br>
                                            <strong style="font-size: 16px; color: #6b7280;">+ ₹'.number_format($tax, 2).'</strong>
                                            <div style="font-size: 11px; color: #64748b; font-weight: 500; margin-top: 2px;">'.$taxCompSummary.'</div>
                                        </div>
                                        <div style="background-color: rgba(37, 99, 235, 0.1); padding: 8px 12px; border-radius: 6px; border-left: 4px solid #2563eb;">
                                            <span style="color: #1e40af; font-weight: 600;">Total Client Quotation:</span><br>
                                            <strong style="font-size: 20px; color: #1e3a8a;">₹'.number_format($total, 2).'</strong>
                                            <div style="font-size: 11px; color: #1e40af;">Official customer amount</div>
                                        </div>
                                    </div>
                                </div>
                            ');
                        }),
                ]),

            Section::make('📝 Itemized Line Items')
                ->description('Review contractor trade estimates, configure client unit prices before taxes, and preview formal customer charges.')
                ->columnSpanFull()
                ->headerActions([
                    Action::make('importFromVendorQuotes')
                        ->label('Import Vendor Quotes')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('info')
                        ->button()
                        ->size('sm')
                        ->visible(fn ($record) => ! in_array($record?->status, ['approved', 'archived']))
                        ->action(function (Get $get, Set $set, $record, $livewire) {
                            $ticketId = $record?->maintenance_request_id
                                ?? $livewire->record?->maintenance_request_id
                                ?? $get('maintenance_request_id');
                            $quotes = [];

                            if ($ticketId) {
                                $marginPct = (float) ($get('margin_percentage') ?? SettingService::get('financials.default_margin_percentage', 10.00));
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

                                    $vendorCost = (float) $q->quoted_cost;
                                    $clientPrice = round($vendorCost * (1 + $marginPct / 100), 2);

                                    $quotes[] = [
                                        'vendor_quote_id' => $q->id,
                                        'maintenance_request_item_id' => $targetItemId,
                                        'description' => $q->trade_title ?: 'Trade Work',
                                        'quantity' => 1,
                                        'unit' => 'job',
                                        'vendor_cost' => $vendorCost,
                                        'unit_price' => $clientPrice,
                                        'total_price' => $clientPrice,
                                        'unit_rate' => $clientPrice,
                                        'total_cost' => $clientPrice,
                                    ];
                                }
                            }

                            if (! empty($quotes)) {
                                $currentItems = $get('items') ?? [];
                                $filteredCurrentItems = array_filter($currentItems, function ($it) {
                                    return ! empty($it['description']) || ! empty($it['unit_price']) || ! empty($it['unit_rate']) || ! empty($it['total_price']) || ! empty($it['total_cost']) || ! empty($it['maintenance_request_item_id']);
                                });
                                $set('items', array_values(array_merge($filteredCurrentItems, $quotes)));

                                static::recalculateQuotationTotals($get, $set);

                                Notification::make()
                                    ->title('Line Items Imported')
                                    ->body(count($quotes).' vendor trade quote items added with client prices calculated.')
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
                    // Duplicate Items Alert Banner
                    Placeholder::make('duplicate_items_alert')
                        ->label('')
                        ->columnSpanFull()
                        ->visible(function (Get $get, $record) {
                            $duplicates = static::getDuplicateLineItemsSummary($record, $get);
                            return ! empty($duplicates);
                        })
                        ->content(function (Get $get, $record) {
                            $duplicates = static::getDuplicateLineItemsSummary($record, $get);
                            if (empty($duplicates)) {
                                return null;
                            }

                            $itemsList = '<ul style="margin: 4px 0 0 16px; padding: 0; list-style-type: disc;">';
                            foreach ($duplicates as $dup) {
                                $itemsList .= '<li style="margin-bottom: 2px;">'.$dup.'</li>';
                            }
                            $itemsList .= '</ul>';

                            return new HtmlString('
                                <div style="background-color: #fffbeb; border: 1px solid #fde68a; border-left: 4px solid #f59e0b; border-radius: 6px; padding: 12px 16px; margin-bottom: 8px; color: #92400e; font-size: 13px;">
                                    <div style="display: flex; align-items: center; gap: 8px; font-weight: 700; font-size: 14px; margin-bottom: 4px;">
                                        <span>⚠️ Potential Duplicate Line Items Detected</span>
                                    </div>
                                    <div style="line-height: 1.4;">
                                        Duplicate entries may cause redundant contractor costs or overbilling the customer:
                                        '.$itemsList.'
                                    </div>
                                </div>
                            ');
                        }),

                    Repeater::make('items')
                        ->relationship('items')
                        ->columns(12)
                        ->defaultItems(1)
                        ->disabled(fn ($record) => in_array($record?->status, ['approved', 'archived']))
                        ->addable(fn ($record) => ! in_array($record?->status, ['approved', 'archived']))
                        ->deletable(fn ($record) => ! in_array($record?->status, ['approved', 'archived']))
                        ->reorderable(fn ($record) => ! in_array($record?->status, ['approved', 'archived']))
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
                                ->live()
                                ->nullable()
                                ->columnSpan(4),

                            TextInput::make('description')
                                ->label('Line Description / Scope')
                                ->placeholder('e.g. Wall Plaster & Primer Coating (Materials + Labor)')
                                ->required()
                                ->live(debounce: 500)
                                ->columnSpan(4),

                            TextInput::make('quantity')
                                ->label('Qty')
                                ->numeric()
                                ->default(1)
                                ->required()
                                ->live(debounce: 500)
                                ->afterStateUpdated(function (Get $get, Set $set) {
                                    $qty = (float) ($get('quantity') ?? 1);
                                    $rate = (float) ($get('unit_price') ?? $get('unit_rate') ?? 0);
                                    $total = round($qty * $rate, 2);
                                    $set('total_price', $total);
                                    $set('total_cost', $total);
                                    static::recalculateQuotationTotals($get, $set);
                                })
                                ->columnSpan(1),

                            TextInput::make('unit_price')
                                ->label('Client Unit Price (₹)')
                                ->helperText('Price quoted to client (excl. tax)')
                                ->numeric()
                                ->prefix('₹')
                                ->required()
                                ->live(debounce: 500)
                                ->afterStateUpdated(function (Get $get, Set $set) {
                                    $qty = (float) ($get('quantity') ?? 1);
                                    $rate = (float) ($get('unit_price') ?? 0);
                                    $total = round($qty * $rate, 2);
                                    $set('total_price', $total);
                                    $set('total_cost', $total);
                                    static::recalculateQuotationTotals($get, $set);
                                })
                                ->columnSpan(3),

                            Hidden::make('vendor_cost')->dehydrated(),
                            Hidden::make('vendor_quote_id')->dehydrated(),
                            Hidden::make('total_price')->dehydrated(),
                            Hidden::make('total_cost')->dehydrated(),

                            // Line 2: Financial Calculation Breakdown Bar
                            Placeholder::make('financial_breakdown_line')
                                ->label('')
                                ->columnSpanFull()
                                ->content(function (Get $get, $record) {
                                    $qty = (float) ($get('quantity') ?? 1);
                                    $clientUnitPrice = (float) ($get('unit_price') ?? $get('unit_rate') ?? 0);
                                    $clientSubtotal = round($qty * $clientUnitPrice, 2);

                                    $vendorUnitPrice = (float) ($get('vendor_cost') ?? 0);
                                    if ($vendorUnitPrice <= 0 && $get('vendor_quote_id')) {
                                        $vendorUnitPrice = (float) (MaintenanceVendorQuote::find($get('vendor_quote_id'))?->quoted_cost ?? 0);
                                    }
                                    $vendorTotal = round($qty * $vendorUnitPrice, 2);

                                    $marginPct = (float) ($get('../../margin_percentage') ?? $record?->margin_percentage ?? SettingService::get('financials.default_margin_percentage', 10.00));
                                    $taxPct = (float) ($get('../../gst_percentage') ?? $record?->gst_percentage ?? SettingService::get('financials.default_gst_percentage', 18.00));

                                    if ($vendorTotal > 0 && $clientSubtotal >= $vendorTotal) {
                                        $marginAmount = round($clientSubtotal - $vendorTotal, 2);
                                        $marginDisplayPct = $vendorTotal > 0 ? round(($marginAmount / $vendorTotal) * 100, 1) : $marginPct;
                                    } else {
                                        $marginAmount = round($clientSubtotal * ($marginPct / 100), 2);
                                        $marginDisplayPct = $marginPct;
                                    }

                                    $taxAmount = round($clientSubtotal * ($taxPct / 100), 2);
                                    $clientGrandTotal = round($clientSubtotal + $taxAmount, 2);

                                    return new HtmlString('
                                        <div style="background: rgba(241, 245, 249, 0.7); border: 1px dashed rgba(148, 163, 184, 0.5); border-radius: 6px; padding: 7px 12px; margin-top: 2px; margin-bottom: 4px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px; font-size: 12px;">
                                            <div style="display: flex; align-items: center; gap: 5px;">
                                                <span style="color: #64748b; font-weight: 500;">🏢 Vendor Rate:</span>
                                                <strong style="color: #334155;">'.($vendorUnitPrice > 0 ? ('₹'.number_format($vendorUnitPrice, 2)) : '—').'</strong>
                                                '.($qty > 1 && $vendorTotal > 0 ? '<span style="color: #94a3b8; font-size: 11px;">(Total: ₹'.number_format($vendorTotal, 2).')</span>' : '').'
                                            </div>
                                            <div style="color: #cbd5e1;">•</div>
                                            <div style="display: flex; align-items: center; gap: 5px;">
                                                <span style="color: #64748b; font-weight: 500;">💼 Client Subtotal:</span>
                                                <strong style="color: #2563eb;">₹'.number_format($clientSubtotal, 2).'</strong>
                                                <span style="color: #94a3b8; font-size: 11px;">(Excl. Tax)</span>
                                            </div>
                                            <div style="color: #cbd5e1;">•</div>
                                            <div style="display: flex; align-items: center; gap: 5px;">
                                                <span style="color: #16a34a; font-weight: 500;">📈 Margin:</span>
                                                <strong style="color: #16a34a;">+ ₹'.number_format($marginAmount, 2).' ('.$marginDisplayPct.'%)</strong>
                                            </div>
                                            <div style="color: #cbd5e1;">•</div>
                                            <div style="display: flex; align-items: center; gap: 5px;">
                                                <span style="color: #64748b; font-weight: 500;">🧾 GST ('.$taxPct.'%):</span>
                                                <strong style="color: #475569;">+ ₹'.number_format($taxAmount, 2).'</strong>
                                                '.($taxAmount > 0 ? '<span style="color: #94a3b8; font-size: 10px;">(CGST: ₹'.number_format($taxAmount / 2, 2).' + SGST: ₹'.number_format($taxAmount / 2, 2).')</span>' : '').'
                                            </div>
                                            <div style="color: #cbd5e1;">•</div>
                                            <div style="display: flex; align-items: center; gap: 5px; background: rgba(37, 99, 235, 0.08); padding: 2px 8px; border-radius: 4px;">
                                                <span style="color: #1e40af; font-weight: 600;">💳 Line Total:</span>
                                                <strong style="color: #1e3a8a; font-size: 13px;">₹'.number_format($clientGrandTotal, 2).'</strong>
                                                <span style="color: #1e40af; font-size: 10px;">(Incl. Tax)</span>
                                            </div>
                                        </div>
                                    ');
                                })
                                ->columnSpanFull(),
                        ])
                        ->columnSpanFull(),
                ]),

            // PDF Preview & Generation Card
            Section::make('📄 Official Client Quotation PDF')
                ->description('Compile, preview, and download the official PDF estimate presented to the customer.')
                ->columnSpanFull()
                ->headerActions([
                    Action::make('generateQuotationInPdfCard')
                        ->label(fn ($record) => ($record && ($record->hasMedia('generated_quote_pdf') || $record->hasMedia('quote_pdf'))) ? 'Regenerate PDF' : 'Generate Quotation')
                        ->icon('heroicon-o-arrow-path')
                        ->color('primary')
                        ->button()
                        ->size('sm')
                        ->disabled(fn ($record) => $record && in_array($record->status, ['approved', 'settled', 'archived']))
                        ->tooltip(fn ($record) => $record && $record->status === 'archived' ? 'Quotation is archived and locked from regeneration.' : ($record && in_array($record->status, ['approved', 'settled']) ? 'Quotation is already approved and locked from regeneration.' : null))
                        ->requiresConfirmation()
                        ->modalHeading(fn ($record, Get $get) => ! empty(static::getDuplicateLineItemsSummary($record, $get)) ? '⚠️ Duplicate Line Items Detected' : 'Generate Official Client Quotation PDF')
                        ->modalDescription(function ($record, Get $get) {
                            $duplicates = static::getDuplicateLineItemsSummary($record, $get);
                            if (! empty($duplicates)) {
                                return new HtmlString(
                                    '<p style="margin-bottom: 8px; font-size: 13px;">The following potential duplicate line items were detected in this quotation:</p>'
                                    . '<ul style="margin: 0 0 12px 18px; padding: 0; list-style-type: disc; color: #b45309; font-size: 13px;">'
                                    . '<li>' . implode('</li><li>', $duplicates) . '</li>'
                                    . '</ul>'
                                    . '<p style="font-weight: 600; font-size: 13px; color: #1e293b;">Are you sure you want to proceed and generate the official customer quotation PDF with these duplicates?</p>'
                                );
                            }

                            return 'Compile and generate the official branded PDF quotation estimate based on current line items and pricing.';
                        })
                        ->modalSubmitActionLabel(fn ($record, Get $get) => ! empty(static::getDuplicateLineItemsSummary($record, $get)) ? 'Yes, Proceed & Generate' : 'Generate Quotation')
                        ->action(function ($record, $livewire) {
                            if (! $record) {
                                return;
                            }

                            if (in_array($record->status, ['approved', 'settled', 'archived'])) {
                                Notification::make()
                                    ->title('Quotation Locked')
                                    ->body('This quotation is locked and cannot be regenerated.')
                                    ->warning()
                                    ->send();

                                return;
                            }

                            try {
                                app(MaintenanceQuotationPdfService::class)->generatePdf($record);

                                Notification::make()
                                    ->title('Quotation PDF Generated')
                                    ->body('Official Client Quotation PDF has been generated.')
                                    ->success()
                                    ->send();

                                $livewire->redirect(
                                    \App\Filament\Resources\Billing\MaintenanceQuotationResource::getUrl('pricing', ['record' => $record])
                                );
                            } catch (\Throwable $e) {
                                Notification::make()
                                    ->title('Quotation Generation Failed')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        }),

                    Action::make('downloadPdf')
                        ->label('Download Latest PDF')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('gray')
                        ->button()
                        ->size('sm')
                        ->visible(fn ($record) => $record && ($record->hasMedia('generated_quote_pdf') || $record->hasMedia('quote_pdf')))
                        ->url(fn ($record) => $record ? route('billing.quotation.pdf.download', ['quote' => $record->id]) : '#'),
                ])
                ->schema([
                    Placeholder::make('quote_pdf_viewer')
                        ->label('')
                        ->columnSpanFull()
                        ->content(fn ($record) => view('maintenance.quotation-document-history', ['record' => $record])),
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
                            $approvedAt = $livewire->data['approved_at'] ?? $record->approved_at;
                            $channel = $livewire->data['approval_channel'] ?? $record->approval_channel;

                            if (blank($notes) || blank($approvedAt) || blank($channel) || ! $record->hasMedia('approval_proof_files')) {
                                Notification::make()
                                    ->title('All Approval Details Required')
                                    ->body('Please fill in all mandatory fields (Approval Method, Confirmation Remarks, Approval Date & Time, and Approval Proof document) before confirming approval.')
                                    ->warning()
                                    ->persistent()
                                    ->send();

                                return;
                            }

                            $billingService = app(MaintenanceBillingService::class);
                            $payer = $record->maintenanceRequest?->payer_type;
                            $payerVal = $payer instanceof \App\Domain\Maintenance\Enums\PayerType ? $payer->value : (string) $payer;
                            $defaultApprover = in_array($payerVal, ['tenant', 'tenant_direct', 'dwelly_invoice_tenant']) ? 'tenant' : (in_array($payerVal, ['dwelly', 'dwelly_direct_absorbed']) ? 'dwelly' : 'owner');

                            $billingService->recordClientApproval($record, [
                                'approved_by_type' => $livewire->data['approved_by_type'] ?? $record->approved_by_type ?? $defaultApprover,
                                'approval_channel' => $channel,
                                'approval_notes' => $notes,
                                'approved_at' => $approvedAt,
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
                                ->required()
                                ->options([
                                    'owner' => '👤 Owner',
                                    'tenant' => '🏠 Tenant',
                                    'dwelly' => '🏢 Dwelly Internal Management',
                                ])
                                ->default(function ($record) {
                                    $payer = $record?->maintenanceRequest?->payer_type;
                                    $payerVal = $payer instanceof \App\Domain\Maintenance\Enums\PayerType ? $payer->value : (string) $payer;
                                    if (in_array($payerVal, ['tenant', 'tenant_direct', 'dwelly_invoice_tenant'])) {
                                        return 'tenant';
                                    }
                                    if (in_array($payerVal, ['dwelly', 'dwelly_direct_absorbed'])) {
                                        return 'dwelly';
                                    }

                                    return 'owner';
                                })
                                ->afterStateHydrated(function (Select $component, $state, $record) {
                                    if (blank($state) && $record) {
                                        $payer = $record->maintenanceRequest?->payer_type;
                                        $payerVal = $payer instanceof \App\Domain\Maintenance\Enums\PayerType ? $payer->value : (string) $payer;
                                        if (in_array($payerVal, ['tenant', 'tenant_direct', 'dwelly_invoice_tenant'])) {
                                            $component->state('tenant');
                                        } elseif (in_array($payerVal, ['dwelly', 'dwelly_direct_absorbed'])) {
                                            $component->state('dwelly');
                                        } else {
                                            $component->state('owner');
                                        }
                                    }
                                })
                                ->disabled()
                                ->dehydrated()
                                ->helperText(function ($record) {
                                    if ($record?->maintenanceRequest?->payer_type) {
                                        $payer = $record->maintenanceRequest->payer_type;
                                        $label = $payer instanceof \App\Domain\Maintenance\Enums\PayerType ? $payer->getPlainLabel() : ucfirst((string) $payer);
                                        return "🔒 Locked to Ticket Payer: {$label}";
                                    }

                                    return '🔒 Locked to Ticket Financial Responsibility';
                                })
                                ->columnSpan(1),

                            Select::make('approval_channel')
                                ->label('Approval Method / Channel')
                                ->required()
                                ->options([
                                    'whatsapp' => '💬 WhatsApp Confirmation',
                                    'email' => '📧 Email Approval',
                                    'written' => '✍️ Physical / Signed Estimate',
                                    'portal' => '🌐 Customer Portal',
                                    'verbal' => '📞 Phone Call / Verbal Confirmation',
                                ])
                                ->default('whatsapp')
                                ->disabled(fn ($record) => in_array($record?->status, ['approved', 'archived']))
                                ->columnSpan(1),
                        ]),

                    // Middle Row: Full width Remarks
                    Textarea::make('approval_notes')
                        ->label('Approval Confirmation Remarks')
                        ->required()
                        ->placeholder('e.g. Approved via WhatsApp message from Owner Mr. Ramesh on 19-Aug-2026. Authorized full scope.')
                        ->rows(3)
                        ->disabled(fn ($record) => in_array($record?->status, ['approved', 'archived']))
                        ->columnSpanFull(),

                    // Last Line: Approval Date & Time + Client Approval Proof
                    Grid::make(3)
                        ->schema([
                            DatePicker::make('approved_at')
                                ->label('Approval Date & Time')
                                ->required()
                                ->default(now())
                                ->disabled(fn ($record) => in_array($record?->status, ['approved', 'archived']))
                                ->columnSpan(1),

                            SpatieMediaLibraryFileUpload::make('approval_proof_files')
                                ->collection('approval_proof_files')
                                ->label('📎 Client Approval Proof (Screenshot, Email PDF, or Signed Document)')
                                ->required()
                                ->multiple()
                                ->openable()
                                ->downloadable()
                                ->previewable()
                                ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png', 'image/webp'])
                                ->maxSize(10240)
                                ->disabled(fn ($record) => in_array($record?->status, ['approved', 'archived']))
                                ->helperText('Upload screenshot of WhatsApp message, signed quotation scan, or client email approval.')
                                ->columnSpan(2),
                        ]),

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
                ->description('Select winning contractor quotations in the table below and click "Issue Work Order(s)" to authorize on-site repair work.')
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
                        ->modalDescription(function ($record, $livewire) {
                            $selectedIds = (array) ($livewire->data['awarded_vendor_quote_ids'] ?? $record?->awarded_vendor_quote_ids ?? []);
                            $includedIds = (array) ($record?->getIncludedVendorQuoteIds() ?? []);
                            $unapprovedSelectedIds = array_values(array_diff($selectedIds, $includedIds));

                            $alertHtml = '';
                            if (! empty($unapprovedSelectedIds)) {
                                $unapprovedQuotes = MaintenanceVendorQuote::whereIn('id', $unapprovedSelectedIds)->with('vendor')->get();
                                $itemsList = $unapprovedQuotes->map(function ($q) {
                                    $name = e($q->vendor?->display_name ?? 'Contractor');
                                    $cost = number_format((float) $q->quoted_cost, 2);
                                    $trade = e($q->trade_title);

                                    return "<li><strong>{$trade}</strong> &mdash; {$name} (₹{$cost})</li>";
                                })->implode('');

                                $alertHtml = '
                                    <div style="margin-bottom: 14px; padding: 12px 16px; border-radius: 8px; background-color: #fffbeb; border: 1.5px solid #f59e0b; color: #92400e; font-size: 13px;">
                                        <div style="font-weight: 700; display: flex; align-items: center; gap: 6px; margin-bottom: 6px; font-size: 14px; color: #b45309;">
                                            ⚠️ Warning: Unapproved Contractor Estimate(s) Selected
                                        </div>
                                        <div style="margin-bottom: 6px;">
                                            The following selected contractor estimate(s) were <strong>NOT</strong> part of the client-approved quotation:
                                        </div>
                                        <ul style="list-style-type: disc; margin-left: 20px; margin-bottom: 6px;">
                                            '.$itemsList.'
                                        </ul>
                                        <div style="font-size: 12px; color: #b45309; font-weight: 600;">
                                            Please confirm that you want to issue work orders to these alternative contractors instead of the quotation-approved scope.
                                        </div>
                                    </div>
                                ';
                            }

                            return new HtmlString(
                                $alertHtml.'
                                <p style="font-size: 13px; color: #475569;">
                                    Confirm issuance of official Work Orders for the selected contractor quotation(s). This will generate work order reference numbers and authorize technicians for on-site repair work.
                                </p>
                            '
                            );
                        })
                        ->modalSubmitActionLabel('Confirm & Issue Work Orders')
                        ->action(function ($record, $livewire) {
                            $selectedIds = $livewire->data['awarded_vendor_quote_ids'] ?? $record->awarded_vendor_quote_ids ?? [];
                            $selectedIds = (array) $selectedIds;

                            if (empty($selectedIds)) {
                                Notification::make()
                                    ->title('Selection Required')
                                    ->body('Please check at least one vendor quote in the table below before issuing work orders.')
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
                    Placeholder::make('contractor_work_orders_table')
                        ->hiddenLabel()
                        ->columnSpanFull()
                        ->content(fn ($record) => view('maintenance.contractor-work-orders-table', ['record' => $record])),
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
            'archived' => '#64748b',
            default => '#3b82f6',
        };

        $statusLabel = match ($status) {
            'approved' => '✅ Client Approved',
            'settled' => '💳 Settled',
            'pending_approval' => '⏳ Pending Client Approval',
            'rejected' => '❌ Rejected',
            'archived' => '🗄️ Archived & Locked',
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

        $archivedBanner = '';
        if ($status === 'archived') {
            $archivedBanner = '<div style="background-color: rgba(239, 68, 68, 0.08); border-left: 4px solid #ef4444; border-radius: 6px; padding: 12px 16px; margin-bottom: 1rem; font-size: 13px; color: #991b1b; font-weight: 500;">🗄️ <strong>Quotation Archived & Locked:</strong> This financial quotation has been archived and locked. All fields, calculation tools, and approval actions are permanently disabled.</div>';
        }

        $html = '<div style="background-color: var(--fi-section-bg, #ffffff); border: 1px solid var(--fi-section-border, rgba(0,0,0,0.1)); border-radius: 0.75rem; padding: 1.25rem; margin-bottom: 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">';
        $html .= $archivedBanner;
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
        $vendorTotal = 0.0;

        foreach ($items as $item) {
            $qty = (float) ($item['quantity'] ?? 1);
            $rate = (float) ($item['unit_price'] ?? $item['unit_rate'] ?? 0);
            $lineTotal = isset($item['total_price']) && $item['total_price'] !== null && $item['total_price'] !== ''
                ? (float) $item['total_price']
                : (isset($item['total_cost']) && $item['total_cost'] !== null && $item['total_cost'] !== ''
                    ? (float) $item['total_cost']
                    : round($qty * $rate, 2));
            $subtotal += $lineTotal;

            $vCost = (float) ($item['vendor_cost'] ?? 0);
            $vendorTotal += round($qty * $vCost, 2);
        }

        $marginPct = (float) ($get('margin_percentage') ?? SettingService::get('financials.default_margin_percentage', 10));
        $taxPct = (float) ($get('gst_percentage') ?? SettingService::get('financials.default_gst_percentage', 18));

        $marginAmount = ($vendorTotal > 0 && $subtotal >= $vendorTotal)
            ? round($subtotal - $vendorTotal, 2)
            : round($subtotal * ($marginPct / 100), 2);

        $taxAmount = round($subtotal * ($taxPct / 100), 2);
        $total = round($subtotal + $taxAmount, 2);

        $set('subtotal_amount', $subtotal);
        $set('margin_amount', $marginAmount);
        $set('tax_amount', $taxAmount);
        $set('total_amount', $total);
    }

    /**
     * Helper to detect and summarize duplicate line items
     */
    public static function getDuplicateLineItemsSummary(?MaintenanceClientQuote $record, ?Get $get = null): array
    {
        $items = $get ? ($get('items') ?? []) : [];
        if (empty($items) && $record) {
            $items = $record->items()->get()->toArray();
        }

        if (count($items) < 2) {
            return [];
        }

        $defectCounts = [];
        $descCounts = [];
        $counter = 1;

        foreach ($items as $it) {
            $lineNumber = $counter++;
            $defId = $it['maintenance_request_item_id'] ?? null;
            if ($defId) {
                $defectCounts[$defId][] = $lineNumber;
            }

            $desc = trim(strtolower((string) ($it['description'] ?? '')));
            if ($desc !== '') {
                $descCounts[$desc][] = [
                    'line' => $lineNumber,
                    'original' => trim((string) ($it['description'] ?? '')),
                ];
            }
        }

        $duplicates = [];

        foreach ($defectCounts as $defId => $lines) {
            if (count($lines) > 1) {
                $defectItem = MaintenanceRequestItem::with('itemable')->find($defId);
                $name = $defectItem ? Str::limit($defectItem->issue_description, 35) : "Defect Item #{$defId}";
                $linesStr = implode(', ', array_map(fn ($l) => "Line {$l}", $lines));
                $duplicates[] = "{$linesStr} are mapped to the same defect item ({$name})";
            }
        }

        foreach ($descCounts as $desc => $entries) {
            if (count($entries) > 1) {
                $lines = array_column($entries, 'line');
                $linesStr = implode(', ', array_map(fn ($l) => "Line {$l}", $lines));
                $origDesc = $entries[0]['original'];
                $duplicates[] = "{$linesStr} have identical descriptions (\"{$origDesc}\")";
            }
        }

        return $duplicates;
    }
}
