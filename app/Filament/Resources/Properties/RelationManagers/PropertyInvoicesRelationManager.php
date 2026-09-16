<?php

namespace App\Filament\Resources\Properties\RelationManagers;

use App\Domain\Agreement\Models\TenancyAgreement;
use App\Domain\Finance\Services\AccountingProvisioningService;
use App\Domain\Finance\Services\BillingPropertyResolver;
use App\Domain\Maintenance\Actions\SettleMaintenanceInvoiceViaReserveAction;
use App\Domain\Maintenance\Models\MaintenanceRequest;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Livewire\Component;
use Tek2991\Accounting\Enums\InvoiceStatus;
use Tek2991\Accounting\Facades\Accounting;
use Tek2991\Accounting\Models\Account;
use Tek2991\Accounting\Models\Invoice;
use Tek2991\Accounting\Services\InvoiceService;

class PropertyInvoicesRelationManager extends Component implements HasActions, HasForms, HasTable
{
    use InteractsWithActions;
    use InteractsWithForms;
    use InteractsWithTable;

    public Model $ownerRecord;

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => $this->ownerRecord->getInvoicesQuery())
            ->emptyStateHeading('No Invoices Recorded')
            ->emptyStateDescription('Client invoices, monthly rent demands, and maintenance charge notes linked to this property will appear here.')
            ->emptyStateIcon('heroicon-o-document-text')
            ->columns([
                TextColumn::make('invoice_number')
                    ->label('Invoice #')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('category')
                    ->label('Category')
                    ->badge()
                    ->state(fn (Invoice $record): string => BillingPropertyResolver::categorizeInvoice($record)['label'])
                    ->color(fn (Invoice $record): string => BillingPropertyResolver::categorizeInvoice($record)['color'])
                    ->sortable(false),

                TextColumn::make('contact.name')
                    ->label('Billed Contact / Tenant')
                    ->searchable()
                    ->sortable()
                    ->description(function (Invoice $record) {
                        if ($record->reference_type === MaintenanceRequest::class && $record->reference_id) {
                            $maint = MaintenanceRequest::find($record->reference_id);
                            return $maint ? "Ticket #{$maint->ticket_number}" : "Ticket #{$record->reference_id}";
                        }
                        if ($record->reference_type === TenancyAgreement::class && $record->reference_id) {
                            $agr = TenancyAgreement::find($record->reference_id);
                            return $agr ? "Agreement #{$agr->agreement_number}" : null;
                        }
                        return null;
                    }),

                TextColumn::make('issue_date')
                    ->label('Date')
                    ->date('d M Y')
                    ->description(fn (Invoice $record) => $record->due_date ? 'Due ' . Carbon::parse($record->due_date)->format('d M Y') : null)
                    ->sortable(),

                TextColumn::make('grand_total')
                    ->label('Total Amount')
                    ->money('INR')
                    ->weight('bold')
                    ->sortable(),

                TextColumn::make('balance_due')
                    ->label('Balance Due')
                    ->money('INR')
                    ->color(fn ($state) => $state > 0 ? 'danger' : 'success')
                    ->description(fn (Invoice $record) => $record->amount_paid > 0 ? 'Paid: ₹' . number_format($record->amount_paid, 2) : null)
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
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'sent' => 'Sent / Pending',
                        'partially_paid' => 'Partially Paid',
                        'paid' => 'Paid',
                        'cancelled' => 'Cancelled',
                    ]),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('download_invoice')
                        ->label('Invoice PDF')
                        ->icon('heroicon-o-document-arrow-down')
                        ->color('primary')
                        ->modalHeading(fn (Invoice $record) => "Invoice - {$record->invoice_number}")
                        ->modalWidth(Width::SevenExtraLarge)
                        ->modalContent(fn (Invoice $record) => view('components.invoice-pdf-modal', ['invoice' => $record]))
                        ->modalSubmitAction(false)
                        ->modalCancelActionLabel('Close'),

                    Action::make('download_receipt')
                        ->label('Receipt PDF')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('success')
                        ->visible(fn (Invoice $record) => $record->payments()->exists())
                        ->modalHeading(fn (Invoice $record) => "Payment Receipt - {$record->invoice_number}")
                        ->modalWidth(Width::SevenExtraLarge)
                        ->modalContent(fn (Invoice $record) => view('components.receipt-pdf-modal', [
                            'invoice' => $record,
                            'payment' => $record->payments()->latest()->first(),
                        ]))
                        ->modalSubmitAction(false)
                        ->modalCancelActionLabel('Close'),

                    Action::make('record_payment')
                        ->label('Record Payment')
                        ->icon('heroicon-o-banknotes')
                        ->color('success')
                        ->visible(fn (Invoice $record) => $record->balance_due > 0 && $record->status !== InvoiceStatus::Draft && (auth()->user()?->can('billing.receipt.record') || auth()->user()?->hasAnyRole(['Business Owner', 'Accountant']) || (bool) auth()->user()?->roles->isEmpty()))
                        ->fillForm(fn (Invoice $record): array => [
                            'amount' => $record->balance_due,
                            'payment_account_id' => Accounting::getDefaultBankAccountId(),
                            'payment_date' => now()->toDateString(),
                        ])
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
                                ->default(fn () => Accounting::getDefaultBankAccountId())
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
                        ->visible(fn (Invoice $record) => $record->status !== InvoiceStatus::Paid && $record->contact?->party_id !== null && (auth()->user()?->can('billing.receipt.record') || auth()->user()?->hasAnyRole(['Business Owner', 'Accountant']) || (bool) auth()->user()?->roles->isEmpty()))
                        ->requiresConfirmation()
                        ->modalHeading(fn (Invoice $record) => "Settle {$record->invoice_number} from Owner Reserve")
                        ->modalDescription(function (Invoice $record) {
                            $owner = $record->contact?->party;
                            $bal = $owner ? app(AccountingProvisioningService::class)->getOwnerReserveBalance($owner) : 0;
                            $req = $record->balance_due > 0 ? $record->balance_due : $record->grand_total;
                            return "Available Owner Reserve Balance: ₹" . number_format($bal, 2) . ". Amount to Settle: ₹" . number_format($req, 2) . ". Do you want to draw down from the reserve float to mark this invoice as Paid?";
                        })
                        ->action(function (Invoice $record) {
                            try {
                                app(SettleMaintenanceInvoiceViaReserveAction::class)->execute($record, auth()->user());
                                Notification::make()
                                    ->title('Invoice Settled via Reserve')
                                    ->body("Invoice {$record->invoice_number} has been settled from the owner's maintenance reserve float.")
                                    ->success()
                                    ->send();
                            } catch (\Exception $e) {
                                Notification::make()
                                    ->title('Settlement Failed')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        }),

                    Action::make('post_invoice')
                        ->label('Approve & Post')
                        ->icon('heroicon-o-paper-airplane')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading(fn (Invoice $record) => "Post Invoice {$record->invoice_number}")
                        ->modalDescription('Are you sure you want to approve and post this draft client invoice into the General Ledger?')
                        ->visible(fn (Invoice $record) => $record->status === InvoiceStatus::Draft && (auth()->user()?->can('billing.invoice.post') || auth()->user()?->hasAnyRole(['Business Owner', 'Accountant']) || (bool) auth()->user()?->roles->isEmpty()))
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

                    Action::make('view_agreement')
                        ->label('View Agreement')
                        ->icon('heroicon-o-document-text')
                        ->color('gray')
                        ->visible(fn (Invoice $record) => $record->reference_type === TenancyAgreement::class && $record->reference_id)
                        ->url(function (Invoice $record) {
                            try {
                                return \App\Filament\Resources\TenancyAgreements\TenancyAgreementResource::getUrl('view', ['record' => $record->reference_id]);
                            } catch (\Throwable $e) {
                                return null;
                            }
                        })
                        ->openUrlInNewTab(),
                ])
                ->icon('heroicon-m-ellipsis-vertical')
                ->tooltip('Actions'),
            ]);
    }

    public function render()
    {
        return view('filament.properties.property-invoices-table');
    }
}
