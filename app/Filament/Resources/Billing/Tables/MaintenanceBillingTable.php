<?php

namespace App\Filament\Resources\Billing\Tables;

use App\Domain\Maintenance\Models\MaintenanceRequest;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Tek2991\Accounting\Enums\InvoiceStatus;
use Tek2991\Accounting\Models\Account;
use Tek2991\Accounting\Models\Invoice;
use Tek2991\Accounting\Services\InvoiceService;

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
                    ->sortable()
                    ->description(function (Invoice $record) {
                        if ($record->reference_type === MaintenanceRequest::class && $record->reference_id) {
                            $maint = MaintenanceRequest::find($record->reference_id);
                            return $maint ? "Ticket #{$maint->ticket_number}" : "Ticket #{$record->reference_id}";
                        }
                        return null;
                    }),

                TextColumn::make('reference_id')
                    ->label('Ticket #')
                    ->formatStateUsing(function ($state, Invoice $record) {
                        if ($record->reference_type === MaintenanceRequest::class && $state) {
                            $maint = MaintenanceRequest::find($state);
                            return $maint ? "Ticket #{$maint->ticket_number}" : "Ticket #{$state}";
                        }
                        return '—';
                    })
                    ->url(function ($state, Invoice $record) {
                        if ($record->reference_type === MaintenanceRequest::class && $state) {
                            try {
                                return \App\Filament\Resources\Operations\MaintenanceRequestResource::getUrl('edit', ['record' => $state]);
                            } catch (\Throwable $e) {
                                return null;
                            }
                        }
                        return null;
                    })
                    ->openUrlInNewTab()
                    ->color('primary')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('issue_date')
                    ->label('Date')
                    ->date('d M Y')
                    ->description(fn (Invoice $record) => $record->due_date ? 'Due ' . Carbon::parse($record->due_date)->format('d M Y') : null)
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('due_date')
                    ->label('Due Date')
                    ->date('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('grand_total')
                    ->label('Total Amount')
                    ->money('INR')
                    ->weight('bold')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('balance_due')
                    ->label('Balance Due')
                    ->money('INR')
                    ->color(fn ($state) => $state > 0 ? 'danger' : 'success')
                    ->description(fn (Invoice $record) => $record->amount_paid > 0 ? 'Paid: ₹' . number_format($record->amount_paid, 2) : null)
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('amount_paid')
                    ->label('Paid Amount')
                    ->money('INR')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn ($state): string => match (is_object($state) ? $state->value : (string) $state) {
                        'paid' => 'success',
                        'sent', 'partially_paid' => 'warning',
                        'cancelled' => 'gray',
                        default => 'info',
                    })
                    ->toggleable(),
            ])
            ->defaultSort('issue_date', 'desc')
            ->recordActions([
                ActionGroup::make([
                    Action::make('view_ticket')
                        ->label('View Ticket')
                        ->icon('heroicon-o-wrench-screwdriver')
                        ->color('gray')
                        ->visible(fn (Invoice $record) => $record->reference_type === MaintenanceRequest::class && $record->reference_id)
                        ->url(function (Invoice $record) {
                            try {
                                return \App\Filament\Resources\Operations\MaintenanceRequestResource::getUrl('edit', ['record' => $record->reference_id]);
                            } catch (\Throwable $e) {
                                return null;
                            }
                        })
                        ->openUrlInNewTab(),

                    Action::make('post_invoice')
                        ->label('Approve & Post')
                        ->icon('heroicon-o-paper-airplane')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading(fn (Invoice $record) => "Post Invoice {$record->invoice_number}")
                        ->modalDescription('Are you sure you want to approve and post this draft client invoice into the General Ledger?')
                        ->visible(fn (Invoice $record) => $record->status === InvoiceStatus::Draft)
                        ->action(function (Invoice $record) {
                            try {
                                app(InvoiceService::class)->post($record);
                                Notification::make()
                                    ->title('Invoice Posted')
                                    ->body("Invoice {$record->invoice_number} has been approved and posted to the General Ledger.")
                                    ->success()
                                    ->send();
                            } catch (\Exception $e) {
                                Notification::make()
                                    ->title('Failed to Post Invoice')
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
                        ->action(function (Invoice $record, array $data) {
                            try {
                                app(InvoiceService::class)->recordPayment($record, [
                                    'amount' => (float) $data['amount'],
                                    'payment_account_id' => (int) $data['payment_account_id'],
                                    'payment_date' => $data['payment_date'],
                                    'reference' => $data['reference'] ?? null,
                                    'notes' => $data['notes'] ?? null,
                                ]);

                                Notification::make()
                                    ->title('Payment Recorded')
                                    ->body("Recorded payment of ₹" . number_format($data['amount'], 2) . " for Invoice #{$record->invoice_number}")
                                    ->success()
                                    ->send();
                            } catch (\Exception $e) {
                                Notification::make()
                                    ->title('Payment Failed')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        }),

                    Action::make('settle_via_reserve')
                        ->label('Settle from Reserve')
                        ->icon('heroicon-o-shield-check')
                        ->color('warning')
                        ->visible(fn (Invoice $record) => $record->status !== \Tek2991\Accounting\Enums\InvoiceStatus::Paid && $record->contact?->party_id !== null)
                        ->requiresConfirmation()
                        ->modalHeading(fn (Invoice $record) => "Settle {$record->invoice_number} from Owner Reserve")
                        ->modalDescription(function (Invoice $record) {
                            $owner = $record->contact?->party;
                            $bal = $owner ? app(\App\Domain\Finance\Services\AccountingProvisioningService::class)->getOwnerReserveBalance($owner) : 0;
                            $req = $record->balance_due > 0 ? $record->balance_due : $record->grand_total;
                            return "Available Owner Reserve Balance: ₹" . number_format($bal, 2) . ". Amount to Settle: ₹" . number_format($req, 2) . ". Do you want to draw down from the reserve float to mark this invoice as Paid?";
                        })
                        ->action(function (Invoice $record) {
                            try {
                                app(\App\Domain\Maintenance\Actions\SettleMaintenanceInvoiceViaReserveAction::class)->execute($record, auth()->user());
                                \Filament\Notifications\Notification::make()
                                    ->title('Invoice Settled via Reserve')
                                    ->body("Invoice {$record->invoice_number} has been settled from the owner's maintenance reserve float.")
                                    ->success()
                                    ->send();
                            } catch (\Exception $e) {
                                \Filament\Notifications\Notification::make()
                                    ->title('Settlement Failed')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->send();
                            }
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
                ])
                ->icon('heroicon-m-ellipsis-vertical')
                ->tooltip('Actions'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
