<?php

namespace App\Filament\Resources\Billing\Tables;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
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
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
