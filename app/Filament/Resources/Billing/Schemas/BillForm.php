<?php

namespace App\Filament\Resources\Billing\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Tek2991\Accounting\Models\Contact;

class BillForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Vendor Bill Information')
                    ->columns(3)
                    ->components([
                        TextInput::make('bill_number')
                            ->label('Bill #')
                            ->disabled()
                            ->dehydrated()
                            ->placeholder('Auto-generated'),

                        TextInput::make('vendor_reference')
                            ->label('Vendor Invoice / Ref #')
                            ->placeholder('e.g. INV-2026-0891'),

                        Select::make('contact_id')
                            ->label('Vendor / Contractor / Utility Board')
                            ->options(fn () => Contact::pluck('name', 'id'))
                            ->searchable()
                            ->required(),

                        Select::make('status')
                            ->options([
                                'draft' => 'Draft',
                                'received' => 'Received / Pending Review',
                                'partially_paid' => 'Partially Paid',
                                'paid' => 'Paid',
                                'cancelled' => 'Cancelled',
                            ])
                            ->default('received')
                            ->required(),

                        DatePicker::make('issue_date')
                            ->label('Issue Date')
                            ->default(now())
                            ->required(),

                        DatePicker::make('due_date')
                            ->label('Due Date')
                            ->default(now()->addDays(14)),

                        \Filament\Forms\Components\FileUpload::make('seller_invoice_path')
                            ->label('Attached Bill / Receipt')
                            ->disk('public')
                            ->directory('bills')
                            ->columnSpanFull(),
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
                            ->label('Bill Remarks / Expense Purpose')
                            ->columnSpanFull()
                            ->rows(3),
                    ]),
            ]);
    }
}
