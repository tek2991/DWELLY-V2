<?php

namespace App\Filament\Resources\Billing\Tables;

use Filament\Actions\Action;
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

class MaintenanceBillingTable
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
                    ->label('Billed Contact')
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
                            ->label('Payment Account')
                            ->options(fn () => Account::where('type', 'asset')->pluck('name', 'id'))
                            ->required(),

                        DatePicker::make('payment_date')
                            ->label('Payment Date')
                            ->default(now())
                            ->required(),

                        TextInput::make('reference')
                            ->label('Transaction Reference / UTR Number'),

                        Textarea::make('notes')
                            ->label('Payment Remarks'),
                    ])
                    ->action(function (Invoice $record, array $data) {
                        app(\Tek2991\Accounting\Services\InvoiceService::class)->recordPayment($record, [
                            'amount' => (float) $data['amount'],
                            'payment_account_id' => (int) $data['payment_account_id'],
                            'payment_date' => $data['payment_date'],
                            'reference' => $data['reference'] ?? null,
                            'notes' => $data['notes'] ?? null,
                        ]);

                        Notification::make()
                            ->title('Payment Recorded')
                            ->body("Recorded payment of ₹" . number_format($data['amount'], 2) . " for Maintenance Invoice {$record->invoice_number}")
                            ->success()
                            ->send();
                    }),

                Action::make('download_invoice')
                    ->label('Invoice PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('primary')
                    ->url(fn (Invoice $record) => route('billing.invoice.pdf', $record))
                    ->openUrlInNewTab(),

                Action::make('download_receipt')
                    ->label('Receipt PDF')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->visible(fn (Invoice $record) => $record->payments()->exists())
                    ->url(fn (Invoice $record) => route('billing.receipt.pdf', ['invoice' => $record->id, 'payment' => $record->payments()->latest()->first()?->id ?? 0]))
                    ->openUrlInNewTab(),

                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
