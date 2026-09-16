<?php

namespace App\Filament\Resources\Properties\RelationManagers;

use App\Domain\Finance\Services\BillingPropertyResolver;
use App\Domain\Finance\Services\PropertyBillCreationService;
use App\Domain\Maintenance\Models\MaintenanceRequest;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Tek2991\Accounting\Enums\AccountType;
use Tek2991\Accounting\Enums\BillStatus;
use Tek2991\Accounting\Enums\ContactType;
use Tek2991\Accounting\Facades\Accounting;
use Tek2991\Accounting\Models\Account;
use Tek2991\Accounting\Models\Bill;
use Tek2991\Accounting\Models\Contact;
use Tek2991\Accounting\Services\BillService;

class PropertyBillsRelationManager extends Component implements HasActions, HasForms, HasTable
{
    use InteractsWithActions;
    use InteractsWithForms;
    use InteractsWithTable;

    public Model $ownerRecord;

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => $this->ownerRecord->getBillsQuery())
            ->emptyStateHeading('No Bills or Payables Recorded')
            ->emptyStateDescription('Vendor bills for electricity, water, society maintenance dues, turnover services, and contractor repairs for this property will appear here.')
            ->emptyStateIcon('heroicon-o-receipt-percent')
            ->headerActions([
                Action::make('recordBill')
                    ->label('Record Bill / Payable')
                    ->icon('heroicon-o-plus-circle')
                    ->color('primary')
                    ->button()
                    ->visible(fn (): bool => auth()->user()?->can('create', Bill::class)
                        || auth()->user()?->can('billing.bill.create')
                        || auth()->user()?->hasAnyRole(['Business Owner', 'Accountant', 'Operations Manager'])
                        || (bool) auth()->user()?->roles->isEmpty())
                    ->modalHeading(fn () => "Record Bill for {$this->ownerRecord->code}")
                    ->modalDescription('Enter an incoming vendor bill for electricity, water, society maintenance, turnover services, or general operating expenses for this property.')
                    ->modalWidth(Width::TwoExtraLarge)
                    ->modalSubmitActionLabel('Record Bill')
                    ->form([
                        Grid::make(2)
                            ->schema([
                                Select::make('category')
                                    ->label('Expense Category')
                                    ->options([
                                        'utility_electricity' => '⚡ Property Utility — Electricity / Power',
                                        'utility_water' => '💧 Property Utility — Water Supply / Tanker',
                                        'utility_gas_internet' => '📶 Property Utility — Gas / Internet / Cable',
                                        'society_maintenance' => '🏢 Society Maintenance / HOA Dues',
                                        'turnover_cleaning' => '✨ Turnover Service — Deep Cleaning',
                                        'turnover_painting' => '🎨 Turnover Service — Painting & Touchups',
                                        'turnover_pest' => '🐜 Turnover Service — Pest Control',
                                        'general_vendor' => '📦 General Vendor / Operating Expense',
                                    ])
                                    ->default('utility_electricity')
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function (Set $set, $state) {
                                        $service = app(PropertyBillCreationService::class);
                                        $set('expense_account_id', $service->resolveDefaultExpenseAccountId($state));
                                    }),

                                Select::make('contact_id')
                                    ->label('Vendor / Service Provider / Board')
                                    ->options(fn () => Contact::whereIn('type', [ContactType::Vendor, 'vendor'])
                                        ->orWhereNull('type')
                                        ->pluck('name', 'id'))
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->createOptionForm([
                                        TextInput::make('name')
                                            ->label('Vendor / Board Name')
                                            ->required(),
                                        TextInput::make('phone')
                                            ->label('Phone Number'),
                                        TextInput::make('email')
                                            ->label('Email Address')
                                            ->email(),
                                    ])
                                    ->createOptionUsing(function (array $data) {
                                        $branchId = app(\Tek2991\Accounting\Services\BranchContext::class)->getCurrentId()
                                            ?? \App\Models\Branch::first()?->id;

                                        return Contact::create([
                                            'name' => $data['name'],
                                            'phone' => $data['phone'] ?? null,
                                            'email' => $data['email'] ?? null,
                                            'type' => ContactType::Vendor,
                                            'branch_id' => $branchId,
                                        ])->id;
                                    }),

                                TextInput::make('vendor_reference')
                                    ->label('Vendor Invoice / Bill Ref #')
                                    ->placeholder('e.g. BESCOM-2026-08 / HOA-Q2-102'),

                                DatePicker::make('issue_date')
                                    ->label('Bill / Invoice Date')
                                    ->default(now())
                                    ->required(),

                                DatePicker::make('due_date')
                                    ->label('Due Date')
                                    ->default(now()->addDays(14)),

                                TextInput::make('amount')
                                    ->label('Total Amount (₹)')
                                    ->numeric()
                                    ->prefix('₹')
                                    ->minValue(0.01)
                                    ->required(),

                                Select::make('expense_account_id')
                                    ->label('General Ledger Expense Account (DR)')
                                    ->options(fn () => Account::where('type', AccountType::Expense)->pluck('name', 'id'))
                                    ->default(fn (Get $get) => app(PropertyBillCreationService::class)->resolveDefaultExpenseAccountId($get('category') ?? 'utility_electricity'))
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->helperText('Debit expense account in the Chart of Accounts.'),

                                FileUpload::make('seller_invoice_path')
                                    ->label('Attach Bill / Invoice Scan (PDF or Image)')
                                    ->disk('public')
                                    ->directory('bills')
                                    ->acceptedFileTypes(['application/pdf', 'image/*'])
                                    ->maxSize(10240)
                                    ->columnSpanFull(),

                                Textarea::make('notes')
                                    ->label('Bill Remarks / Meter Reading / Period')
                                    ->placeholder('e.g. Electricity bill for period 01 Aug - 31 Aug 2026, Meter #88123')
                                    ->rows(2)
                                    ->columnSpanFull(),

                                Toggle::make('auto_post')
                                    ->label('Approve & Post to General Ledger immediately')
                                    ->default(true)
                                    ->visible(fn () => auth()->user()?->can('billing.bill.approve')
                                        || auth()->user()?->hasAnyRole(['Business Owner', 'Accountant'])
                                        || (bool) auth()->user()?->roles->isEmpty())
                                    ->helperText('Automatically generates double-entry GL journal entries (DR Expense, CR Accounts Payable).')
                                    ->columnSpanFull(),
                            ]),
                    ])
                    ->action(function (array $data) {
                        try {
                            $data['property_id'] = $this->ownerRecord->id;
                            $service = app(PropertyBillCreationService::class);
                            $bill = $service->createBill($data, auth()->user());

                            $statusMessage = $bill->status === BillStatus::Received
                                ? ' and approved into the General Ledger.'
                                : ' in Draft status.';

                            Notification::make()
                                ->title('Bill Recorded Successfully')
                                ->body("Bill #{$bill->bill_number} for ₹" . number_format($bill->grand_total, 2) . " has been recorded{$statusMessage}")
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Failed to Record Bill')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
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

                TextColumn::make('contact.name')
                    ->label('Vendor / Contractor / Board')
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
                    Action::make('view_scan')
                        ->label('View Bill Scan')
                        ->icon('heroicon-o-paper-clip')
                        ->color('info')
                        ->visible(fn (Bill $record) => ! empty($record->seller_invoice_path))
                        ->url(fn (Bill $record) => Storage::disk('public')->url($record->seller_invoice_path))
                        ->openUrlInNewTab(),

                    Action::make('download_bill')
                        ->label('Bill PDF')
                        ->icon('heroicon-o-document-arrow-down')
                        ->color('primary')
                        ->visible(fn (Bill $record) => Route::has('billing.bill.pdf'))
                        ->url(fn (Bill $record) => route('billing.bill.pdf', ['bill' => $record]))
                        ->openUrlInNewTab(),

                    Action::make('record_payment')
                        ->label('Record Payment')
                        ->icon('heroicon-o-banknotes')
                        ->color('success')
                        ->visible(fn (Bill $record) => $record->balance_due > 0 && $record->status !== BillStatus::Draft && (auth()->user()?->can('billing.bill.pay') || auth()->user()?->hasAnyRole(['Business Owner', 'Accountant']) || (bool) auth()->user()?->roles->isEmpty()))
                        ->fillForm(fn (Bill $record): array => [
                            'amount' => $record->balance_due,
                            'payment_account_id' => Accounting::getDefaultBankAccountId(),
                            'payment_date' => now()->toDateString(),
                        ])
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
                ])
                ->icon('heroicon-m-ellipsis-vertical')
                ->tooltip('Actions'),
            ]);
    }

    public function render()
    {
        return view('filament.properties.property-bills-table');
    }
}
