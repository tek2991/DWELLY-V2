<?php

namespace App\Filament\Resources\Billing\Tables;

use App\Domain\Finance\Services\BillingPropertyResolver;
use App\Domain\Maintenance\Models\MaintenanceRequest;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Tek2991\Accounting\Enums\BillStatus;
use Tek2991\Accounting\Models\Account;
use Tek2991\Accounting\Models\Bill;
use Tek2991\Accounting\Services\BillService;

class BillsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('bill_number')
                    ->label('Bill #')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('category')
                    ->label('Category')
                    ->badge()
                    ->state(fn (Bill $record): string => BillingPropertyResolver::categorizeBill($record)['label'])
                    ->color(fn (Bill $record): string => BillingPropertyResolver::categorizeBill($record)['color'])
                    ->sortable(false),

                TextColumn::make('property')
                    ->label('Property / Unit')
                    ->state(fn (Bill $record): string => BillingPropertyResolver::resolvePropertyLabel($record))
                    ->url(fn (Bill $record): ?string => BillingPropertyResolver::resolvePropertyUrl($record))
                    ->openUrlInNewTab()
                    ->color('primary')
                    ->weight('medium')
                    ->searchable(query: function ($query, string $search) {
                        $query->whereHas('contact', function ($q) use ($search) {
                            $q->where('name', 'like', "%{$search}%");
                        })->orWhere('notes', 'like', "%{$search}%");
                    }),

                TextColumn::make('contact.name')
                    ->label('Vendor / Contractor')
                    ->searchable()
                    ->sortable()
                    ->description(function (Bill $record) {
                        $parts = [];
                        if ($record->vendor_reference) {
                            $parts[] = "Ref: {$record->vendor_reference}";
                        }
                        if ($record->reference_type === MaintenanceRequest::class && $record->reference_id) {
                            $m = MaintenanceRequest::find($record->reference_id);
                            $parts[] = $m ? "Ticket #{$m->ticket_number}" : "Ticket #{$record->reference_id}";
                        }
                        return ! empty($parts) ? implode(' • ', $parts) : null;
                    }),

                TextColumn::make('issue_date')
                    ->label('Date')
                    ->date('d M Y')
                    ->description(fn (Bill $record) => $record->due_date ? 'Due ' . Carbon::parse($record->due_date)->format('d M Y') : null)
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
                    ->description(fn (Bill $record) => $record->amount_paid > 0 ? 'Paid: ₹' . number_format($record->amount_paid, 2) : null)
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn ($state): string => match (is_object($state) ? $state->value : (string) $state) {
                        'paid' => 'success',
                        'received', 'approved', 'partially_paid' => 'warning',
                        'draft' => 'gray',
                        'cancelled' => 'danger',
                        default => 'info',
                    }),
            ])
            ->defaultSort('issue_date', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'received' => 'Received',
                        'approved' => 'Approved',
                        'partially_paid' => 'Partially Paid',
                        'paid' => 'Paid',
                        'cancelled' => 'Cancelled',
                    ]),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('view_ticket')
                        ->label('View Ticket')
                        ->icon('heroicon-o-wrench-screwdriver')
                        ->color('gray')
                        ->visible(fn (Bill $record) => $record->reference_type === MaintenanceRequest::class && $record->reference_id)
                        ->url(function (Bill $record) {
                            try {
                                return \App\Filament\Resources\Operations\MaintenanceRequestResource::getUrl('edit', ['record' => $record->reference_id]);
                            } catch (\Throwable $e) {
                                return null;
                            }
                        })
                        ->openUrlInNewTab(),

                    Action::make('post_bill')
                        ->label('Approve & Post')
                        ->icon('heroicon-o-paper-airplane')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading(fn (Bill $record) => "Post Bill {$record->bill_number}")
                        ->modalDescription('Are you sure you want to approve and post this draft bill into the General Ledger?')
                        ->visible(fn (Bill $record) => $record->status === BillStatus::Draft && (auth()->user()?->can('billing.bill.approve') || auth()->user()?->hasAnyRole(['Business Owner', 'Accountant']) || (bool) auth()->user()?->roles->isEmpty()))
                        ->action(function (Bill $record) {
                            try {
                                app(BillService::class)->post($record);
                                Notification::make()
                                    ->title('Bill Posted')
                                    ->body("Bill {$record->bill_number} has been approved and posted.")
                                    ->success()
                                    ->send();
                            } catch (\Exception $e) {
                                Notification::make()
                                    ->title('Failed to Post Bill')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        }),

                    Action::make('record_payment')
                        ->label('Record Payment')
                        ->icon('heroicon-o-banknotes')
                        ->color('success')
                        ->visible(fn (Bill $record) => $record->balance_due > 0 && $record->status !== BillStatus::Draft && (auth()->user()?->can('billing.bill.pay') || auth()->user()?->hasAnyRole(['Business Owner', 'Accountant']) || (bool) auth()->user()?->roles->isEmpty()))
                        ->fillForm(function (Bill $record): array {
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
                                ->default(fn (Bill $record) => $record->balance_due)
                                ->required(),

                            Select::make('payment_account_id')
                                ->label('Paid From (Bank / Cash Account)')
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
                                ->label('Payment Reference (UTR / Cheque #)')
                                ->placeholder('e.g. UTR12345678'),

                            Textarea::make('notes')
                                ->label('Payment Remarks')
                                ->placeholder('e.g. Paid via RTGS / Corporate Net Banking')
                                ->rows(2),
                        ])
                        ->action(function (Bill $record, array $data) {
                            try {
                                app(BillService::class)->recordPayment($record, [
                                    'amount' => (float) $data['amount'],
                                    'payment_account_id' => (int) $data['payment_account_id'],
                                    'payment_date' => $data['payment_date'],
                                    'reference' => $data['reference'] ?? null,
                                    'notes' => $data['notes'] ?? null,
                                ]);

                                Notification::make()
                                    ->title('Payment Recorded')
                                    ->body("Recorded payment of ₹" . number_format($data['amount'], 2) . " for Bill #{$record->bill_number}")
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

                    Action::make('download_bill')
                        ->label('Bill PDF')
                        ->icon('heroicon-o-document-arrow-down')
                        ->color('primary')
                        ->visible(fn (Bill $record) => \Illuminate\Support\Facades\Route::has('billing.bill.pdf'))
                        ->url(fn (Bill $record) => route('billing.bill.pdf', ['bill' => $record]))
                        ->openUrlInNewTab(),
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
