<?php

namespace App\Filament\Resources\Billing\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Tek2991\Accounting\Models\Contact;

class InvoiceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Invoice Information')
                    ->columns(3)
                    ->components([
                        TextInput::make('invoice_number')
                            ->label('Invoice #')
                            ->disabled()
                            ->dehydrated()
                            ->placeholder('Auto-generated'),

                        Select::make('contact_id')
                            ->label('Billed Contact (Tenant / Owner)')
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
                            ->default(now()->addDays(7)),

                        TextInput::make('currency_code')
                            ->label('Currency')
                            ->default('INR')
                            ->disabled(),
                    ]),

                Section::make('Financial Details')
                    ->columns(3)
                    ->components([
                        TextInput::make('grand_total')
                            ->label('Total Amount (₹)')
                            ->numeric()
                            ->prefix('₹')
                            ->disabled(),

                        TextInput::make('amount_paid')
                            ->label('Amount Paid (₹)')
                            ->numeric()
                            ->prefix('₹')
                            ->disabled(),

                        TextInput::make('balance_due')
                            ->label('Balance Due (₹)')
                            ->numeric()
                            ->prefix('₹')
                            ->disabled(),

                        Textarea::make('notes')
                            ->label('Billing Remarks / Notes')
                            ->columnSpanFull()
                            ->rows(3),
                    ]),
            ]);
    }
}
