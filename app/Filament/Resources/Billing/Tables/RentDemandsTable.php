<?php

namespace App\Filament\Resources\Billing\Tables;

use App\Domain\Agreement\Models\TenancyAgreement;
use App\Domain\Finance\Services\RentBillingService;
use App\Domain\Property\Models\Property;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Tek2991\Accounting\Models\Account;
use Tek2991\Accounting\Models\Invoice;
use Tek2991\Accounting\Services\InvoiceService;
use Tek2991\Accounting\Enums\InvoiceStatus;

class RentDemandsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('invoice_number')
                    ->label('Demand / Ref #')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('contact.name')
                    ->label('Tenant')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('billing_period')
                    ->label('Billing Period')
                    ->state(fn (Invoice $record) => $record->billing_period_formatted ?? ($record->issue_date ? $record->issue_date->format('M Y') : '—'))
                    ->sortable(['billing_period_start'])
                    ->color('primary')
                    ->weight('medium'),

                TextColumn::make('issue_date')
                    ->label('Issue Date')
                    ->date()
                    ->sortable(),

                TextColumn::make('due_date')
                    ->label('Due Date')
                    ->date()
                    ->sortable(),

                TextColumn::make('grand_total')
                    ->label('Total Demand')
                    ->money('INR')
                    ->weight('bold')
                    ->sortable(),

                TextColumn::make('amount_paid')
                    ->label('Paid')
                    ->money('INR')
                    ->sortable(),

                TextColumn::make('balance_due')
                    ->label('Balance Due')
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
                    Action::make('post_invoice')
                        ->label('Approve & Post')
                        ->icon('heroicon-o-paper-airplane')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading(fn (Invoice $record) => "Post Rent Demand {$record->invoice_number}")
                        ->modalDescription('Are you sure you want to approve and post this draft rent demand into the General Ledger?')
                        ->visible(fn (Invoice $record) => $record->status === InvoiceStatus::Draft)
                        ->action(function (Invoice $record) {
                            try {
                                app(InvoiceService::class)->post($record);
                                Notification::make()
                                    ->title('Demand Posted')
                                    ->body("Rent Demand {$record->invoice_number} has been approved and posted to the General Ledger.")
                                    ->success()
                                    ->send();
                            } catch (\Exception $e) {
                                Notification::make()
                                    ->title('Failed to Post Demand')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        }),

                    Action::make('record_payment')
                        ->label('Record Payment')
                        ->icon('heroicon-o-banknotes')
                        ->color('success')
                        ->visible(fn (Invoice $record) => $record->balance_due > 0 && $record->status !== InvoiceStatus::Draft)
                        ->fillForm(function (Invoice $record): array {
                            return [
                                'amount' => $record->balance_due,
                                'payment_account_id' => \Tek2991\Accounting\Facades\Accounting::getDefaultBankAccountId(),
                                'payment_date' => now()->toDateString(),
                            ];
                        })
                        ->form([
                            TextInput::make('amount')
                                ->label('Payment Amount (₹)')
                                ->numeric()
                                ->prefix('₹')
                                ->default(fn (Invoice $record) => $record->balance_due)
                                ->required(),

                            Select::make('payment_account_id')
                                ->label('Deposit To (Bank / Cash Account)')
                                ->options(fn () => Account::bankAndCashOptionsWithDefault())
                                ->default(fn () => \Tek2991\Accounting\Facades\Accounting::getDefaultBankAccountId())
                                ->allowHtml()
                                ->searchable()
                                ->preload()
                                ->required(),

                            DatePicker::make('payment_date')
                                ->label('Payment Date')
                                ->default(now())
                                ->required(),

                            TextInput::make('reference')
                                ->label('Transaction Reference / UTR Number')
                                ->placeholder('e.g. UTR12345678'),

                            Textarea::make('notes')
                                ->label('Payment Remarks')
                                ->placeholder('e.g. Received via NEFT / UPI / Cheque')
                                ->rows(2),
                        ])
                        ->action(function (Invoice $record, array $data, RentBillingService $service) {
                            $service->recordPayment(
                                $record,
                                (float) $data['amount'],
                                (int) $data['payment_account_id'],
                                $data['payment_date'],
                                $data['reference'] ?? null,
                                $data['notes'] ?? null
                            );

                            Notification::make()
                                ->title('Payment Recorded')
                                ->body("Recorded payment of ₹" . number_format($data['amount'], 2) . " for Demand #{$record->invoice_number}")
                                ->success()
                                ->send();
                        }),

                    Action::make('download_demand_notice')
                        ->label('Demand Notice PDF')
                        ->icon('heroicon-o-document-arrow-down')
                        ->color('primary')
                        ->modalHeading(fn (Invoice $record) => "Rent Demand Notice & Statement - #{$record->invoice_number}")
                        ->modalWidth(Width::SevenExtraLarge)
                        ->modalContent(fn (Invoice $record) => view('components.invoice-pdf-modal', ['invoice' => $record]))
                        ->modalSubmitAction(false)
                        ->modalCancelActionLabel('Close'),

                    Action::make('download_receipt')
                        ->label('Receipt PDF')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('success')
                        ->visible(fn (Invoice $record) => $record->payments()->exists())
                        ->modalHeading(fn (Invoice $record) => "Payment Receipt - #{$record->invoice_number}")
                        ->modalWidth(Width::SevenExtraLarge)
                        ->modalContent(fn (Invoice $record) => view('components.receipt-pdf-modal', [
                            'invoice' => $record,
                            'payment' => $record->payments()->latest()->first(),
                        ]))
                        ->modalSubmitAction(false)
                        ->modalCancelActionLabel('Close'),

                    Action::make('cancel_invoice')
                        ->label('Cancel Demand')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Cancel Rent Demand')
                        ->modalDescription('Are you sure you want to cancel this rent demand? This will automatically reverse the General Ledger accounting transactions and return the balance to zero.')
                        ->visible(fn (Invoice $record) => $record->status !== InvoiceStatus::Cancelled && $record->status !== InvoiceStatus::Paid)
                        ->action(function (Invoice $record, InvoiceService $invoiceService) {
                            $invoiceService->cancel($record);

                            Notification::make()
                                ->title('Demand Cancelled')
                                ->body("Rent Demand #{$record->invoice_number} has been cancelled and accounting entries reversed.")
                                ->success()
                                ->send();
                        }),
                ]),
            ]);
    }
}
