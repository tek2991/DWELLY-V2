<?php

namespace App\Filament\Resources\Billing\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Tek2991\Accounting\Models\Contact;
use Tek2991\Accounting\Models\Account;

class RentInvoiceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Invoice Information')
                    ->columns(3)
                    ->components([
                        TextInput::make('invoice_number')
                            ->label('Invoice Number')
                            ->disabled()
                            ->dehydrated()
                            ->placeholder('Auto-generated'),

                        Select::make('contact_id')
                            ->label('Tenant (Contact)')
                            ->options(fn () => Contact::pluck('name', 'id'))
                            ->searchable()
                            ->required(),

                        Select::make('status')
                            ->options([
                                'draft' => 'Draft',
                                'sent' => 'Sent / Pending',
                                'paid' => 'Paid',
                                'partially_paid' => 'Partially Paid',
                                'cancelled' => 'Cancelled',
                            ])
                            ->default('sent')
                            ->required(),

                        DatePicker::make('issue_date')
                            ->label('Issue Date')
                            ->default(now())
                            ->required(),

                        DatePicker::make('due_date')
                            ->label('Due Date')
                            ->default(now()->startOfMonth()->addDays(5)),

                        TextInput::make('currency_code')
                            ->label('Currency')
                            ->default('INR')
                            ->disabled(),
                    ]),

                Section::make('Rent & Charge Items')
                    ->components([
                        Repeater::make('items')
                            ->relationship()
                            ->columns(12)
                            ->schema([
                                TextInput::make('description')
                                    ->required()
                                    ->columnSpan(5),

                                TextInput::make('quantity')
                                    ->numeric()
                                    ->default(1)
                                    ->required()
                                    ->columnSpan(2),

                                TextInput::make('unit_price')
                                    ->numeric()
                                    ->prefix('₹')
                                    ->required()
                                    ->columnSpan(3),

                                Select::make('income_account_id')
                                    ->label('Credit / Pass-Through Account')
                                    ->options(fn () => Account::whereIn('type', ['liability', 'revenue', 'income'])->pluck('name', 'id'))
                                    ->searchable()
                                    ->columnSpan(2),
                            ])
                            ->defaultItems(1),
                    ]),

                Section::make('Summary & Notes')
                    ->columns(2)
                    ->components([
                        Textarea::make('notes')
                            ->label('Invoice Notes / Billing Period')
                            ->columnSpanFull(),

                        Textarea::make('terms')
                            ->label('Terms & Conditions')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
