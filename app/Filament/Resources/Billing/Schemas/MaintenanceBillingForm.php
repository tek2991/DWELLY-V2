<?php

namespace App\Filament\Resources\Billing\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Tek2991\Accounting\Models\Contact;
use Tek2991\Accounting\Models\Account;

class MaintenanceBillingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Maintenance Billing Information')
                    ->columns(3)
                    ->components([
                        TextInput::make('invoice_number')
                            ->label('Invoice / Bill #')
                            ->disabled()
                            ->degraded()
                            ->placeholder('Auto-generated'),

                        Select::make('contact_id')
                            ->label('Billed Contact (Tenant / Owner / Vendor)')
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

                Section::make('Work & Service Line Items')
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
                                    ->label('Account')
                                    ->options(fn () => Account::pluck('name', 'id'))
                                    ->columnSpan(2),
                            ])
                            ->defaultItems(1),
                    ]),

                Section::make('Notes')
                    ->components([
                        Textarea::make('notes')
                            ->label('Maintenance Ticket & Work Remarks')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
