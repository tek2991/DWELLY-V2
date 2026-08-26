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
use Filament\Forms\Components\ViewField;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Support\Enums\Width;
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
                                ->body("Recorded payment of ₹" . number_format($data['amount'], 2) . " for Invoice {$record->invoice_number}")
                                ->success()
                                ->send();
                        }),

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
                    ->modalHeading('Raise Single Rent Invoice')
                    ->modalWidth(Width::FourExtraLarge)
                    ->modalDescription('Calculate and review the billing period and rent demand before generating.')
                    ->modalSubmitActionLabel('Confirm & Generate Invoice')
                    ->form([
                        Select::make('tenancy_agreement_id')
                            ->label('Tenancy Agreement')
                            ->options(fn () => TenancyAgreement::where('status', 'active')->with('property')->get()->mapWithKeys(fn ($agr) => [
                                $agr->id => "{$agr->code} - " . ($agr->property?->building_name ?? $agr->property?->name ?? 'Property')
                            ]))
                            ->searchable()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (Set $set, Get $get, $state, RentBillingService $service) {
                                if ($state) {
                                    $agr = TenancyAgreement::find($state);
                                    if ($agr) {
                                        $month = (int) ($get('month') ?: date('n'));
                                        $year = (int) ($get('year') ?: date('Y'));
                                        $calc = $service->calculateBillingDetails($agr, $month, $year);
                                        $set('billing_period_start', $calc['billing_period_start']);
                                        $set('billing_period_end', $calc['billing_period_end']);
                                        $set('rent_amount', $calc['rent_amount']);
                                    }
                                }
                            }),

                        Select::make('month')
                            ->label('Billing Month')
                            ->options([
                                1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
                                5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
                                9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December',
                            ])
                            ->default((int) date('n'))
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (Set $set, Get $get, $state, RentBillingService $service) {
                                $agrId = $get('tenancy_agreement_id');
                                if ($agrId) {
                                    $agr = TenancyAgreement::find($agrId);
                                    if ($agr) {
                                        $month = (int) $state;
                                        $year = (int) ($get('year') ?: date('Y'));
                                        $calc = $service->calculateBillingDetails($agr, $month, $year);
                                        $set('billing_period_start', $calc['billing_period_start']);
                                        $set('billing_period_end', $calc['billing_period_end']);
                                        $set('rent_amount', $calc['rent_amount']);
                                    }
                                }
                            }),

                        TextInput::make('year')
                            ->label('Billing Year')
                            ->numeric()
                            ->default((int) date('Y'))
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (Set $set, Get $get, $state, RentBillingService $service) {
                                $agrId = $get('tenancy_agreement_id');
                                if ($agrId) {
                                    $agr = TenancyAgreement::find($agrId);
                                    if ($agr) {
                                        $month = (int) ($get('month') ?: date('n'));
                                        $year = (int) $state;
                                        $calc = $service->calculateBillingDetails($agr, $month, $year);
                                        $set('billing_period_start', $calc['billing_period_start']);
                                        $set('billing_period_end', $calc['billing_period_end']);
                                        $set('rent_amount', $calc['rent_amount']);
                                    }
                                }
                            }),

                        View::make('filament.billing.single-rent-preview-modal')
                            ->columnSpanFull(),

                        DatePicker::make('billing_period_start')
                            ->label('Billing Period Start')
                            ->required(),

                        DatePicker::make('billing_period_end')
                            ->label('Billing Period End')
                            ->required(),

                        TextInput::make('rent_amount')
                            ->label('Rent Amount (₹)')
                            ->numeric()
                            ->prefix('₹')
                            ->required(),

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

                        Textarea::make('notes')
                            ->label('Notes & Remarks')
                            ->placeholder('Auto-populated with billing period details if blank')
                            ->columnSpanFull(),
                    ])
                    ->action(function (array $data, RentBillingService $service) {
                        $agreement = TenancyAgreement::findOrFail($data['tenancy_agreement_id']);
                        $overrides = [
                            'billing_period_start' => $data['billing_period_start'],
                            'billing_period_end' => $data['billing_period_end'],
                            'rent_amount' => (float) $data['rent_amount'],
                            'utility_amount' => (float) ($data['utility_amount'] ?? 0),
                            'maintenance_amount' => (float) ($data['maintenance_amount'] ?? 0),
                        ];
                        if (!empty($data['notes'])) {
                            $overrides['notes'] = $data['notes'];
                        }

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
                    ->modalHeading('Bulk Generate Monthly Rent Invoices')
                    ->modalWidth(Width::SevenExtraLarge)
                    ->modalSubmitActionLabel('Yes, Generate Invoices Now')
                    ->modifyWizardUsing(fn (Wizard $wizard) => $wizard->nextAction(fn (Action $action) => $action->label('Confirm & Generate Invoices')->color('warning')->icon('heroicon-o-arrow-right')))
                    ->steps([
                        Step::make('Review & Select Invoices')
                            ->description('Select billing cycle, filter property & choose tenancies')
                            ->icon('heroicon-o-clipboard-document-list')
                            ->schema([
                                Grid::make(3)->schema([
                                    Select::make('month')
                                        ->label('Billing Month')
                                        ->options([
                                            1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
                                            5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
                                            9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December',
                                        ])
                                        ->default((int) date('n'))
                                        ->required()
                                        ->live()
                                        ->afterStateUpdated(function (Set $set, Get $get, RentBillingService $service) {
                                            $month = (int) ($get('month') ?: date('n'));
                                            $year = (int) ($get('year') ?: date('Y'));
                                            $propertyId = $get('property_id') ? (int) $get('property_id') : null;
                                            $preview = $service->getBulkGenerationPreview($month, $year, $propertyId);
                                            $readyIds = array_map('strval', array_column(array_filter($preview['items'], fn ($i) => $i['status'] === 'ready'), 'agreement_id'));
                                            $set('selected_agreements', $readyIds);
                                        }),

                                    TextInput::make('year')
                                        ->label('Billing Year')
                                        ->numeric()
                                        ->default((int) date('Y'))
                                        ->required()
                                        ->live()
                                        ->afterStateUpdated(function (Set $set, Get $get, RentBillingService $service) {
                                            $month = (int) ($get('month') ?: date('n'));
                                            $year = (int) ($get('year') ?: date('Y'));
                                            $propertyId = $get('property_id') ? (int) $get('property_id') : null;
                                            $preview = $service->getBulkGenerationPreview($month, $year, $propertyId);
                                            $readyIds = array_map('strval', array_column(array_filter($preview['items'], fn ($i) => $i['status'] === 'ready'), 'agreement_id'));
                                            $set('selected_agreements', $readyIds);
                                        }),

                                    Select::make('property_id')
                                        ->label('Filter by Property')
                                        ->placeholder('All Properties')
                                        ->options(fn () => Property::pluck('building_name', 'id'))
                                        ->searchable()
                                        ->nullable()
                                        ->live()
                                        ->afterStateUpdated(function (Set $set, Get $get, RentBillingService $service) {
                                            $month = (int) ($get('month') ?: date('n'));
                                            $year = (int) ($get('year') ?: date('Y'));
                                            $propertyId = $get('property_id') ? (int) $get('property_id') : null;
                                            $preview = $service->getBulkGenerationPreview($month, $year, $propertyId);
                                            $readyIds = array_map('strval', array_column(array_filter($preview['items'], fn ($i) => $i['status'] === 'ready'), 'agreement_id'));
                                            $set('selected_agreements', $readyIds);
                                        }),
                                ]),

                                ViewField::make('selected_agreements')
                                    ->view('filament.billing.bulk-rent-preview-modal')
                                    ->default(function (Get $get, RentBillingService $service) {
                                        $month = (int) ($get('month') ?: date('n'));
                                        $year = (int) ($get('year') ?: date('Y'));
                                        $propertyId = $get('property_id') ? (string) $get('property_id') : null;
                                        $preview = $service->getBulkGenerationPreview($month, $year, $propertyId);

                                        return array_map('strval', array_column(array_filter($preview['items'], fn ($i) => $i['status'] === 'ready'), 'agreement_id'));
                                    })
                                    ->columnSpanFull(),
                            ]),

                        Step::make('Final Confirmation')
                            ->description('Confirm invoice creation & ledger posting')
                            ->icon('heroicon-o-shield-check')
                            ->schema([
                                View::make('filament.billing.bulk-rent-final-confirmation-modal')
                                    ->columnSpanFull(),
                            ]),
                    ])
                    ->action(function (array $data, RentBillingService $service) {
                        $selected = !empty($data['selected_agreements']) ? array_map('intval', (array) $data['selected_agreements']) : null;
                        $propertyId = !empty($data['property_id']) ? (int) $data['property_id'] : null;

                        $count = $service->bulkGenerateRentInvoices(
                            (int) $data['month'],
                            (int) $data['year'],
                            $selected,
                            $propertyId
                        );

                        if ($count === 0) {
                            Notification::make()
                                ->title('No Invoices Generated')
                                ->body('No eligible tenancy agreements were selected or ready for invoice generation.')
                                ->warning()
                                ->send();
                            return;
                        }

                        Notification::make()
                            ->title('Bulk Generation Completed')
                            ->body("Generated {$count} rent invoices for " . date('F Y', mktime(0, 0, 0, $data['month'], 1, $data['year'])))
                            ->success()
                            ->send();
                    }),
            ]);
    }
}

