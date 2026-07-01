<?php

namespace Tek2991\Accounting\Filament\Resources\Purchases\DebitNotes\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Placeholder;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Tek2991\Accounting\Models\Contact;
use Tek2991\Accounting\Models\Bill;
use Tek2991\Accounting\Models\Item;
use Tek2991\Accounting\Models\Tax;

class DebitNoteForm
{
    public static function make(): array
    {
        $calculateTotals = function (Get $get) {
            $subtotal = 0;
            $taxableAmount = 0;
            $taxTotal = 0;
            
            foreach ((array) $get('items') as $item) {
                $qty = (float) ($item['quantity'] ?? 0);
                $price = (float) ($item['unit_price'] ?? 0);
                $baseLineTotal = $qty * $price;
                $subtotal += $baseLineTotal;
                
                $itemTaxAmount = 0;
                $isInclusive = false;
                $hasTax = false;
                if (!empty($item['tax_id'])) {
                    $hasTax = true;
                    $tax = \Tek2991\Accounting\Models\Tax::find($item['tax_id']);
                    if ($tax) {
                        $isInclusive = $tax->type === \Tek2991\Accounting\Enums\TaxType::Inclusive;
                        $rateSum = (float) $tax->total_rate;
                        
                        if ($isInclusive) {
                            $itemTaxAmount = $baseLineTotal * ($rateSum / (100 + $rateSum));
                        } else {
                            $itemTaxAmount = $baseLineTotal * ($rateSum / 100);
                        }
                    }
                }
                
                $itemPreTaxTotal = $isInclusive ? ($baseLineTotal - $itemTaxAmount) : $baseLineTotal;
                
                if ($hasTax) {
                    $taxableAmount += $itemPreTaxTotal;
                }
                
                $taxTotal += $itemTaxAmount;
            }
            
            $grandTotal = 0;
            foreach ((array) $get('items') as $item) {
                $qty = (float) ($item['quantity'] ?? 0);
                $price = (float) ($item['unit_price'] ?? 0);
                $baseLineTotal = $qty * $price;
                
                $itemTaxAmount = 0;
                $isInclusive = false;
                if (!empty($item['tax_id'])) {
                    $tax = \Tek2991\Accounting\Models\Tax::find($item['tax_id']);
                    if ($tax) {
                        $isInclusive = $tax->type === \Tek2991\Accounting\Enums\TaxType::Inclusive;
                        $rateSum = (float) $tax->total_rate;
                        if ($isInclusive) {
                            $itemTaxAmount = $baseLineTotal * ($rateSum / (100 + $rateSum));
                        } else {
                            $itemTaxAmount = $baseLineTotal * ($rateSum / 100);
                        }
                    }
                }
                $itemPreTaxTotal = $isInclusive ? ($baseLineTotal - $itemTaxAmount) : $baseLineTotal;
                $grandTotal += $itemPreTaxTotal + $itemTaxAmount;
            }
            
            return [
                'subtotal' => $subtotal,
                'taxable_amount' => $taxableAmount,
                'tax_total' => $taxTotal,
                'grand_total' => $grandTotal,
            ];
        };

        return [
            Section::make('Debit Note Details')
                ->columnSpan(1)
                ->schema([
                    Radio::make('source_type')
                        ->label('Source Type')
                        ->options([
                            'against_bill' => 'Against Existing Bill',
                            'standalone' => 'Standalone Adjustment',
                        ])
                        ->default(fn (Get $get) => filled(request()->query('bill_id')) || filled($get('bill_id')) ? 'against_bill' : 'standalone')
                        ->reactive()
                        ->dehydrated(false)
                        ->disabled(fn (?\Tek2991\Accounting\Models\DebitNote $record) => $record && $record->exists),

                    Select::make('contact_id')
                        ->label('Vendor')
                        ->relationship('contact', 'name', fn ($query) => $query->whereIn('type', [\Tek2991\Accounting\Enums\ContactType::Vendor, \Tek2991\Accounting\Enums\ContactType::Both]))
                        ->searchable()
                        ->required()
                        ->default(request()->query('contact_id'))
                        ->reactive()
                        ->disabled(fn (Get $get, ?\Tek2991\Accounting\Models\DebitNote $record) => ($record && $record->exists && filled($get('bill_id'))) || ($get('source_type') === 'against_bill' && filled($get('bill_id')) && $record && $record->exists))
                        ->afterStateUpdated(fn (Set $set) => $set('bill_id', null)),
                        
                    Select::make('bill_id')
                        ->label('Bill')
                        ->options(fn (Get $get) => Bill::where('contact_id', $get('contact_id'))->pluck('bill_number', 'id'))
                        ->searchable()
                        ->required(fn (Get $get) => $get('source_type') === 'against_bill')
                        ->visible(fn (Get $get) => $get('source_type') === 'against_bill')
                        ->default(request()->query('bill_id'))
                        ->disabled(fn (?\Tek2991\Accounting\Models\DebitNote $record) => $record && $record->exists)
                        ->reactive(),

                    DatePicker::make('issue_date')
                        ->default(now())
                        ->required(),
                        
                    Select::make('reason')
                        ->options([
                            'Goods Returned' => 'Goods Returned',
                            'Quantity Shortage' => 'Quantity Shortage',
                            'Pricing Correction' => 'Pricing Correction',
                            'Commercial Settlement' => 'Commercial Settlement',
                            'Volume Rebate' => 'Volume Rebate',
                            'Other' => 'Other',
                        ])
                        ->required(fn (Get $get) => blank($get('bill_id'))),
                ]),

            Section::make('Summary')
                ->columnSpan(1)
                ->schema([
                    Placeholder::make('subtotal_placeholder')
                        ->label('Subtotal')
                        ->content(function (Get $get) use ($calculateTotals) {
                            $currency = \Tek2991\Accounting\Facades\Accounting::getCurrency();
                            return $currency . ' ' . number_format($calculateTotals($get)['subtotal'], 2);
                        }),
                        
                    Placeholder::make('taxable_amount_placeholder')
                        ->label('Taxable Amount')
                        ->content(function (Get $get) use ($calculateTotals) {
                            $currency = \Tek2991\Accounting\Facades\Accounting::getCurrency();
                            return $currency . ' ' . number_format($calculateTotals($get)['taxable_amount'], 2);
                        }),

                    Placeholder::make('tax_total_placeholder')
                        ->label('Total Tax')
                        ->content(function (Get $get) use ($calculateTotals) {
                            $currency = \Tek2991\Accounting\Facades\Accounting::getCurrency();
                            return $currency . ' ' . number_format($calculateTotals($get)['tax_total'], 2);
                        }),

                    Placeholder::make('grand_total_placeholder')
                        ->label('Grand Total')
                        ->content(function (Get $get) use ($calculateTotals) {
                            $currency = \Tek2991\Accounting\Facades\Accounting::getCurrency();
                            return $currency . ' ' . number_format($calculateTotals($get)['grand_total'], 2);
                        })
                        ->extraAttributes(['class' => 'text-xl font-bold']),
                ]),

            Section::make('Line Items')
                ->columnSpanFull()
                ->schema([
                    Repeater::make('items')
                        ->relationship()
                        ->schema([
                            Select::make('item_id')
                                ->options(Item::pluck('name', 'id'))
                                ->searchable()
                                ->reactive()
                                ->disabled(fn (Get $get) => filled($get('../../bill_id')))
                                ->afterStateUpdated(function ($state, Set $set) {
                                    if ($item = Item::find($state)) {
                                        $set('description', $item->description);
                                        $set('unit_price', $item->purchase_price ?? $item->unit_price);
                                        $set('tax_id', $item->purchase_tax_id);
                                    }
                                }),
                                
                            TextInput::make('description')
                                ->required()
                                ->disabled(fn (Get $get) => filled($get('../../bill_id')))
                                ->maxLength(500),
                                
                            TextInput::make('quantity')
                                ->numeric()
                                ->default(1)
                                ->reactive()
                                ->required(),
                                
                            TextInput::make('unit_price')
                                ->numeric()
                                ->reactive()
                                ->disabled(fn (Get $get) => filled($get('../../bill_id')))
                                ->required(),
                                
                            Select::make('tax_id')
                                ->label('Tax')
                                ->options(Tax::pluck('name', 'id'))
                                ->reactive()
                                ->disabled(fn (Get $get) => filled($get('../../bill_id')))
                                ->searchable(),
                        ])
                        ->columns(5)
                        ->orderColumn('sort_order')
                        ->defaultItems(1)
                ])
        ];
    }
}
