<?php

namespace App\Filament\Resources\Billing\Tables;

use App\Domain\Agreement\Models\TenancyAgreement;
use App\Domain\Finance\Services\RentBillingService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Tek2991\Accounting\Models\Account;
use Tek2991\Accounting\Models\Invoice;
use Tek2991\Accounting\Services\InvoiceService;
use Tek2991\Accounting\Enums\InvoiceStatus;

class RentInvoicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('invoice_number')
                    ->label('Invoice #')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('contact.name')
                    ->label('Tenant')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('issue_date')
                    ->label('Issue Date')
                    ->date()
                    ->sortable(),

                TextColumn::make('due_date')
                    ->label('Due Date')
                    ->date()
                    ->sortable(),

                TextColumn::make('grand_total')
                    ->label('Total Amount')
                    ->money('INR')
                    ->weight('bold')
                    ->sortable(),

                TextColumn::make('amount_paid')
                    ->label('Paid')
                    ->money('INR')
                    ->sortable(),

                TextColumn::make('balance_due')
                    ->label('Balance')
                    ->money('INR')
                    ->color(fn ($state) => $state > 0 ? 'danger' : 'success')
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn ($state): string => match (is_object($state) ? $state->value : (string) $state) {
                        'paid' => 'success',
                        'sent', 'partially_paid' => 'warning',
                        'cancelled' => 'gray',
                        default => 'info',
                    }),
            ])
            ->defaultSort('issue_date', 'desc')
            ->recordActions([
                ActionGroup::make([
                    Action::make('record_payment')
                        ->label('Record Payment')
                        ->icon('heroicon-o-banknotes')
                        ->color('success')
                        ->visible(fn (Invoice $record) => $record->balance_due > 0)
                        ->form([
                            TextInput::make('amount')
                                ->label('Payment Amount (₹)')
                                ->numeric()
                                ->prefix('₹')
                                ->default(fn (Invoice $record) => $record->balance_due)
                                ->required(),

                            Select::make('payment_account_id')
                                ->label('Payment / Bank Account')
                                ->options(fn () => Account::where('type', 'asset')->pluck('name', 'id'))
                                ->required(),

                            DatePicker::make('payment_date')
                                ->label('Payment Date')
                                ->default(now())
                                ->required(),

                            TextInput::make('reference')
                                ->label('Transaction Reference / UTR Number')
                                ->placeholder('e.g. UTR12345678'),

                            Textarea::make('notes')
                                ->label('Payment Remarks'),
                        ])
                        ->action(function (Invoice $record, array $data, RentBillingService $service) {
                            $payment = $service->recordPayment(
                                $record,
                                (float) $data['amount'],
                                (int) $data['payment_account_id'],
                                $data['payment_date'],
                                $data['reference'] ?? null,
                                $data['notes'] ?? null
                            );

                            Notification::make()
                                ->title('Payment Recorded')
                                ->body("Recorded payment of ₹" . number_format($data['amount'], 2) . " for Invoice {$record->invoice_number}")
                                ->success()
                                ->send();
                        }),

                    Action::make('download_invoice')
                        ->label('Invoice PDF')
                        ->icon('heroicon-o-document-arrow-down')
                        ->color('primary')
                        ->modalHeading(fn (Invoice $record) => "Invoice - {$record->invoice_number}")
                        ->modalWidth(\Filament\Support\Enums\Width::SevenExtraLarge)
                        ->modalContent(fn (Invoice $record) => view('components.invoice-pdf-modal', ['invoice' => $record]))
                        ->modalSubmitAction(false)
                        ->modalCancelActionLabel('Close'),

                    Action::make('download_receipt')
                        ->label('Receipt PDF')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('success')
                        ->visible(fn (Invoice $record) => $record->payments()->exists())
                        ->modalHeading(fn (Invoice $record) => "Payment Receipt - {$record->invoice_number}")
                        ->modalWidth(\Filament\Support\Enums\Width::SevenExtraLarge)
                        ->modalContent(fn (Invoice $record) => view('components.receipt-pdf-modal', [
                            'invoice' => $record,
                            'payment' => $record->payments()->latest()->first(),
                        ]))
                        ->modalSubmitAction(false)
                        ->modalCancelActionLabel('Close'),

                    Action::make('cancel_invoice')
                        ->label('Cancel Invoice')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Cancel Rent Invoice')
                        ->modalDescription('Are you sure you want to cancel this rent invoice? This will automatically reverse the General Ledger accounting transactions and return the balance to zero.')
                        ->visible(fn (Invoice $record) => $record->status !== InvoiceStatus::Cancelled && $record->status !== InvoiceStatus::Paid)
                        ->action(function (Invoice $record, InvoiceService $invoiceService) {
                            $invoiceService->cancel($record);

                            Notification::make()
                                ->title('Invoice Cancelled')
                                ->body("Rent Invoice {$record->invoice_number} has been cancelled and accounting entries reversed.")
                                ->success()
                                ->send();
                        }),
                ]),
            ])
            ->toolbarActions([
                Action::make('raise_rent_invoice')
                    ->label('Raise Rent Invoice')
                    ->icon('heroicon-o-plus')
                    ->color('primary')
                    ->form([
                        Select::make('tenancy_agreement_id')
                            ->label('Tenancy Agreement')
                            ->options(fn () => TenancyAgreement::with('property')->get()->mapWithKeys(fn ($agr) => [
                                $agr->id => "{$agr->code} - " . ($agr->property?->name ?? 'Property')
                            ]))
                            ->searchable()
                            ->required()
                            ->reactive(),

                        Select::make('month')
                            ->label('Billing Month')
                            ->options([
                                1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
                                5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
                                9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December',
                            ])
                            ->default((int) date('n'))
                            ->required(),

                        TextInput::make('year')
                            ->label('Billing Year')
                            ->numeric()
                            ->default((int) date('Y'))
                            ->required(),

                        TextInput::make('rent_amount')
                            ->label('Rent Amount Override (₹)')
                            ->numeric()
                            ->prefix('₹')
                            ->placeholder('Leave blank to use agreement rent'),

                        TextInput::make('utility_amount')
                            ->label('Utility Charges (₹)')
                            ->numeric()
                            ->prefix('₹')
                            ->default(0),

                        TextInput::make('maintenance_amount')
                            ->label('Maintenance Fee (₹)')
                            ->numeric()
                            ->prefix('₹')
                            ->default(0),
                    ])
                    ->action(function (array $data, RentBillingService $service) {
                        $agreement = TenancyAgreement::findOrFail($data['tenancy_agreement_id']);
                        $overrides = [];
                        if (!empty($data['rent_amount'])) $overrides['rent_amount'] = (float) $data['rent_amount'];
                        if (!empty($data['utility_amount'])) $overrides['utility_amount'] = (float) $data['utility_amount'];
                        if (!empty($data['maintenance_amount'])) $overrides['maintenance_amount'] = (float) $data['maintenance_amount'];

                        $invoice = $service->generateRentInvoice(
                            $agreement,
                            (int) $data['month'],
                            (int) $data['year'],
                            $overrides
                        );

                        Notification::make()
                            ->title('Rent Invoice Raised')
                            ->body("Generated Invoice {$invoice->invoice_number} for {$agreement->code}")
                            ->success()
                            ->send();
                    }),

                Action::make('bulk_generate_rent')
                    ->label('Bulk Generate Monthly Rent')
                    ->icon('heroicon-o-sparkles')
                    ->color('warning')
                    ->form([
                        Select::make('month')
                            ->label('Month')
                            ->options([
                                1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
                                5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
                                9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December',
                            ])
                            ->default((int) date('n'))
                            ->required(),

                        TextInput::make('year')
                            ->label('Year')
                            ->numeric()
                            ->default((int) date('Y'))
                            ->required(),
                    ])
                    ->action(function (array $data, RentBillingService $service) {
                        $count = $service->bulkGenerateRentInvoices((int) $data['month'], (int) $data['year']);

                        Notification::make()
                            ->title('Bulk Generation Completed')
                            ->body("Generated {$count} rent invoices for " . date('F Y', mktime(0, 0, 0, $data['month'], 1, $data['year'])))
                            ->success()
                            ->send();
                    }),
            ]);
    }
}
