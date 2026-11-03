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
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions;
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
                ->headerActions([
                    Action::make('saveHeader')
                        ->label('Save Changes')
                        ->icon('heroicon-o-check')
                        ->color('success')
                        ->button()
                        ->size('sm')
                        ->visible(fn ($record) => ! in_array($record?->status, ['approved', 'archived']))
                        ->action(function ($livewire) {
                            if (method_exists($livewire, 'save')) {
                                $livewire->save(shouldRedirect: false);
                            }

                            Notification::make()
                                ->title('Quotation Saved')
                                ->body('Quotation and vendor estimates have been saved successfully.')
                                ->success()
                                ->send();
                        }),
                ])
                ->schema([
                    Repeater::make('vendorQuotes')
                        ->relationship('vendorQuotes')
                        ->columns(3)
                        ->defaultItems(1)
                        ->disabled(fn ($record) => in_array($record?->status, ['approved', 'archived']))
                        ->loadStateFromRelationshipsUsing(static function (Repeater $component): void {
                            $component->clearCachedExistingRecords();
                            $component->fillFromRelationship();

                            if (empty($component->getState())) {
                                $record = $component->getRecord();
                                if (! in_array($record?->status, ['approved', 'archived'])) {
                                    $uuid = $component->generateUuid();
                                    $items = [$uuid ?? 0 => []];
                                    $component->rawState($items);
                                    $component->getChildSchema($uuid ?? 0)->fill();
                                }
                            }
                        })
                        ->addable(false)
                        ->deletable(fn ($record) => ! in_array($record?->status, ['approved', 'archived']))
                        ->reorderable(fn ($record) => ! in_array($record?->status, ['approved', 'archived']))
                        ->partiallyRenderAfterActionsCalled(false)
                        ->deleteAction(
                            fn (Action $action) => $action
                                ->requiresConfirmation()
                                ->modalHeading('Delete Vendor Quote Estimate?')
                                ->modalDescription('Are you sure you want to remove this contractor trade estimate? Any attached quotation documents and cost bases will be removed.')
                                ->modalSubmitActionLabel('Yes, Delete Quote')
                                ->after(function ($livewire, $record) {
                                    if (method_exists($livewire, 'save')) {
                                        $livewire->save(shouldRedirect: false);
                                    }
                                    if ($record && method_exists($record, 'recalculateTotals')) {
                                        $record->recalculateTotals();
                                    }
                                })
                        )
                        ->addActionLabel('Save and add vendor quote')
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

                    Actions::make([
                        Action::make('saveAndAddVendorQuoteBottom')
                            ->label('Save and add vendor quote')
                            ->icon('heroicon-o-plus-circle')
                            ->color('primary')
                            ->button()
                            ->visible(fn ($record) => ! in_array($record?->status, ['approved', 'archived']))
                            ->action(function ($livewire) {
                                if (method_exists($livewire, 'save')) {
                                    $livewire->save(shouldRedirect: false);
                                }

                                $schema = method_exists($livewire, 'getSchema') ? $livewire->getSchema('form') : null;
                                $repeater = $schema?->getComponent('vendorQuotes');
                                if ($repeater instanceof Repeater) {
                                    $newUuid = $repeater->generateUuid();
                                    $items = $repeater->getRawState();
                                    if ($newUuid) {
                                        $items[$newUuid] = [];
                                    } else {
                                        $items[] = [];
                                    }
                                    $repeater->rawState($items);
                                    $repeater->getChildSchema($newUuid ?? array_key_last($items))->fill();
                                    $repeater->collapsed(false, shouldMakeComponentCollapsible: false);
                                    $repeater->callAfterStateUpdated();
                                    $repeater->shouldPartiallyRenderAfterActionsCalled() ? $repeater->partiallyRender() : null;
                                }
                            }),

                        Action::make('saveChangesBottom')
                            ->label('Save Changes')
                            ->icon('heroicon-o-check')
                            ->color('success')
                            ->button()
                            ->visible(fn ($record) => ! in_array($record?->status, ['approved', 'archived']))
                            ->action(function ($livewire) {
                                if (method_exists($livewire, 'save')) {
                                    $livewire->save(shouldRedirect: false);
                                }

                                Notification::make()
                                    ->title('Quotation Saved')
                                    ->body('Quotation and vendor estimates have been saved successfully.')
                                    ->success()
                                    ->send();
                            }),
                    ])
                        ->alignment(\Filament\Support\Enums\Alignment::Center)
                        ->columnSpanFull(),
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
                        ->action(function (Get $get, Set $set, $livewire) {
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

                            if (method_exists($livewire, 'save')) {
                                $livewire->save(shouldRedirect: false);
                            }

                            Notification::make()
                                ->title('Margin Markup Applied & Saved')
                                ->body("Recalculated client prices for {$updatedCount} line items using {$marginPct}% margin markup and saved changes.")
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
                            $itemsState = $get('items');
                            $subtotal = 0.0;
                            $vendorCost = 0.0;

                            if (is_array($itemsState)) {
                                foreach ($itemsState as $item) {
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
                                    $vCost = (float) ($item->vendor_cost ?? 0);
                                    $subtotal += (float) ($item->total_price ?? round($qty * $clientRate, 2));
                                    $vendorCost += round($qty * $vCost, 2);
                                }
                            } else {
                                $subtotal = (float) ($get('subtotal_amount') ?? $record?->subtotal_amount ?? 0);
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
                    Action::make('saveItemsHeader')
                        ->label('Save Changes')
                        ->icon('heroicon-o-check')
                        ->color('success')
                        ->button()
                        ->size('sm')
                        ->visible(fn ($record) => ! in_array($record?->status, ['approved', 'archived']))
                        ->action(function ($livewire) {
                            if (method_exists($livewire, 'save')) {
                                $livewire->save(shouldRedirect: false);
                            }

                            Notification::make()
                                ->title('Quotation Saved')
                                ->body('Quotation and line items have been saved successfully.')
                                ->success()
                                ->send();
                        }),

                    Action::make('syncVendorCosts')
                        ->label('Sync Vendor Costs')
                        ->icon('heroicon-o-arrow-path')
                        ->color('warning')
                        ->button()
                        ->size('sm')
                        ->visible(fn ($record) => ! in_array($record?->status, ['approved', 'archived']))
                        ->requiresConfirmation()
                        ->modalHeading('Sync Line Item Costs with Tab 1?')
                        ->modalDescription('This will update the contractor vendor cost base and vendor assignment for all linked line items using the latest quotes from Tab 1 and recalculate client pricing. Existing custom line descriptions will be preserved.')
                        ->modalSubmitActionLabel('Yes, Sync Costs')
                        ->action(function (Get $get, Set $set, $record, $livewire) {
                            $ticketId = $record?->maintenance_request_id
                                ?? $livewire->record?->maintenance_request_id
                                ?? $get('maintenance_request_id');

                            if (! $ticketId) {
                                return;
                            }

                            $marginPct = (float) ($get('margin_percentage') ?? SettingService::get('financials.default_margin_percentage', 10.00));
                            $dbQuotes = MaintenanceVendorQuote::where('maintenance_request_id', $ticketId)->get()->keyBy('id');
                            $items = $get('items') ?? [];
                            $syncedCount = 0;

                            $quoteUsageCounts = array_count_values(array_filter(array_column($items, 'vendor_quote_id')));

                            foreach ($items as $index => $item) {
                                $vQuoteId = $item['vendor_quote_id'] ?? null;
                                if ($vQuoteId && isset($dbQuotes[$vQuoteId])) {
                                    $vq = $dbQuotes[$vQuoteId];
                                    $items[$index]['vendor_party_id'] = $vq->vendor_party_id;

                                    // Only overwrite vendor_cost directly if this quote is used by exactly one line item (not split)
                                    // Only overwrite vendor_cost directly if this quote is used by exactly one line item (not split)
                                    if (($quoteUsageCounts[$vQuoteId] ?? 1) === 1) {
                                        $newCost = (float) $vq->quoted_cost;
                                        $items[$index]['vendor_cost'] = $newCost;
                                    } else {
                                        $newCost = (float) ($items[$index]['vendor_cost'] ?? 0);
                                    }

                                    $qty = (float) ($items[$index]['quantity'] ?? 1);
                                    $newClientPrice = round($newCost * (1 + $marginPct / 100), 2);
                                    $newTotal = round($qty * $newClientPrice, 2);
                                    $items[$index]['unit_price'] = $newClientPrice;
                                    $items[$index]['unit_rate'] = $newClientPrice;
                                    $items[$index]['total_price'] = $newTotal;
                                    $items[$index]['total_cost'] = $newTotal;
                                    $syncedCount++;
                                }
                            }

                            $set('items', $items);
                            static::recalculateQuotationTotals($get, $set);

                            if (method_exists($livewire, 'save')) {
                                $livewire->save(shouldRedirect: false);
                            }

                            Notification::make()
                                ->title('Vendor Costs Synchronized & Saved')
                                ->body("Updated vendor cost base, vendor links, and recalculated pricing for {$syncedCount} line items.")
                                ->success()
                                ->send();
                        }),
                ])
                ->schema([
                    // Defect Items Coverage Status Widget
                    Placeholder::make('defect_items_coverage_status')
                        ->label('')
                        ->hiddenLabel()
                        ->columnSpanFull()
                        ->content(function (Get $get, $record, $livewire) {
                            $ticketId = $record?->maintenance_request_id
                                ?? $livewire->record?->maintenance_request_id
                                ?? $get('maintenance_request_id');

                            if (! $ticketId) {
                                return null;
                            }

                            $ticketItems = MaintenanceRequestItem::where('maintenance_request_id', $ticketId)
                                ->with(['itemable'])
                                ->get();

                            if ($ticketItems->isEmpty()) {
                                return null;
                            }

                            $items = $get('items') ?? [];
                            $coverageMap = [];
                            $lineCounter = 0;

                            foreach ($items as $line) {
                                $lineCounter++;
                                $lineNum = $lineCounter;
                                $targetIds = (array) ($line['maintenance_request_item_ids'] ?? []);
                                if (empty($targetIds) && ! empty($line['maintenance_request_item_id'])) {
                                    $targetIds = [$line['maintenance_request_item_id']];
                                }
                                $lineDesc = $line['description'] ?? "Line #{$lineNum}";
                                $clientRate = (float) ($line['unit_price'] ?? $line['unit_rate'] ?? 0);
                                $vPartyId = $line['vendor_party_id'] ?? null;
                                $vQuoteId = $line['vendor_quote_id'] ?? null;

                                foreach ($targetIds as $tid) {
                                    if ($tid) {
                                        $coverageMap[$tid][] = [
                                            'line_num' => $lineNum,
                                            'description' => $lineDesc,
                                            'client_price' => $clientRate,
                                            'vendor_party_id' => $vPartyId,
                                            'vendor_quote_id' => $vQuoteId,
                                        ];
                                    }
                                }
                            }

                            $totalItems = $ticketItems->count();
                            $coveredItemsCount = 0;
                            foreach ($ticketItems as $tItem) {
                                if (! empty($coverageMap[$tItem->id])) {
                                    $coveredItemsCount++;
                                }
                            }

                            $pct = $totalItems > 0 ? round(($coveredItemsCount / $totalItems) * 100) : 0;
                            $allCovered = $coveredItemsCount >= $totalItems;

                            $cardsHtml = '';
                            foreach ($ticketItems as $item) {
                                $isCovered = ! empty($coverageMap[$item->id]);

                                $locationName = 'General Property Area';
                                if ($item->itemable instanceof \App\Domain\Property\Models\PropertyRoom) {
                                    $locationName = '🚪 ' . ($item->itemable->custom_name ?: ($item->itemable->roomDefinition?->name ?? "Room #{$item->itemable->id}"));
                                } elseif ($item->itemable instanceof \App\Domain\Property\Models\PropertyInventory) {
                                    $locationName = '📦 ' . ($item->itemable->inventoryType?->name ?? "Item #{$item->itemable->id}");
                                } elseif ($item->itemable instanceof \App\Domain\Property\Models\PropertyUtility) {
                                    $locationName = '⚡ ' . ($item->itemable->utilityType?->name ?? "Utility #{$item->itemable->id}");
                                }

                                $issue = $item->issue_description ?: 'Reported Defect';
                                $action = $item->repair_action ? ucfirst(strtolower($item->repair_action)) : null;
                                $displayIssue = ($action ? "{$action}: " : '') . Str::limit($issue, 45);

                                if ($isCovered) {
                                    $linesInfo = $coverageMap[$item->id];
                                    $lineLabels = [];
                                    $vendorName = null;

                                    foreach ($linesInfo as $li) {
                                        $priceStr = $li['client_price'] > 0 ? ' (₹' . number_format($li['client_price'], 2) . ')' : '';
                                        $lineLabels[] = "Line #{$li['line_num']}{$priceStr}";

                                        if (! $vendorName) {
                                            if ($li['vendor_party_id']) {
                                                $vendorName = Party::find($li['vendor_party_id'])?->display_name;
                                            } elseif ($li['vendor_quote_id']) {
                                                $vendorName = MaintenanceVendorQuote::find($li['vendor_quote_id'])?->vendor?->display_name;
                                            }
                                        }
                                    }

                                    $coveredInText = implode(', ', $lineLabels);
                                    $vendorSubtext = $vendorName ? ' • Vendor: ' . e($vendorName) : '';

                                    $cardsHtml .= '
                                        <div style="background-color: #f0fdf4; border: 1px solid #86efac; border-radius: 8px; padding: 10px 12px; display: flex; flex-direction: column; justify-content: space-between;">
                                            <div>
                                                <div style="display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-bottom: 4px;">
                                                    <span style="font-size: 11px; font-weight: 600; color: #166534; text-transform: uppercase; letter-spacing: 0.025em; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">'.e($locationName).'</span>
                                                    <span style="background-color: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; font-size: 10px; font-weight: 700; padding: 2px 7px; border-radius: 9999px; white-space: nowrap;">✓ Accounted For</span>
                                                </div>
                                                <div style="font-weight: 600; font-size: 13px; color: #14532d; line-height: 1.35; margin-bottom: 6px;">'.e($displayIssue).'</div>
                                            </div>
                                            <div style="font-size: 11px; color: #15803d; border-top: 1px dashed #bbf7d0; padding-top: 6px; margin-top: 4px;">
                                                <span>Included in: <strong>'.e($coveredInText).'</strong>'.e($vendorSubtext).'</span>
                                            </div>
                                        </div>
                                    ';
                                } else {
                                    $cardsHtml .= '
                                        <div style="background-color: #fffbeb; border: 1px solid #fcd34d; border-radius: 8px; padding: 10px 12px; display: flex; flex-direction: column; justify-content: space-between;">
                                            <div>
                                                <div style="display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-bottom: 4px;">
                                                    <span style="font-size: 11px; font-weight: 600; color: #92400e; text-transform: uppercase; letter-spacing: 0.025em; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">'.e($locationName).'</span>
                                                    <span style="background-color: #fef3c7; color: #b45309; border: 1px solid #fde68a; font-size: 10px; font-weight: 700; padding: 2px 7px; border-radius: 9999px; white-space: nowrap;">⚠️ Unaccounted</span>
                                                </div>
                                                <div style="font-weight: 600; font-size: 13px; color: #78350f; line-height: 1.35; margin-bottom: 6px;">'.e($displayIssue).'</div>
                                            </div>
                                            <div style="font-size: 11px; color: #b45309; border-top: 1px dashed #fde68a; padding-top: 6px; margin-top: 4px; display: flex; align-items: center; justify-content: space-between;">
                                                <span>Missing from quote</span>
                                                <span style="font-style: italic; color: #92400e;">Select in Target Defect Items below</span>
                                            </div>
                                        </div>
                                    ';
                                }
                            }

                            $headerBadge = $allCovered
                                ? '<span style="background-color: #dcfce7; color: #15803d; border: 1px solid #86efac; font-weight: 700; padding: 3px 10px; border-radius: 9999px; font-size: 12px; display: inline-flex; align-items: center; gap: 4px;">
                                    <span>✓</span> All '.$totalItems.' Defect Items Accounted For (100%)
                                   </span>'
                                : '<span style="background-color: #fef3c7; color: #b45309; border: 1px solid #fcd34d; font-weight: 700; padding: 3px 10px; border-radius: 9999px; font-size: 12px; display: inline-flex; align-items: center; gap: 4px;">
                                    <span>⚠️</span> '.$coveredItemsCount.' of '.$totalItems.' Defect Items Accounted For ('.$pct.'%)
                                   </span>';

                            $progressColor = $allCovered ? '#22c55e' : '#f59e0b';

                            return new HtmlString('
                                <div style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px 16px; margin-bottom: 12px;">
                                    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px; margin-bottom: 10px;">
                                        <div>
                                            <div style="font-weight: 700; font-size: 13px; color: #1e293b; display: flex; align-items: center; gap: 6px;">
                                                <span>🛠️ Repair Defect Coverage Status</span>
                                            </div>
                                            <div style="font-size: 11px; color: #64748b; margin-top: 1px;">
                                                Track which ticket repair issues have been included in the quotation line items below.
                                            </div>
                                        </div>
                                        <div>
                                            '.$headerBadge.'
                                        </div>
                                    </div>

                                    <div style="background-color: #e2e8f0; border-radius: 9999px; height: 5px; width: 100%; overflow: hidden; margin-bottom: 12px;">
                                        <div style="background-color: '.$progressColor.'; width: '.$pct.'%; height: 100%; transition: width 0.3s ease;"></div>
                                    </div>

                                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 10px;">
                                        '.$cardsHtml.'
                                    </div>
                                </div>
                            ');
                        }),
                    // Vendor Cost Discrepancy Alert Banner
                    Placeholder::make('vendor_cost_discrepancy_alert')
                        ->label('')
                        ->columnSpanFull()
                        ->visible(fn (Get $get, $record) => ! empty(static::getVendorCostDiscrepanciesSummary($record, $get)))
                        ->content(function (Get $get, $record) {
                            $discrepancies = static::getVendorCostDiscrepanciesSummary($record, $get);
                            if (empty($discrepancies)) {
                                return null;
                            }

                            $list = '<ul style="margin: 4px 0 0 16px; padding: 0; list-style-type: disc;">';
                            foreach ($discrepancies as $disc) {
                                $list .= '<li style="margin-bottom: 2px;">'.e($disc).'</li>';
                            }
                            $list .= '</ul>';

                            return new HtmlString('
                                <div style="border-radius: 0.75rem; border: 1px solid rgba(245, 158, 11, 0.35); border-left: 4px solid #f59e0b; background-color: rgba(245, 158, 11, 0.08); padding: 0.875rem 1rem; margin-bottom: 0.5rem; font-size: 0.75rem; color: #92400e;">
                                    <div style="display: flex; align-items: center; gap: 0.5rem; font-weight: 700; font-size: 0.875rem; margin-bottom: 0.25rem; color: #78350f;">
                                        <span>🔄 Contractor Estimate Updates Detected</span>
                                    </div>
                                    <div style="line-height: 1.5;">
                                        Contractor quotes in Tab 1 have been updated since line items were imported. Click <strong>"Sync Vendor Costs"</strong> above to refresh cost bases:
                                        '.$list.'
                                    </div>
                                </div>
                            ');
                        }),

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
                                $itemsList .= '<li style="margin-bottom: 2px;">'.e($dup).'</li>';
                            }
                            $itemsList .= '</ul>';

                            return new HtmlString('
                                <div style="border-radius: 0.75rem; border: 1px solid rgba(245, 158, 11, 0.35); border-left: 4px solid #f59e0b; background-color: rgba(245, 158, 11, 0.08); padding: 0.875rem 1rem; margin-bottom: 0.5rem; font-size: 0.75rem; color: #92400e;">
                                    <div style="display: flex; align-items: center; gap: 0.5rem; font-weight: 700; font-size: 0.875rem; margin-bottom: 0.25rem; color: #78350f;">
                                        <span>⚠️ Potential Duplicate Line Items Detected</span>
                                    </div>
                                    <div style="line-height: 1.5;">
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
                        ->addable(false)
                        ->deletable(fn ($record) => ! in_array($record?->status, ['approved', 'archived']))
                        ->reorderable(fn ($record) => ! in_array($record?->status, ['approved', 'archived']))
                        ->partiallyRenderAfterActionsCalled(false)
                        ->deleteAction(
                            fn (Action $action) => $action
                                ->requiresConfirmation()
                                ->modalHeading('Delete Line Item?')
                                ->modalDescription('Are you sure you want to remove this itemized quote line from the client pricing breakdown?')
                                ->modalSubmitActionLabel('Yes, Delete Line Item')
                                ->after(function ($livewire, Get $get, Set $set, $record) {
                                    static::recalculateQuotationTotals($get, $set);
                                    if (method_exists($livewire, 'save')) {
                                        $livewire->save(shouldRedirect: false);
                                    }
                                    if ($record && method_exists($record, 'recalculateTotals')) {
                                        $record->recalculateTotals();
                                    }
                                })
                        )
                        ->afterStateUpdated(fn (Get $get, Set $set) => static::recalculateQuotationTotals($get, $set))
                        ->addActionLabel('Save and add line item')
                        ->schema([
                            // Row 1: Target Defect Items (col 6), Contractor Estimate / Bid (col 6)
                            Select::make('maintenance_request_item_ids')
                                ->label('Target Defect Items')
                                ->placeholder('Select Defect Items from Ticket')
                                ->multiple()
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

                                            return [$item->id => "{$name} - ".Str::limit($item->issue_description, 35)];
                                        });
                                })
                                ->searchable()
                                ->preload()
                                ->live()
                                ->nullable()
                                ->columnSpan(6),

                            Select::make('vendor_quote_id')
                                ->label('Contractor Estimate / Bid (Tab 1)')
                                ->placeholder('Select Contractor Bid')
                                ->options(function (Get $get, $record, $livewire) {
                                    $ticketId = $record?->maintenance_request_id
                                        ?? $livewire->record?->maintenance_request_id
                                        ?? $get('../../maintenance_request_id');

                                    if (! $ticketId) {
                                        return [];
                                    }

                                    return MaintenanceVendorQuote::where('maintenance_request_id', $ticketId)
                                        ->with(['vendor'])
                                        ->get()
                                        ->mapWithKeys(function ($q) {
                                            $vName = $q->vendor?->display_name ?? 'Contractor';
                                            $cost = number_format((float) $q->quoted_cost, 2);
                                            $trade = $q->trade_title ?: 'Trade Work';
                                            $coveredCount = count((array) ($q->maintenance_request_item_ids ?: []));
                                            $pkgTag = $coveredCount > 1 ? " 📦 (Pkg: {$coveredCount})" : '';

                                            return [$q->id => "{$vName} — ₹{$cost} ({$trade}){$pkgTag}"];
                                        });
                                })
                                ->searchable()
                                ->preload()
                                ->nullable()
                                ->live()
                                ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                    if ($state) {
                                        $vq = MaintenanceVendorQuote::with('vendor')->find($state);
                                        if ($vq) {
                                            $set('vendor_party_id', $vq->vendor_party_id);
                                            $set('vendor_cost', (float) $vq->quoted_cost);

                                            if (empty($get('description'))) {
                                                $set('description', $vq->trade_title ?: 'Trade Work');
                                            }

                                            if (empty($get('maintenance_request_item_ids')) && ! empty($vq->maintenance_request_item_ids)) {
                                                $set('maintenance_request_item_ids', (array) $vq->maintenance_request_item_ids);
                                            }

                                            $marginPct = (float) ($get('../../margin_percentage') ?? SettingService::get('financials.default_margin_percentage', 10.00));
                                            $suggestedPrice = round((float) $vq->quoted_cost * (1 + $marginPct / 100), 2);
                                            $qty = (float) ($get('quantity') ?? 1);
                                            $set('unit_price', $suggestedPrice);
                                            $set('unit_rate', $suggestedPrice);
                                            $set('total_price', round($qty * $suggestedPrice, 2));
                                            $set('total_cost', round($qty * $suggestedPrice, 2));
                                        }
                                    }
                                    static::recalculateQuotationTotals($get, $set);
                                })
                                ->columnSpan(6),

                            // Direct Artisan selection (visible only when no Tab 1 bid is linked)
                            Select::make('vendor_party_id')
                                ->label('Assigned Direct Artisan / Vendor')
                                ->placeholder('Select Artisan / Contractor from Directory')
                                ->visible(fn (Get $get) => empty($get('vendor_quote_id')))
                                ->options(function ($state) {
                                    $query = Party::whereHas('vendorProfile', function ($sq) {
                                        $sq->where('onboarding_status', VendorOnboardingStatus::VERIFIED->value)
                                            ->orWhere('onboarding_status', VendorOnboardingStatus::VERIFIED);
                                    });

                                    if ($state) {
                                        $query->orWhere('id', $state);
                                    }

                                    return $query->with(['vendorProfile.trade'])
                                        ->get()
                                        ->mapWithKeys(function ($party) {
                                            $tradeName = $party->vendorProfile?->trade?->name ?? 'General';
                                            return [$party->id => "{$party->display_name} ({$tradeName})"];
                                        });
                                })
                                ->searchable()
                                ->preload()
                                ->nullable()
                                ->dehydrated()
                                ->createOptionAction(fn ($action) => $action
                                    ->modalHeading('Quick Create Vendor / Artisan')
                                    ->modalDescription('Add an artisan or contractor to assign to this quotation line.')
                                    ->modalWidth('lg')
                                )
                                ->createOptionForm([
                                    TextInput::make('display_name')->label('Vendor / Artisan Name')->required(),
                                    Select::make('party_type')->label('Vendor Type')->options([
                                        'individual' => '👤 Individual Artisan',
                                        'organization' => '🏢 Contractor Company',
                                    ])->default('individual')->required(),
                                    Select::make('vendor_trade_id')->label('Primary Trade')->options(fn () => VendorTrade::where('is_active', true)->pluck('name', 'id'))->searchable()->preload()->nullable(),
                                    TextInput::make('phone')->label('Phone Number')->tel()->nullable(),
                                ])
                                ->createOptionUsing(function (array $data) {
                                    $isOrg = ($data['party_type'] ?? 'individual') === 'organization';
                                    $party = app(PartyService::class)->createParty([
                                        'party_type' => $data['party_type'] ?? 'individual',
                                        'display_name' => $data['display_name'],
                                        'phone' => $data['phone'] ?? null,
                                        'individual_data' => ! $isOrg ? ['name' => $data['display_name']] : [],
                                        'organization_data' => $isOrg ? ['legal_name' => $data['display_name']] : [],
                                        'vendor_data' => [
                                            'vendor_trade_id' => $data['vendor_trade_id'] ?? null,
                                            'onboarding_status' => VendorOnboardingStatus::VERIFIED->value,
                                            'is_preferred' => true,
                                        ],
                                    ], ['vendor']);
                                    return $party->id;
                                })
                                ->columnSpanFull(),

                            // Row 2: Scope Description (col 5), Qty (col 1), Vendor Cost (col 2), Client Unit Price (col 2), Subtotal (col 2)
                            TextInput::make('description')
                                ->label('Line Description / Scope')
                                ->placeholder('e.g. Wall Plaster & Primer Coating (Materials + Labor)')
                                ->required()
                                ->live(debounce: 500)
                                ->columnSpan(5),

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

                            TextInput::make('vendor_cost')
                                ->label('Vendor Cost (₹)')
                                ->numeric()
                                ->prefix('₹')
                                ->default(0.00)
                                ->live(debounce: 500)
                                ->afterStateUpdated(function (Get $get, Set $set) {
                                    $vendorCost = (float) ($get('vendor_cost') ?? 0);
                                    $currentClientPrice = (float) ($get('unit_price') ?? 0);
                                    if ($vendorCost > 0 && $currentClientPrice <= 0) {
                                        $marginPct = (float) ($get('../../margin_percentage') ?? SettingService::get('financials.default_margin_percentage', 10.00));
                                        $suggestedPrice = round($vendorCost * (1 + $marginPct / 100), 2);
                                        $qty = (float) ($get('quantity') ?? 1);
                                        $set('unit_price', $suggestedPrice);
                                        $set('unit_rate', $suggestedPrice);
                                        $set('total_price', round($qty * $suggestedPrice, 2));
                                        $set('total_cost', round($qty * $suggestedPrice, 2));
                                    }
                                    static::recalculateQuotationTotals($get, $set);
                                })
                                ->columnSpan(2),

                            TextInput::make('unit_price')
                                ->label('Client Unit Price (₹)')
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
                                ->columnSpan(2),

                            Placeholder::make('line_subtotal_preview')
                                ->label('Line Subtotal')
                                ->content(function (Get $get) {
                                    $qty = (float) ($get('quantity') ?? 1);
                                    $unitPrice = (float) ($get('unit_price') ?? 0);
                                    $subtotal = round($qty * $unitPrice, 2);

                                    return new HtmlString('
                                        <div style="margin-top: 6px;">
                                            <span style="font-size: 15px; font-weight: 700; color: #1e3a8a;">₹' . number_format($subtotal, 2) . '</span>
                                            <span style="font-size: 11px; color: #64748b; margin-left: 2px;">(Excl. Tax)</span>
                                        </div>
                                    ');
                                })
                                ->columnSpan(2),

                            Hidden::make('total_price')->dehydrated(),
                            Hidden::make('total_cost')->dehydrated(),

                            // Row 3: Modern, borderless calculation pill footer
                            Placeholder::make('financial_breakdown_line')
                                ->label('')
                                ->hiddenLabel()
                                ->columnSpanFull()
                                ->content(function (Get $get, $record) {
                                    $qty = (float) ($get('quantity') ?? 1);
                                    $clientUnitPrice = (float) ($get('unit_price') ?? $get('unit_rate') ?? 0);
                                    $clientSubtotal = round($qty * $clientUnitPrice, 2);

                                    $vendorUnitPrice = (float) ($get('vendor_cost') ?? 0);
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

                                    $vendorName = '—';
                                    $vPartyId = $get('vendor_party_id');
                                    $vQuoteId = $get('vendor_quote_id');

                                    if ($vPartyId) {
                                        $vendorName = Party::find($vPartyId)?->display_name ?? '—';
                                    } elseif ($vQuoteId) {
                                        $vendorName = MaintenanceVendorQuote::find($vQuoteId)?->vendor?->display_name ?? '—';
                                    }

                                    $marginColor = $marginAmount >= 0 ? '#16a34a' : '#dc2626';

                                    return new HtmlString('
                                        <div style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 6px 14px; margin-top: 2px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; font-size: 12px;">
                                            <div style="display: flex; align-items: center; gap: 6px;">
                                                <span style="color: #64748b; font-weight: 500;">👤 Contractor:</span>
                                                <strong style="color: #334155;">'.e($vendorName).'</strong>
                                                '.($vendorUnitPrice > 0 ? '<span style="color: #94a3b8; font-size: 11px;">(Base: ₹'.number_format($vendorUnitPrice, 2).')</span>' : '').'
                                            </div>
                                            <div style="color: #cbd5e1;">•</div>
                                            <div style="display: flex; align-items: center; gap: 6px;">
                                                <span style="color: #64748b; font-weight: 500;">📈 Dwelly Margin:</span>
                                                <strong style="color: '.$marginColor.'; font-weight: 600;">'.($marginAmount >= 0 ? '+' : '').'₹'.number_format($marginAmount, 2).' ('.$marginDisplayPct.'%)</strong>
                                            </div>
                                            <div style="color: #cbd5e1;">•</div>
                                            <div style="display: flex; align-items: center; gap: 6px;">
                                                <span style="color: #64748b; font-weight: 500;">🧾 GST ('.$taxPct.'%):</span>
                                                <strong style="color: #475569;">+₹'.number_format($taxAmount, 2).'</strong>
                                            </div>
                                            <div style="color: #cbd5e1;">•</div>
                                            <div style="display: flex; align-items: center; gap: 6px; background-color: rgba(37, 99, 235, 0.08); padding: 3px 10px; border-radius: 4px;">
                                                <span style="color: #1e40af; font-weight: 600;">Line Total (Incl. Tax):</span>
                                                <strong style="color: #1e3a8a; font-size: 13px;">₹'.number_format($clientGrandTotal, 2).'</strong>
                                            </div>
                                        </div>
                                    ');
                                })
                                ->columnSpanFull(),
                        ])
                        ->columnSpanFull(),

                    Actions::make([
                        Action::make('saveAndAddItemBottom')
                            ->label('Save and add line item')
                            ->icon('heroicon-o-plus-circle')
                            ->color('primary')
                            ->button()
                            ->visible(fn ($record) => ! in_array($record?->status, ['approved', 'archived']))
                            ->action(function ($livewire) {
                                if (method_exists($livewire, 'save')) {
                                    $livewire->save(shouldRedirect: false);
                                }

                                $schema = method_exists($livewire, 'getSchema') ? $livewire->getSchema('form') : null;
                                $repeater = $schema?->getComponent('items');
                                if ($repeater instanceof Repeater) {
                                    $newUuid = $repeater->generateUuid();
                                    $items = $repeater->getRawState();
                                    if ($newUuid) {
                                        $items[$newUuid] = [
                                            'quantity' => 1,
                                            'vendor_cost' => 0.00,
                                            'unit_price' => 0.00,
                                            'vendor_quote_id' => null,
                                            'vendor_party_id' => null,
                                        ];
                                    } else {
                                        $items[] = [
                                            'quantity' => 1,
                                            'vendor_cost' => 0.00,
                                            'unit_price' => 0.00,
                                            'vendor_quote_id' => null,
                                            'vendor_party_id' => null,
                                        ];
                                    }
                                    $repeater->rawState($items);
                                    $repeater->getChildSchema($newUuid ?? array_key_last($items))->fill();
                                    $repeater->collapsed(false, shouldMakeComponentCollapsible: false);
                                    $repeater->callAfterStateUpdated();
                                    $repeater->shouldPartiallyRenderAfterActionsCalled() ? $repeater->partiallyRender() : null;
                                }
                            }),

                        Action::make('saveItemsChangesBottom')
                            ->label('Save Changes')
                            ->icon('heroicon-o-check')
                            ->color('success')
                            ->button()
                            ->visible(fn ($record) => ! in_array($record?->status, ['approved', 'archived']))
                            ->action(function ($livewire) {
                                if (method_exists($livewire, 'save')) {
                                    $livewire->save(shouldRedirect: false);
                                }

                                Notification::make()
                                    ->title('Quotation Saved')
                                    ->body('Quotation and line items have been saved successfully.')
                                    ->success()
                                    ->send();
                            }),
                    ])
                        ->alignment(\Filament\Support\Enums\Alignment::Center)
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
                                if (method_exists($livewire, 'save')) {
                                    $livewire->save(shouldRedirect: false);
                                }

                                $record->refresh();
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
                    static::getApproveQuotationAction(),
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

                    // Last Line: Approval Date + Client Approval Proof
                    Grid::make(3)
                        ->schema([
                            DatePicker::make('approved_at')
                                ->label('Approval Date')
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
                ->description(fn ($record) => $record?->maintenanceRequest?->payer_type?->isDwellyAbsorbed()
                    ? 'Select winning contractor quotation(s) below and click "Issue Work Order(s)" to authorize technicians directly without client approval.'
                    : 'Select winning contractor quotations in the table below and click "Issue Work Order(s)" to authorize on-site repair work.'
                )
                ->columnSpanFull()
                ->headerActions([
                    Action::make('issueWorkOrderInTab')
                        ->label('Issue Work Order(s)')
                        ->icon('heroicon-o-document-check')
                        ->color('primary')
                        ->button()
                        ->size('sm')
                        ->visible(function ($record) {
                            if (! $record) return false;
                            if (! empty($record->awarded_vendor_quote_ids)) return false;

                            $isDwelly = (bool) $record->maintenanceRequest?->payer_type?->isDwellyAbsorbed();
                            if ($isDwelly) {
                                return $record->vendorQuotes()->count() > 0;
                            }

                            return $record->status === 'approved';
                        })
                        ->requiresConfirmation()
                        ->modalHeading('Issue Contractor Work Order(s)')
                        ->modalDescription(function ($record, $livewire) {
                            $isDwelly = (bool) $record?->maintenanceRequest?->payer_type?->isDwellyAbsorbed();
                            $selectedIds = (array) ($livewire->data['awarded_vendor_quote_ids'] ?? $record?->awarded_vendor_quote_ids ?? []);
                            $includedIds = (array) ($record?->getIncludedVendorQuoteIds() ?? []);
                            $unapprovedSelectedIds = array_values(array_diff($selectedIds, $includedIds));

                            $alertHtml = '';
                            if (! $isDwelly && ! empty($unapprovedSelectedIds)) {
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

                            $msg = $isDwelly
                                ? 'Confirm issuance of official Work Orders for the selected contractor quotation(s). Cost is 100% absorbed by Dwelly.'
                                : 'Confirm issuance of official Work Orders for the selected contractor quotation(s). This will generate work order reference numbers and authorize technicians for on-site repair work.';

                            return new HtmlString($alertHtml . '<p style="font-size: 13px; color: #475569;">' . $msg . '</p>');
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

                            $isDwelly = (bool) $record->maintenanceRequest?->payer_type?->isDwellyAbsorbed();
                            if ($isDwelly && $record->status !== 'approved') {
                                $vendorQuotes = MaintenanceVendorQuote::whereIn('id', $selectedIds)->get();
                                $awardedCost = (float) $vendorQuotes->sum('quoted_cost');

                                $record->update([
                                    'status' => 'approved',
                                    'approved_by_type' => 'dwelly',
                                    'approval_channel' => 'internal',
                                    'approval_notes' => $record->approval_notes ?: 'Direct internal authorization (Dwelly-absorbed repair).',
                                    'approved_at' => now(),
                                    'subtotal_amount' => $awardedCost,
                                    'total_amount' => $awardedCost,
                                    'dwelly_amount' => $awardedCost,
                                    'owner_amount' => 0.00,
                                    'tenant_amount' => 0.00,
                                    'margin_amount' => 0.00,
                                    'margin_percentage' => 0.00,
                                ]);
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
            Section::make('📊 Settlement & Accounting Workflow (Client Invoices & Vendor Bills)')
                ->description('Official accounting documents (Client Invoices and Vendor Bills) are generated on the Maintenance Request page after work completion and verification audit.')
                ->columnSpanFull()
                ->headerActions([
                    Action::make('viewAuditInTab')
                        ->label(fn ($record) => $record?->maintenanceRequest?->triggeredAudit ? ('View Audit #'.$record->maintenanceRequest->triggeredAudit->audit_number) : 'View Verification Audit')
                        ->icon('heroicon-o-clipboard-document-check')
                        ->color('gray')
                        ->button()
                        ->size('sm')
                        ->visible(fn ($record) => filled($record?->maintenanceRequest?->triggered_audit_id) && (bool) $record?->maintenanceRequest?->triggeredAudit)
                        ->url(fn ($record) => $record?->maintenanceRequest?->triggeredAudit ? AuditResource::getUrl('inspect', ['record' => $record->maintenanceRequest->triggeredAudit]) : null)
                        ->openUrlInNewTab(),
                ])
                ->schema([
                    Placeholder::make('accounting_summary')
                        ->hiddenLabel()
                        ->columnSpanFull()
                        ->content(function ($record) {
                            if (! $record) {
                                return null;
                            }

                            $record->loadMissing(['vendorQuotes', 'items', 'maintenanceRequest.vendorQuotes']);
                            $ticket = $record->maintenanceRequest;
                            $ticketNumber = $ticket?->ticket_number ?? 'N/A';
                            $ticketUrl = $ticket ? MaintenanceRequestResource::getUrl('edit', ['record' => $ticket, 'relation' => 2]) : '#';
                            $payer = $ticket?->payer_type?->getLabel() ?? ucfirst((string) ($ticket?->payer_type ?? 'N/A'));
                            $isDirect = (bool) ($ticket?->is_direct_vendor ?? false);
                            $isDwelly = (bool) ($ticket?->payer_type?->isDwellyAbsorbed() ?? false);

                            $allQuotes = $record->vendorQuotes->isNotEmpty() ? $record->vendorQuotes : ($ticket?->vendorQuotes ?? collect());
                            $awardedQuotes = $allQuotes->filter(fn ($vq) => $vq->work_order_awarded || ! empty($vq->work_order_number) || ! empty($vq->bill_id));
                            if ($awardedQuotes->isEmpty()) {
                                $awardedQuotes = $allQuotes;
                            }
                            $vendorCost = (float) $awardedQuotes->sum('quoted_cost');
                            if ($vendorCost === 0.0 && (float) ($record->subtotal_amount ?? 0) > 0) {
                                $vendorCost = (float) $record->subtotal_amount;
                            }

                            $clientTotalAmount = $isDwelly ? 0.0 : (float) ($record->total_amount ?: 0);
                            $totalClient = number_format($clientTotalAmount, 2);
                            $subtotalVendor = number_format($vendorCost, 2);
                            $margin = number_format((float) ($record->margin_amount ?? ($clientTotalAmount - $vendorCost)), 2);

                            return new HtmlString('
                                <div style="display: flex; flex-direction: column; gap: 1.25rem; width: 100%;">
                                    <div style="padding: 1.25rem 1.375rem; border-radius: 0.75rem; background: linear-gradient(135deg, rgba(37, 99, 235, 0.08) 0%, rgba(99, 102, 241, 0.05) 100%); border: 1px solid rgba(37, 99, 235, 0.28); display: flex; flex-direction: column; gap: 1rem;">
                                        <div style="display: flex; align-items: center; justify-content: space-between; gap: 1.25rem; flex-wrap: wrap;">
                                            <div style="display: flex; align-items: flex-start; gap: 0.875rem; max-width: 680px;">
                                                <div style="display: inline-flex; align-items: center; justify-content: center; width: 2.25rem; height: 2.25rem; border-radius: 0.5rem; background: #2563eb; color: #ffffff; flex-shrink: 0; font-size: 1.125rem;">
                                                    ℹ️
                                                </div>
                                                <div>
                                                    <div style="font-weight: 700; font-size: 0.9375rem; color: #1e3a8a;">
                                                        '.($isDwelly ? 'Vendor Bill Accounting Workflow (Dwelly Absorbed)' : 'Invoice &amp; Vendor Bill Generation Workflow').'
                                                    </div>
                                                    <div style="font-size: 0.8125rem; color: #475569; margin-top: 0.25rem; line-height: 1.45;">
                                                        '.($isDwelly
                                                            ? 'Cost is absorbed 100% by Dwelly. <strong>No Client Invoice</strong> is generated. <strong>Vendor Bills</strong> to pay winning contractors are generated from the <strong>Maintenance Request</strong> page upon work completion.'
                                                            : 'Official accounting documents (<strong>Client Invoices</strong> and <strong>Vendor Bills</strong>) are generated from the operational <strong>Maintenance Request</strong> page under the <strong>Completion, Report &amp; Verification</strong> tab once repair work is completed and verified.').'
                                                    </div>
                                                </div>
                                            </div>
                                            <div>
                                                <a href="'.$ticketUrl.'" target="_blank" style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.625rem 1.125rem; border-radius: 0.5rem; background-color: #2563eb; color: #ffffff; font-weight: 700; font-size: 0.8125rem; text-decoration: none; box-shadow: 0 1px 2px rgba(0,0,0,0.08); white-space: nowrap;">
                                                    <span>Open Ticket #'.e($ticketNumber).' (Completion &amp; Billing)</span>
                                                    <svg style="width: 1rem; height: 1rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                                </a>
                                            </div>
                                        </div>

                                        <div style="display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 0.75rem; padding-top: 0.75rem; border-top: 1px solid rgba(37, 99, 235, 0.15);">
                                            <div style="padding: 0.625rem 0.875rem; border-radius: 0.5rem; background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.25); font-size: 0.75rem;">
                                                <span style="font-weight: 700; color: #047857;">'.($isDwelly ? 'Step 1: Estimates Collected' : 'Step 1: Quotation Approved').'</span><br>
                                                <span style="color: #065f46; font-size: 0.6875rem;">'.($isDwelly ? 'Vendor trade quotes logged' : 'Rates &amp; base costs finalized in Tab 2').'</span>
                                            </div>
                                            <div style="padding: 0.625rem 0.875rem; border-radius: 0.5rem; background: rgba(37, 99, 235, 0.1); border: 1px solid rgba(37, 99, 235, 0.25); font-size: 0.75rem;">
                                                <span style="font-weight: 700; color: #1e40af;">Step 2: Work Orders Awarded</span><br>
                                                <span style="color: #1e3a8a; font-size: 0.6875rem;">Contractors authorized directly</span>
                                            </div>
                                            <div style="padding: 0.625rem 0.875rem; border-radius: 0.5rem; background: rgba(245, 158, 11, 0.12); border: 1px solid rgba(245, 158, 11, 0.3); font-size: 0.75rem;">
                                                <span style="font-weight: 700; color: #92400e;">'.($isDwelly ? 'Step 3: Verification &amp; Vendor Bills' : 'Step 3: Verification &amp; Invoicing').'</span><br>
                                                <span style="color: #78350f; font-size: 0.6875rem;">Executed on Maintenance Request page</span>
                                            </div>
                                        </div>
                                    </div>

                                    <div style="display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 16px;">
                                        <div style="background: rgba(128, 128, 128, 0.03); border: 1px solid rgba(128, 128, 128, 0.18); border-radius: 8px; padding: 14px;">
                                            <span style="color: #64748b; font-size: 11px; text-transform: uppercase; font-weight: 600; letter-spacing: 0.05em;">Receivable / Client Invoice</span><br>
                                            <strong style="font-size: 18px; color: '.($isDwelly ? '#059669' : '#1e40af').';">'.($isDwelly ? '₹0.00' : '₹'.$totalClient).'</strong><br>
                                            <span style="font-size: 11px; color: '.($isDwelly ? '#047857' : '#3b82f6').';">'.($isDwelly ? '🏢 Absorbed by Dwelly' : 'Payer: '.e($payer)).'</span>
                                        </div>
                                        <div style="background: rgba(128, 128, 128, 0.03); border: 1px solid rgba(128, 128, 128, 0.18); border-radius: 8px; padding: 14px;">
                                            <span style="color: #64748b; font-size: 11px; text-transform: uppercase; font-weight: 600; letter-spacing: 0.05em;">Payable / Vendor Bills</span><br>
                                            <strong style="font-size: 18px; color: #b91c1c;">₹'.$subtotalVendor.'</strong><br>
                                            <span style="font-size: 11px; color: #ef4444;">Route: '.($isDirect ? 'Direct Client Payment' : 'Dwelly Coordinated').'</span>
                                        </div>
                                        <div style="background: rgba(128, 128, 128, 0.03); border: 1px solid rgba(128, 128, 128, 0.18); border-radius: 8px; padding: 14px;">
                                            <span style="color: #64748b; font-size: 11px; text-transform: uppercase; font-weight: 600; letter-spacing: 0.05em;">'.($isDwelly ? 'Internal Cost Impact' : 'Net Dwelly Margin').'</span><br>
                                            <strong style="font-size: 18px; color: '.($isDwelly ? '#be123c' : '#16a34a').';">'.($isDwelly ? '-₹'.$subtotalVendor : '₹'.$margin).'</strong><br>
                                            <span style="font-size: 11px; color: '.($isDwelly ? '#9f1239' : '#10b981').';">'.($isDwelly ? 'Company Maintenance Budget' : 'Markup: '.$record->margin_percentage.'%').'</span>
                                        </div>
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

        $record->loadMissing(['vendorQuotes', 'items', 'maintenanceRequest.vendorQuotes']);
        $quoteNumber = $record->quote_number ?? ('QT-'.$record->id);
        $status = $record->status ?? 'draft';
        $ticket = $record->maintenanceRequest;
        $isDwelly = (bool) ($ticket?->payer_type?->isDwellyAbsorbed() ?? false);

        $allQuotes = $record->vendorQuotes->isNotEmpty() ? $record->vendorQuotes : ($ticket?->vendorQuotes ?? collect());
        $awardedQuotes = $allQuotes->filter(fn ($vq) => $vq->work_order_awarded || ! empty($vq->work_order_number) || ! empty($vq->bill_id));
        if ($awardedQuotes->isEmpty()) {
            $awardedQuotes = $allQuotes;
        }
        $vendorCost = (float) $awardedQuotes->sum('quoted_cost');
        if ($vendorCost === 0.0 && (float) ($record->subtotal_amount ?? 0) > 0) {
            $vendorCost = (float) $record->subtotal_amount;
        }

        $totalAmount = number_format($isDwelly ? 0.0 : (float) ($record->total_amount ?? 0), 2);
        $subtotalVendor = number_format($vendorCost, 2);
        $marginAmount = number_format($isDwelly ? -$vendorCost : (float) ($record->margin_amount ?? 0), 2);

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

        $vendorQuotesCount = $record->vendorQuotes()->count();
        $itemsCount = $record->items()->count();
        $isApproved = in_array($status, ['approved', 'settled', 'invoiced']);
        $hasWorkOrders = ! empty($record->awarded_vendor_quote_ids);

        $getStepStyle = function (bool $isValid, string $title, string $desc) {
            $bg = $isValid ? 'rgba(16, 185, 129, 0.08)' : 'rgba(128, 128, 128, 0.04)';
            $border = $isValid ? 'rgba(16, 185, 129, 0.35)' : 'rgba(128, 128, 128, 0.18)';
            $titleColor = $isValid ? '#059669' : 'inherit';
            $descColor = $isValid ? '#10b981' : 'rgba(128, 128, 128, 0.75)';
            $icon = $isValid ? '✓' : '○';
            $iconColor = $isValid ? '#059669' : 'rgba(128, 128, 128, 0.6)';

            return '<div style="padding: 0.75rem 0.875rem; border-radius: 0.75rem; border: 1px solid '.$border.'; background-color: '.$bg.'; min-width: 0;">'.
                '<div style="display: flex; align-items: center; gap: 0.5rem;">'.
                '<span style="font-size: 0.875rem; font-weight: 800; color: '.$iconColor.'; flex-shrink: 0;">'.$icon.'</span>'.
                '<h4 style="font-size: 0.8125rem; font-weight: 700; margin: 0; color: '.$titleColor.'; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">'.e($title).'</h4>'.
                '</div>'.
                '<div style="font-size: 0.75rem; margin-top: 0.25rem; color: '.$descColor.'; line-height: 1.35;">'.e($desc).'</div>'.
                '</div>';
        };

        if ($isDwelly) {
            $s1 = $vendorQuotesCount > 0;
            $s2 = $hasWorkOrders;

            $completedCount = ($s1 ? 1 : 0) + ($s2 ? 1 : 0);
            $progress = (int) round(($completedCount / 2) * 100);
            $progressColor = $progress === 100 ? '#10b981' : ($progress >= 50 ? '#3b82f6' : '#f59e0b');

            $card1 = $getStepStyle($s1, '1. Vendor Estimates', $s1 ? "{$vendorQuotesCount} quotes recorded" : 'Add contractor trade quotes');
            $card2 = $getStepStyle($s2, '2. Contractor Work Orders', $s2 ? 'Work order(s) awarded' : 'Award winning vendor quotes');
            $stepCardsHtml = '<div style="display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0.625rem;">'.$card1.$card2.'</div>';
        } else {
            $s1 = $vendorQuotesCount > 0;
            $s2 = $itemsCount > 0;
            $s3 = $isApproved;
            $s4 = $hasWorkOrders;

            $completedCount = ($s1 ? 1 : 0) + ($s2 ? 1 : 0) + ($s3 ? 1 : 0) + ($s4 ? 1 : 0);
            $progress = (int) round(($completedCount / 4) * 100);
            $progressColor = $progress === 100 ? '#10b981' : ($progress >= 50 ? '#3b82f6' : '#f59e0b');

            $card1 = $getStepStyle($s1, '1. Vendor Estimates', $s1 ? "{$vendorQuotesCount} quotes recorded" : 'Add contractor trade quotes');
            $card2 = $getStepStyle($s2, '2. Client Pricing', $s2 ? "{$itemsCount} items (₹{$totalAmount})" : 'Set client line items & rates');
            $card3 = $getStepStyle($s3, '3. Client Approval', $s3 ? 'Customer approved & signed' : 'Pending client authorization');
            $card4 = $getStepStyle($s4, '4. Work Orders', $s4 ? 'Work order(s) awarded' : 'Award winning vendor quotes');
            $stepCardsHtml = '<div style="display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 0.625rem;">'.$card1.$card2.$card3.$card4.'</div>';
        }

        $badgeBg = match ($status) {
            'approved' => '#10b981',
            'settled' => '#047857',
            'pending_approval' => '#f59e0b',
            'rejected' => '#e11d48',
            'archived' => '#6b7280',
            default => '#2563eb',
        };

        $statusLabel = match ($status) {
            'approved' => ($isDwelly ? '✅ Work Orders Authorized' : '✅ Client Approved'),
            'settled' => '💳 Settled',
            'pending_approval' => '⏳ Pending Approval',
            'rejected' => '❌ Rejected',
            'archived' => '🗄️ Archived & Locked',
            default => ($isDwelly ? '📝 Internal Estimate' : '📝 Draft Quotation'),
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
            $archivedBanner = '<div style="margin-bottom: 0.75rem; padding: 0.75rem 1rem; border-radius: 0.5rem; border: 1px solid rgba(239, 68, 68, 0.3); border-left: 4px solid #ef4444; background-color: rgba(239, 68, 68, 0.08); font-size: 0.75rem; color: #991b1b;">🗄️ <strong>Quotation Archived & Locked:</strong> This financial quotation has been archived and locked. All fields, calculation tools, and approval actions are permanently disabled.</div>';
        }

        $html = '<div style="background-color: rgba(128, 128, 128, 0.03); border: 1px solid rgba(128, 128, 128, 0.18); border-radius: 0.875rem; padding: 1.125rem; margin-bottom: 1rem; font-family: ui-sans-serif, system-ui, sans-serif; color: inherit;">';
        $html .= $archivedBanner;
        $html .= '<div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem; margin-bottom: 0.875rem; border-bottom: 1px solid rgba(128, 128, 128, 0.12); padding-bottom: 0.75rem;">';
        $html .= '<div style="display: flex; align-items: center; gap: 0.625rem; flex-wrap: wrap;">';
        $html .= '<span style="font-size: 1.125rem; font-weight: 800; letter-spacing: -0.02em; color: inherit;">Quotation '.e($quoteNumber).'</span>';
        $html .= '<span style="display: inline-block; padding: 0.2rem 0.625rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 700; background-color: '.$badgeBg.'; color: #ffffff;">'.$statusLabel.'</span>';
        if ($isDwelly) {
            $html .= '<span style="display: inline-block; padding: 0.2rem 0.625rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 700; background-color: rgba(225, 29, 72, 0.1); color: #be123c; border: 1px solid rgba(225, 29, 72, 0.25);">🏢 100% Absorbed by Dwelly</span>';
        }
        $html .= '</div>';
        $html .= '<div style="display: flex; align-items: center; gap: 1rem; font-size: 0.8125rem;">';
        if ($isDwelly) {
            $html .= '<div><span style="color: rgba(128, 128, 128, 0.9);">Client Cost:</span> <strong style="font-size: 0.9375rem; font-weight: 800; color: #059669; margin-left: 0.25rem;">₹0.00</strong></div>';
            $html .= '<div><span style="color: rgba(128, 128, 128, 0.9);">Contractor Estimate:</span> <strong style="font-size: 0.9375rem; font-weight: 800; color: #be123c; margin-left: 0.25rem;">₹'.$subtotalVendor.'</strong></div>';
        } else {
            $html .= '<div><span style="color: rgba(128, 128, 128, 0.9);">Client Quote:</span> <strong style="font-size: 0.9375rem; font-weight: 800; color: #2563eb; margin-left: 0.25rem;">₹'.$totalAmount.'</strong></div>';
            $html .= '<div><span style="color: rgba(128, 128, 128, 0.9);">Margin:</span> <strong style="font-size: 0.9375rem; font-weight: 800; color: #16a34a; margin-left: 0.25rem;">₹'.$marginAmount.'</strong></div>';
        }
        $html .= '</div>';
        $html .= '</div>';

        // Context grid
        $html .= '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 0.75rem; font-size: 0.8125rem; margin-bottom: 0.875rem;">';
        $html .= '<div><span style="font-size: 0.6875rem; font-weight: 700; color: rgba(128, 128, 128, 0.7); text-transform: uppercase; letter-spacing: 0.05em; display: block; margin-bottom: 0.125rem;">Linked Ticket:</span>'.$ticketLink.'</div>';
        $html .= '<div><span style="font-size: 0.6875rem; font-weight: 700; color: rgba(128, 128, 128, 0.7); text-transform: uppercase; letter-spacing: 0.05em; display: block; margin-bottom: 0.125rem;">Property:</span>'.$propertyLink.'</div>';
        $html .= '<div><span style="font-size: 0.6875rem; font-weight: 700; color: rgba(128, 128, 128, 0.7); text-transform: uppercase; letter-spacing: 0.05em; display: block; margin-bottom: 0.125rem;">Owner:</span>'.$ownerLink.'</div>';
        $html .= '<div><span style="font-size: 0.6875rem; font-weight: 700; color: rgba(128, 128, 128, 0.7); text-transform: uppercase; letter-spacing: 0.05em; display: block; margin-bottom: 0.125rem;">Payer:</span><strong style="color: #2563eb;">'.$payerLabel.'</strong> <span style="color: rgba(128, 128, 128, 0.7); font-size: 0.6875rem;">(Tenant: '.$tenantLink.')</span></div>';
        $html .= '</div>';

        // Progress bar
        $html .= '<div style="margin-bottom: 0.875rem;">';
        $html .= '<div style="display: flex; justify-content: space-between; font-size: 0.75rem; font-weight: 600; margin-bottom: 0.25rem; color: rgba(128, 128, 128, 0.9);">';
        $html .= '<span>Quotation Workflow Progress</span>';
        $html .= '<span style="color: '.$progressColor.'; font-weight: 800;">'.$progress.'% Completed</span>';
        $html .= '</div>';
        $html .= '<div style="width: 100%; height: 0.5rem; background-color: rgba(128, 128, 128, 0.15); border-radius: 9999px; overflow: hidden;">';
        $html .= '<div style="width: '.$progress.'%; height: 100%; background-color: '.$progressColor.'; border-radius: 9999px; transition: width 400ms ease;"></div>';
        $html .= '</div>';
        $html .= '</div>';

        // Step cards
        $html .= $stepCardsHtml;

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
            $defIds = (array) ($it['maintenance_request_item_ids'] ?? ($it['maintenance_request_item_id'] ? [$it['maintenance_request_item_id']] : []));
            foreach ($defIds as $defId) {
                if ($defId) {
                    $defectCounts[$defId][] = $lineNumber;
                }
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

    /**
     * Helper to detect differences between line item vendor_cost and Tab 1 contractor estimates
     */
    public static function getVendorCostDiscrepanciesSummary(?MaintenanceClientQuote $record, ?Get $get = null): array
    {
        $items = $get ? ($get('items') ?? []) : [];
        if (empty($items) && $record) {
            $items = $record->items()->get()->toArray();
        }

        $ticketId = $record?->maintenance_request_id ?? ($get ? $get('maintenance_request_id') : null);
        if (! $ticketId || empty($items)) {
            return [];
        }

        $dbQuotes = MaintenanceVendorQuote::where('maintenance_request_id', $ticketId)->get()->keyBy('id');
        $discrepancies = [];

        // Sum vendor_cost per vendor_quote_id to accurately support split package line items
        $quoteCostSums = [];
        $quoteLineCount = [];

        foreach ($items as $item) {
            $vQuoteId = $item['vendor_quote_id'] ?? null;
            if ($vQuoteId) {
                $quoteCostSums[$vQuoteId] = ($quoteCostSums[$vQuoteId] ?? 0.0) + (float) ($item['vendor_cost'] ?? 0);
                $quoteLineCount[$vQuoteId] = ($quoteLineCount[$vQuoteId] ?? 0) + 1;
            }
        }

        foreach ($quoteCostSums as $vQuoteId => $totalLineVendorCost) {
            if (isset($dbQuotes[$vQuoteId])) {
                $currentDbCost = (float) $dbQuotes[$vQuoteId]->quoted_cost;
                if (abs($currentDbCost - $totalLineVendorCost) >= 0.01) {
                    $trade = $dbQuotes[$vQuoteId]->trade_title ?: "Vendor Quote #{$vQuoteId}";
                    $lineNote = ($quoteLineCount[$vQuoteId] ?? 1) > 1 ? "across {$quoteLineCount[$vQuoteId]} split lines" : "line item cost base";
                    $discrepancies[] = "Contractor estimate ({$trade}): Tab 1 estimate is ₹" . number_format($currentDbCost, 2) . " but {$lineNote} totals ₹" . number_format($totalLineVendorCost, 2);
                }
            }
        }

        return $discrepancies;
    }

    /**
     * Action to approve client quotation
     */
    public static function getApproveQuotationAction(): Action
    {
        return Action::make('approveQuoteInTab')
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

                $hasProofMedia = $record->hasMedia('approval_proof_files') || ! empty($livewire->data['approval_proof_files']);

                if (blank($notes) || blank($approvedAt) || blank($channel) || ! $hasProofMedia) {
                    Notification::make()
                        ->title('All Approval Details Required')
                        ->body('Please fill in all mandatory fields (Approval Method, Confirmation Remarks, Approval Date, and Approval Proof document) before confirming approval.')
                        ->warning()
                        ->persistent()
                        ->send();

                    return;
                }

                if (! empty($livewire->data['approval_proof_files'])) {
                    foreach ((array) $livewire->data['approval_proof_files'] as $file) {
                        if (is_string($file)) {
                            if (\Illuminate\Support\Facades\Storage::disk('public')->exists($file)) {
                                $record->addMediaFromDisk($file, 'public')->toMediaCollection('approval_proof_files');
                            } elseif (\Illuminate\Support\Facades\Storage::disk('local')->exists($file)) {
                                $record->addMediaFromDisk($file, 'local')->toMediaCollection('approval_proof_files');
                            } elseif (\Illuminate\Support\Facades\Storage::disk('local')->exists('livewire-tmp/' . $file)) {
                                $record->addMediaFromDisk('livewire-tmp/' . $file, 'local')->toMediaCollection('approval_proof_files');
                            } elseif (file_exists($file)) {
                                $record->addMedia($file)->toMediaCollection('approval_proof_files');
                            }
                        } elseif ($file instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile) {
                            $record->addMedia($file->getRealPath())->toMediaCollection('approval_proof_files');
                        }
                    }
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
            });
    }
}
