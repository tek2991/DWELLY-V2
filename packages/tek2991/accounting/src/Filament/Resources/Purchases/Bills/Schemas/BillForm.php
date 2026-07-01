<?php

namespace Tek2991\Accounting\Filament\Resources\Purchases\Bills\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Tek2991\Accounting\Models\Item;
use Tek2991\Accounting\Models\Tax;
use Tek2991\Accounting\Enums\AccountType;

class BillForm
{
    public static function configure(Schema $schema): Schema
    {
        $getTaxType = function ($taxId) {
            static $cache = [];
            if (!$taxId) return null;
            if (!isset($cache[$taxId])) {
                $cache[$taxId] = \Tek2991\Accounting\Models\Tax::find($taxId)?->type?->value;
            }
            return $cache[$taxId];
        };
        
        $calculateTotals = function ($get) use ($getTaxType) {
            $grossSubtotal = 0;
            $totalItemDiscounts = 0;
            $itemsData = [];
            
            foreach ((array) $get('items') as $index => $item) {
                $qty = (float) ($item['quantity'] ?? 1);
                $price = (float) ($item['unit_price'] ?? 0);
                $gross = $qty * $price;
                $grossSubtotal += $gross;
                
                $itemDiscount = 0;
                if (($item['discount_type'] ?? 'percentage') === 'percentage') {
                    $itemDiscount = $gross * ((float) ($item['discount_rate'] ?? 0) / 100);
                } else {
                    $itemDiscount = (float) ($item['discount_amount'] ?? 0);
                }
                $totalItemDiscounts += $itemDiscount;
                
                $lineNetBeforeDoc = $gross - $itemDiscount;
                
                $itemsData[$index] = [
                    'item' => $item,
                    'lineNetBeforeDoc' => $lineNetBeforeDoc,
                ];
            }
            
            $preDocSubtotal = $grossSubtotal - $totalItemDiscounts;
            $docDiscount = 0;
            if (($get('discount_type') ?? 'percentage') === 'percentage') {
                $docDiscount = $preDocSubtotal * ((float) ($get('discount_rate') ?? 0) / 100);
            } else {
                $docDiscount = (float) ($get('discount_amount') ?? 0);
            }
            
            $remainingDocDiscount = $docDiscount;
            $itemsCount = count($itemsData);
            $i = 0;
            
            $taxableAmount = 0;
            $taxTotal = 0;
            $netItemsTotal = 0;
            $taxBreakdown = [];
            
            foreach ($itemsData as $index => $data) {
                $i++;
                $item = $data['item'];
                $lineNetBeforeDoc = $data['lineNetBeforeDoc'];
                
                $allocated = 0;
                if ($itemsCount > 0) {
                    if ($i === $itemsCount) {
                        $allocated = $remainingDocDiscount;
                    } else {
                        $proportion = $preDocSubtotal > 0 ? ($lineNetBeforeDoc / $preDocSubtotal) : 0;
                        $allocated = round($docDiscount * $proportion, 2);
                        $remainingDocDiscount -= $allocated;
                    }
                }
                
                $taxableValue = $lineNetBeforeDoc - $allocated;
                
                $itemTaxAmount = 0;
                $isInclusive = false;
                $hasTax = false;
                
                if (!empty($item['tax_id'])) {
                    $hasTax = true;
                    $docMode = $get('tax_computation_mode') ?? 'exclusive';
                    
                    if ($docMode === 'manual') {
                        $isInclusive = false;
                        foreach ($item['tax_snapshot'] ?? [] as $comp) {
                            $compAmount = (float) ($comp['amount'] ?? 0);
                            $itemTaxAmount += $compAmount;
                            $name = $comp['name'] ?? 'Tax';
                            $taxBreakdown[$name] = ($taxBreakdown[$name] ?? 0) + $compAmount;
                        }
                    } else {
                        $isInclusive = $docMode === 'inclusive';
                        $rateSum = 0;
                        foreach ($item['tax_snapshot'] ?? [] as $comp) {
                            $rateSum += (float) ($comp['rate'] ?? 0);
                        }
                        if ($isInclusive) {
                            $itemTaxAmount = $taxableValue * ($rateSum / (100 + $rateSum));
                        } else {
                            $itemTaxAmount = $taxableValue * ($rateSum / 100);
                        }
                        if ($rateSum > 0) {
                            foreach ($item['tax_snapshot'] ?? [] as $comp) {
                                $rate = (float) ($comp['rate'] ?? 0);
                                $compAmount = $itemTaxAmount * ($rate / $rateSum);
                                $name = $comp['name'] ?? 'Tax';
                                $taxBreakdown[$name] = ($taxBreakdown[$name] ?? 0) + $compAmount;
                            }
                        }
                    }
                }
                
                $itemPreTaxTotal = $isInclusive ? ($taxableValue - $itemTaxAmount) : $taxableValue;
                
                if ($hasTax) {
                    $taxableAmount += $itemPreTaxTotal;
                }
                
                $netItemsTotal += $itemPreTaxTotal;
                $taxTotal += $itemTaxAmount;
            }
            
            $totalDiscount = $totalItemDiscounts + $docDiscount;
            $grandTotal = $netItemsTotal + $taxTotal;
            
            return [
                'subtotal' => $grossSubtotal,
                'line_discounts' => $totalItemDiscounts,
                'subtotal_after_line_discounts' => $preDocSubtotal,
                'doc_discount' => $docDiscount,
                'net_amount' => $preDocSubtotal - $docDiscount,
                'taxable_amount' => $taxableAmount,
                'tax_breakdown' => $taxBreakdown,
                'total_discount' => $totalDiscount,
                'tax_total' => $taxTotal,
                'grand_total' => $grandTotal,
            ];
        };

        $updateTaxesForPlaceOfSupply = function (callable $set, \Filament\Schemas\Components\Utilities\Get $get) {
            $companyContext = app(\Tek2991\Accounting\Services\CompanyContext::class);
            if (!$companyContext->isIndiaGst()) return;
            
            $companyStateId = $companyContext->getProfile()?->state_id;
            $posStateId = $get('place_of_supply_state_id');
            $isIntrastate = $companyStateId && $posStateId && ((string)$companyStateId === (string)$posStateId);
            
            $items = $get('items') ?? [];
            $updated = false;
            foreach ($items as $index => &$item) {
                if (!empty($item['tax_id'])) {
                    $tax = \Tek2991\Accounting\Models\Tax::with('components')->find($item['tax_id']);
                    if ($tax) {
                        $components = $tax->components->filter(function ($c) use ($isIntrastate) {
                            return $isIntrastate 
                                ? $c->type === \Tek2991\Accounting\Enums\TaxComponentType::Intrastate 
                                : $c->type === \Tek2991\Accounting\Enums\TaxComponentType::Interstate;
                        });
                        $item['tax_snapshot'] = $components->map(fn($c) => [
                            'account_id' => $c->account_id,
                            'name' => $c->name,
                            'rate' => $c->rate,
                            'amount' => 0,
                        ])->values()->toArray();
                        $updated = true;
                    }
                }
            }
            if ($updated) {
                $set('items', $items);
            }
        };

        return $schema
            ->components([
                Section::make('Bill Details')
                    ->columnSpan(1)
                    ->columns(2)
                    ->components([
                        Forms\Components\Placeholder::make('status')
                            ->content(fn ($record) => $record?->display_status ? strtoupper($record->display_status) : '')
                            ->hidden(fn ($record) => !$record)
                            ->extraAttributes(['class' => 'font-bold text-lg text-primary-600']),
                            
                        Forms\Components\Select::make('contact_id')
                            ->label('Vendor')
                            ->relationship('contact', 'name', fn ($query) => $query->whereIn('type', [\Tek2991\Accounting\Enums\ContactType::Vendor, \Tek2991\Accounting\Enums\ContactType::Both]))
                            ->searchable()
                            ->preload()
                            ->reactive()
                            ->afterStateUpdated(function (callable $set, $state, \Filament\Schemas\Components\Utilities\Get $get) use ($updateTaxesForPlaceOfSupply) {
                                if ($state) {
                                    $contact = \Tek2991\Accounting\Models\Contact::find($state);
                                    if ($contact && $contact->state_id) {
                                        $set('place_of_supply_state_id', $contact->state_id);
                                    }
                                }
                                $updateTaxesForPlaceOfSupply($set, $get);
                            })
                            ->required(),
                            
                        Forms\Components\Select::make('place_of_supply_state_id')
                            ->label('Place of Supply (State)')
                            ->options(fn () => \Tek2991\Accounting\Models\State::pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->reactive()
                            ->afterStateUpdated(function (callable $set, \Filament\Schemas\Components\Utilities\Get $get) use ($updateTaxesForPlaceOfSupply) {
                                $updateTaxesForPlaceOfSupply($set, $get);
                            })
                            ->visible(fn () => app(\Tek2991\Accounting\Services\CompanyContext::class)->isIndiaGst()),
                            

                        Forms\Components\TextInput::make('vendor_reference')
                            ->label('Vendor Invoice #'),
                            
                            
                        Forms\Components\DatePicker::make('issue_date')
                            ->default(now())
                            ->required(),
                            
                        Forms\Components\DatePicker::make('due_date'),
                        
                        Forms\Components\Select::make('currency_code')
                            ->options(function () {
                                try {
                                    $names = \Symfony\Component\Intl\Currencies::getNames();
                                    $formatted = [];
                                    foreach ($names as $code => $name) {
                                        $formatted[$code] = "{$code} - {$name}";
                                    }
                                    return $formatted;
                                } catch (\Throwable) {
                                    return ['USD' => 'USD - US Dollar', 'INR' => 'INR - Indian Rupee', 'EUR' => 'EUR - Euro', 'GBP' => 'GBP - British Pound'];
                                }
                            })
                            ->default(fn () => \Tek2991\Accounting\Facades\Accounting::getCurrency())
                            ->searchable()
                            ->required()
                            ->live(),
                            
                        Forms\Components\Select::make('tax_computation_mode')
                            ->options([
                                'exclusive' => 'Auto - Tax Exclusive',
                                'inclusive' => 'Auto - Tax Inclusive',
                                'manual' => 'Manual Tax (Override)',
                            ])
                            ->default('exclusive')
                            ->required()
                            ->reactive(),
                            
                        Forms\Components\Select::make('discount_type')
                            ->options([
                                \Tek2991\Accounting\Enums\DiscountType::Percentage->value => 'Percentage',
                                \Tek2991\Accounting\Enums\DiscountType::Fixed->value => 'Fixed',
                            ])
                            ->default(\Tek2991\Accounting\Enums\DiscountType::Percentage->value)
                            ->reactive(),
                            
                        Forms\Components\TextInput::make('discount_rate')
                            ->label('Discount %')
                            ->numeric()
                            ->default(0)
                            ->reactive()
                            ->visible(fn ($get) => $get('discount_type') === \Tek2991\Accounting\Enums\DiscountType::Percentage->value)
                            ->helperText(function ($get) use ($calculateTotals) {
                                $netItemsTotal = $calculateTotals($get)['subtotal'];
                                $rate = (float) ($get('discount_rate') ?? 0);
                                $amount = $netItemsTotal * ($rate / 100);
                                $currency = \Tek2991\Accounting\Enums\CurrencySymbol::getSymbol($get('currency_code') ?? 'USD');
                                return "Amount: {$currency} " . number_format($amount, 2);
                            }),
                            
                        Forms\Components\TextInput::make('discount_amount')
                            ->label('Discount Amount')
                            ->numeric()
                            ->default(0)
                            ->reactive()
                            ->visible(fn ($get) => $get('discount_type') === \Tek2991\Accounting\Enums\DiscountType::Fixed->value)
                            ->helperText(function ($get) use ($calculateTotals) {
                                $netItemsTotal = $calculateTotals($get)['subtotal'];
                                $amount = (float) ($get('discount_amount') ?? 0);
                                $pct = $netItemsTotal > 0 ? ($amount / $netItemsTotal) * 100 : 0;
                                return "Rate: " . number_format($pct, 2) . '%';
                            }),

                        Forms\Components\FileUpload::make('seller_invoice_path')
                            ->label('Seller Invoice (Optional)')
                            ->directory('accounting/seller_invoices')
                            ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                            ->maxSize(5120)
                            ->downloadable(),


                        Forms\Components\Textarea::make('notes')
                            ->columnSpanFull(),
                    ]),
                    
                Section::make('Summary')
                    ->columnSpan(1)
                    ->columns(1)
                    ->components([
                        Forms\Components\Placeholder::make('summary_details')
                            ->label('')
                            ->content(function ($get, $record) use ($calculateTotals) {
                                $totals = $calculateTotals($get);
                                $currency = \Tek2991\Accounting\Enums\CurrencySymbol::getSymbol($get('currency_code') ?? 'USD');
                                
                                $format = fn($val) => number_format($val, 2);
                                
                                $html = "<table class='w-full text-sm' style='width: 100%;'>";
                                $html .= "<tr><td class='py-1 pr-4 font-medium text-gray-500' style='text-align: left;'>Subtotal</td><td class='py-1' style='text-align: right; min-width: 100px;'>{$currency} {$format($totals['subtotal'])}</td></tr>";
                                
                                if ($totals['line_discounts'] > 0) {
                                    $html .= "<tr><td class='py-1 pr-4 font-medium text-gray-500' style='text-align: left;'>Line Discounts</td><td class='py-1 text-danger-600' style='text-align: right;'>- {$currency} {$format($totals['line_discounts'])}</td></tr>";
                                    $html .= "<tr><td class='py-1 pr-4 font-medium text-gray-500' style='text-align: left;'>Subtotal After Line Disc.</td><td class='py-1' style='text-align: right;'>{$currency} {$format($totals['subtotal_after_line_discounts'])}</td></tr>";
                                }
                                
                                if ($totals['doc_discount'] > 0) {
                                    $html .= "<tr><td class='py-1 pr-4 font-medium text-gray-500' style='text-align: left;'>Bill Discount</td><td class='py-1 text-danger-600' style='text-align: right;'>- {$currency} {$format($totals['doc_discount'])}</td></tr>";
                                }
                                
                                $html .= "<tr><td class='py-1 pr-4 font-medium text-gray-500' style='text-align: left;'>Net Amount</td><td class='py-1' style='text-align: right;'>{$currency} {$format($totals['net_amount'])}</td></tr>";
                                $html .= "<tr><td class='py-1 pr-4 font-medium text-gray-500' style='text-align: left;'>Taxable Amount</td><td class='py-1' style='text-align: right;'>{$currency} {$format($totals['taxable_amount'])}</td></tr>";
                                
                                if (!empty($totals['tax_breakdown'])) {
                                    foreach ($totals['tax_breakdown'] as $taxName => $taxAmount) {
                                        $html .= "<tr><td class='py-1 pr-4 font-medium text-gray-500' style='text-align: left;'>{$taxName}</td><td class='py-1' style='text-align: right;'>{$currency} {$format($taxAmount)}</td></tr>";
                                    }
                                } else {
                                    $html .= "<tr><td class='py-1 pr-4 font-medium text-gray-500' style='text-align: left;'>Total Tax</td><td class='py-1' style='text-align: right;'>{$currency} {$format($totals['tax_total'])}</td></tr>";
                                }
                                
                                $html .= "<tr class='text-lg font-bold'><td class='py-2 pr-4' style='text-align: left;'>Grand Total</td><td class='py-2' style='text-align: right;'>{$currency} {$format($totals['grand_total'])}</td></tr>";
                                
                                $paid = $record ? (float) $record->amount_paid : 0;
                                $balanceDue = max(0, $totals['grand_total'] - $paid);
                                
                                $html .= "<tr class='text-lg font-bold text-danger-600'><td class='py-2 pr-4' style='text-align: left;'>Balance Due</td><td class='py-2' style='text-align: right;'>{$currency} {$format($balanceDue)}</td></tr>";
                                $html .= "</table>";
                                
                                return new \Illuminate\Support\HtmlString($html);
                            }),
                            
                        Forms\Components\Placeholder::make('tax_determination')
                            ->label('Tax Determination')
                            ->content(function (\Filament\Schemas\Components\Utilities\Get $get) {
                                $companyContext = app(\Tek2991\Accounting\Services\CompanyContext::class);
                                if (!$companyContext->isIndiaGst()) return 'N/A';
                                
                                $companyStateId = $companyContext->getProfile()?->state_id;
                                $companyState = $companyStateId ? \Tek2991\Accounting\Models\State::find($companyStateId)?->name : 'Unknown';
                                
                                $posStateId = $get('place_of_supply_state_id');
                                $posState = $posStateId ? \Tek2991\Accounting\Models\State::find($posStateId)?->name : 'Unknown';
                                
                                $type = ($companyStateId && $posStateId && (string)$companyStateId === (string)$posStateId) ? 'Intrastate' : 'Interstate';
                                
                                return new \Illuminate\Support\HtmlString(
                                    "<div class='text-sm space-y-1'>" .
                                    "<div>Tax Regime: <strong>India GST</strong></div>" .
                                    "<div>Company State: <strong>{$companyState}</strong></div>" .
                                    "<div>Place of Supply: <strong>{$posState}</strong></div>" .
                                    "<div>Supply Type: <strong>{$type}</strong></div>" .
                                    "</div>"
                                );
                            })
                            ->visible(fn () => app(\Tek2991\Accounting\Services\CompanyContext::class)->isIndiaGst()),
                    ]),
                    
                Section::make('Line Items')
                    ->columnSpanFull()
                    ->components([
                        Forms\Components\Repeater::make('items')
                            ->relationship()
                            ->columns(12)
                            ->schema([
                                Forms\Components\ToggleButtons::make('line_type')
                                    ->options([
                                        'item' => 'Item',
                                        'account' => 'Account',
                                    ])
                                    ->default('item')
                                    ->inline()
                                    ->reactive()
                                    ->required()
                                    ->columnSpanFull()
                                    ->afterStateUpdated(function (callable $set) {
                                        $set('item_id', null);
                                        $set('expense_account_id', null);
                                        $set('description', null);
                                        $set('quantity', 1);
                                        $set('unit_price', null);
                                    }),
                                    
                                Forms\Components\Select::make('item_id')
                                    ->relationship('item', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->columnSpan(3)
                                    ->reactive()
                                    ->visible(fn ($get) => $get('line_type') === 'item')
                                    ->required(fn ($get) => $get('line_type') === 'item')
                                    ->afterStateUpdated(function ($state, callable $set) {
                                        if ($state) {
                                            $item = Item::find($state);
                                            if ($item) {
                                                $set('description', $item->name);
                                                $set('unit_price', $item->purchase_price);
                                                $set('hsn_sac_code', $item->hsn_sac_code);
                                            }
                                        }
                                    }),
                                    
                                Forms\Components\Select::make('expense_account_id')
                                    ->relationship('expenseAccount', 'name', fn ($query) => $query->where('type', \Tek2991\Accounting\Enums\AccountType::Expense))
                                    ->searchable()
                                    ->preload()
                                    ->columnSpan(3)
                                    ->reactive()
                                    ->visible(fn ($get) => $get('line_type') === 'account')
                                    ->required(fn ($get) => $get('line_type') === 'account'),
                                    
                                Forms\Components\TextInput::make('description')
                                    ->required()
                                    ->columnSpan(3),
                                    
                                Forms\Components\TextInput::make('quantity')
                                    ->label('Qty')
                                    ->numeric()
                                    ->default(1)
                                    ->required()
                                    ->reactive()
                                    ->columnSpan(1)
                                    ->visible(fn ($get) => $get('line_type') === 'item'),
                                    
                                Forms\Components\TextInput::make('unit_price')
                                    ->label(function ($get) {
                                        $base = $get('line_type') === 'account' ? 'Amount' : 'Unit Price';
                                        $mode = $get('../../tax_computation_mode') ?? 'exclusive';
                                        if ($mode === 'inclusive') return $base . ' (Tax Incl.)';
                                        if ($mode === 'exclusive') return $base . ' (Tax Excl.)';
                                        return $base;
                                    })
                                    ->numeric()
                                    ->required()
                                    ->reactive()
                                    ->columnSpan(3),
                                    
                                Forms\Components\Select::make('tax_id')
                                    ->relationship('tax', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->columnSpan(2)
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, callable $set, \Filament\Schemas\Components\Utilities\Get $get) {
                                        if ($state) {
                                            $tax = Tax::with('components')->find($state);
                                            if ($tax) {
                                                $companyContext = app(\Tek2991\Accounting\Services\CompanyContext::class);
                                                $components = $tax->components;
                                                
                                                if ($companyContext->isIndiaGst()) {
                                                    $companyStateId = $companyContext->getProfile()?->state_id;
                                                    $posStateId = $get('../../place_of_supply_state_id');
                                                    $isIntrastate = $companyStateId && $posStateId && ((string)$companyStateId === (string)$posStateId);
                                                    
                                                    $components = $components->filter(function ($c) use ($isIntrastate) {
                                                        return $isIntrastate 
                                                            ? $c->type === \Tek2991\Accounting\Enums\TaxComponentType::Intrastate 
                                                            : $c->type === \Tek2991\Accounting\Enums\TaxComponentType::Interstate;
                                                    });
                                                }

                                                $snapshot = $components->map(fn($c) => [
                                                    'account_id' => $c->account_id,
                                                    'name' => $c->name,
                                                    'rate' => $c->rate,
                                                    'amount' => 0, // minor units
                                                ])->values()->toArray();
                                                
                                                $set('tax_snapshot', $snapshot);
                                            }
                                        }
                                    }),
                                    
                                Forms\Components\Repeater::make('tax_snapshot')
                                    ->schema([
                                        Forms\Components\Hidden::make('account_id'),
                                        Forms\Components\Hidden::make('rate'),
                                        Forms\Components\TextInput::make('name')
                                            ->disabled()
                                            ->dehydrated(true)
                                            ->columnSpan(2),
                                        Forms\Components\TextInput::make('amount')
                                            ->numeric()
                                            ->required()
                                            ->reactive()
                                            ->dehydrateStateUsing(fn ($state) => (int) round(((float) $state) * 100))
                                            ->formatStateUsing(fn ($state) => $state !== null ? number_format((float) $state / 100, 2, '.', '') : '0.00')
                                            ->columnSpan(2),
                                    ])
                                    ->columns(4)
                                    ->addable(false)
                                    ->deletable(false)
                                    ->reorderable(false)
                                    ->visible(fn ($get) => $get('../../tax_computation_mode') === 'manual')
                                    ->columnSpanFull()
                                    ->dehydrated(fn ($get) => $get('../../tax_computation_mode') === 'manual'),
                                    
                                Forms\Components\Hidden::make('tax_snapshot_hidden')
                                    ->dehydrated(false),
                                    
                                Forms\Components\Select::make('discount_type')
                                    ->options([
                                        \Tek2991\Accounting\Enums\DiscountType::Percentage->value => 'Percentage',
                                        \Tek2991\Accounting\Enums\DiscountType::Fixed->value => 'Fixed',
                                    ])
                                    ->default(\Tek2991\Accounting\Enums\DiscountType::Percentage->value)
                                    ->reactive()
                                    ->columnSpan(3),
                                    
                                Forms\Components\TextInput::make('discount_rate')
                                    ->label('Discount %')
                                    ->numeric()
                                    ->default(0)
                                    ->reactive()
                                    ->columnSpan(3)
                                    ->visible(fn ($get) => $get('discount_type') === \Tek2991\Accounting\Enums\DiscountType::Percentage->value)
                                    ->helperText(function ($get) {
                                        $qty = (float) ($get('quantity') ?? 0);
                                        $price = (float) ($get('unit_price') ?? 0);
                                        $baseTotal = $qty * $price;
                                        $amount = $baseTotal * ((float) ($get('discount_rate') ?? 0) / 100);
                                        if ($amount <= 0) return null;
                                        $currency = \Tek2991\Accounting\Enums\CurrencySymbol::getSymbol($get('../../currency_code') ?? 'USD');
                                        return "Discount Amount: {$currency} " . number_format($amount, 2);
                                    }),
                                    
                                Forms\Components\TextInput::make('discount_amount')
                                    ->label('Discount Amount')
                                    ->numeric()
                                    ->default(0)
                                    ->reactive()
                                    ->columnSpan(3)
                                    ->visible(fn ($get) => $get('discount_type') === \Tek2991\Accounting\Enums\DiscountType::Fixed->value)
                                    ->helperText(function ($get) {
                                        $qty = (float) ($get('quantity') ?? 0);
                                        $price = (float) ($get('unit_price') ?? 0);
                                        $baseTotal = $qty * $price;
                                        $amount = (float) ($get('discount_amount') ?? 0);
                                        if ($amount <= 0) return null;
                                        $pct = $baseTotal > 0 ? ($amount / $baseTotal) * 100 : 0;
                                        return "Discount Rate: " . number_format($pct, 2) . '%';
                                    }),
                                    
                                Forms\Components\Placeholder::make('line_total')
                                    ->label('Line Total')
                                    ->content(function ($get) use ($getTaxType) {
                                        $qty = (float) ($get('quantity') ?? 0);
                                        $price = (float) ($get('unit_price') ?? 0);
                                        $baseLineTotal = $qty * $price;
                                        
                                        $itemDiscount = 0;
                                        if (($get('discount_type') ?? 'percentage') === 'percentage') {
                                            $itemDiscount = $baseLineTotal * ((float) ($get('discount_rate') ?? 0) / 100);
                                        } else {
                                            $itemDiscount = (float) ($get('discount_amount') ?? 0);
                                        }
                                        
                                        $discountedLineTotal = $baseLineTotal - $itemDiscount;
                                        
                                        $itemTaxAmount = 0;
                                        $isInclusive = false;
                                        if (!empty($get('tax_id'))) {
                                            $docMode = $get('../../tax_computation_mode') ?? 'exclusive';
                                            
                                            if ($docMode === 'manual') {
                                                foreach ($get('tax_snapshot') ?? [] as $comp) {
                                                    $itemTaxAmount += (float) ($comp['amount'] ?? 0);
                                                }
                                            } else {
                                                $isInclusive = $docMode === 'inclusive';
                                                $rateSum = 0;
                                                foreach ($get('tax_snapshot') ?? [] as $comp) {
                                                    $rateSum += (float) ($comp['rate'] ?? 0);
                                                }
                                                if ($isInclusive) {
                                                    $itemTaxAmount = $discountedLineTotal * ($rateSum / (100 + $rateSum));
                                                } else {
                                                    $itemTaxAmount = $discountedLineTotal * ($rateSum / 100);
                                                }
                                            }
                                        }
                                        
                                        $lineTotalWithTax = $discountedLineTotal + ($isInclusive ? 0 : $itemTaxAmount);
                                        $currency = \Tek2991\Accounting\Enums\CurrencySymbol::getSymbol($get('../../currency_code') ?? 'USD');
                                        
                                        $html = "<div class='text-right'><div class='text-lg font-bold'>{$currency} " . number_format($lineTotalWithTax, 2) . "</div>";
                                        if ($itemTaxAmount > 0) {
                                            $taxText = $isInclusive ? 'Incl. Tax' : '+ Tax';
                                            $html .= "<div class='text-xs text-gray-500'>{$taxText}: {$currency} " . number_format($itemTaxAmount, 2) . "</div>";
                                        }
                                        $html .= "</div>";
                                        return new \Illuminate\Support\HtmlString($html);
                                    })
                                    ->columnSpan(6),

                            ])
                            ->defaultItems(1)
                            ->orderColumn('sort_order')
                            ->addActionLabel('Add Line Item')
                    ]),
            ]);
    }
}
