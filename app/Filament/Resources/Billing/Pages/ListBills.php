<?php

namespace App\Filament\Resources\Billing\Pages;

use App\Domain\Finance\Services\PropertyBillCreationService;
use App\Domain\Maintenance\Models\MaintenanceRequest;
use App\Domain\Property\Models\Property;
use App\Filament\Resources\Billing\BillsResource;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Builder;
use Tek2991\Accounting\Enums\AccountType;
use Tek2991\Accounting\Enums\BillStatus;
use Tek2991\Accounting\Enums\ContactType;
use Tek2991\Accounting\Models\Account;
use Tek2991\Accounting\Models\Bill;
use Tek2991\Accounting\Models\Contact;

class ListBills extends ListRecords
{
    protected static string $resource = BillsResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('recordBill')
                ->label('Record Bill / Payable')
                ->icon('heroicon-o-plus-circle')
                ->color('primary')
                ->visible(fn (): bool => auth()->user()?->can('create', Bill::class)
                    || auth()->user()?->can('billing.bill.create')
                    || auth()->user()?->hasAnyRole(['Business Owner', 'Accountant', 'Operations Manager'])
                    || (bool) auth()->user()?->roles->isEmpty())
                ->modalHeading('Record Bill / Payable')
                ->modalDescription('Enter an incoming vendor bill for property utilities, society dues, turnover services, or general operating expenses.')
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

                            Select::make('property_id')
                                ->label('Property / Unit')
                                ->options(fn () => Property::all()->mapWithKeys(fn (Property $p) => [
                                    $p->id => "{$p->building_name}" . ($p->address_line_1 ? " - {$p->address_line_1}" : '') . " ({$p->code})",
                                ]))
                                ->searchable()
                                ->preload()
                                ->placeholder('Select Property (Optional for corporate bills)')
                                ->helperText('Attributes expense to property P&L and owner monthly payout statement.'),

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
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('Failed to Record Bill')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }

    public function getTabs(): array
    {
        $branchScopedQuery = function () {
            $query = Bill::query();
            app(\Tek2991\Accounting\Services\BranchContext::class)->applyQueryScope($query);
            return $query;
        };

        return [
            'all' => Tab::make('All Bills')
                ->badge(fn () => $branchScopedQuery()->count())
                ->badgeColor('primary'),

            'maintenance' => Tab::make('Work Orders & Repairs')
                ->icon('heroicon-o-wrench-screwdriver')
                ->badge(fn () => $branchScopedQuery()->where(function ($q) {
                    $q->where('reference_type', MaintenanceRequest::class)
                      ->orWhere('notes', 'like', '%Maintenance%')
                      ->orWhere('notes', 'like', '%Ticket%')
                      ->orWhere('notes', 'like', '%Work Order%');
                })->count())
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $query->where(function ($q) {
                    $q->where('reference_type', MaintenanceRequest::class)
                      ->orWhere('notes', 'like', '%Maintenance%')
                      ->orWhere('notes', 'like', '%Ticket%')
                      ->orWhere('notes', 'like', '%Work Order%');
                })),

            'utilities' => Tab::make('Utilities & Society')
                ->icon('heroicon-o-bolt')
                ->badge(fn () => $branchScopedQuery()->where(function ($q) {
                    $q->where(function ($sub) {
                        $sub->whereNull('reference_type')
                            ->orWhere('reference_type', '!=', MaintenanceRequest::class);
                    })->where(function ($sub) {
                        $sub->where('notes', 'like', '%Electric%')
                            ->orWhere('notes', 'like', '%Power%')
                            ->orWhere('notes', 'like', '%Water%')
                            ->orWhere('notes', 'like', '%Gas%')
                            ->orWhere('notes', 'like', '%Society%')
                            ->orWhere('notes', 'like', '%HOA%');
                    });
                })->count())
                ->badgeColor('info')
                ->modifyQueryUsing(fn (Builder $query) => $query->where(function ($q) {
                    $q->where(function ($sub) {
                        $sub->whereNull('reference_type')
                            ->orWhere('reference_type', '!=', MaintenanceRequest::class);
                    })->where(function ($sub) {
                        $sub->where('notes', 'like', '%Electric%')
                            ->orWhere('notes', 'like', '%Power%')
                            ->orWhere('notes', 'like', '%Water%')
                            ->orWhere('notes', 'like', '%Gas%')
                            ->orWhere('notes', 'like', '%Society%')
                            ->orWhere('notes', 'like', '%HOA%');
                    });
                })),

            'turnover' => Tab::make('Turnover & Services')
                ->icon('heroicon-o-sparkles')
                ->badge(fn () => $branchScopedQuery()->where(function ($q) {
                    $q->where(function ($sub) {
                        $sub->whereNull('reference_type')
                            ->orWhere('reference_type', '!=', MaintenanceRequest::class);
                    })->where(function ($sub) {
                        $sub->where('notes', 'like', '%Clean%')
                            ->orWhere('notes', 'like', '%Paint%')
                            ->orWhere('notes', 'like', '%Pest%')
                            ->orWhere('notes', 'like', '%Turnover%');
                    });
                })->count())
                ->badgeColor('success')
                ->modifyQueryUsing(fn (Builder $query) => $query->where(function ($q) {
                    $q->where(function ($sub) {
                        $sub->whereNull('reference_type')
                            ->orWhere('reference_type', '!=', MaintenanceRequest::class);
                    })->where(function ($sub) {
                        $sub->where('notes', 'like', '%Clean%')
                            ->orWhere('notes', 'like', '%Paint%')
                            ->orWhere('notes', 'like', '%Pest%')
                            ->orWhere('notes', 'like', '%Turnover%');
                    });
                })),

            'unpaid' => Tab::make('Unpaid / Due')
                ->icon('heroicon-o-exclamation-circle')
                ->badge(fn () => $branchScopedQuery()->where('balance_due', '>', 0)->where('status', '!=', 'draft')->count())
                ->badgeColor('danger')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('balance_due', '>', 0)->where('status', '!=', 'draft')),
        ];
    }
}
