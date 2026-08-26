<?php

namespace App\Filament\Pages\Billing;

use App\Domain\Agreement\Models\TenancyAgreement;
use App\Domain\Finance\Services\RentBillingService;
use App\Domain\Finance\Services\SecurityDepositService;
use App\Domain\Maintenance\Models\MaintenanceRequest;
use App\Domain\Party\Models\Party;
use App\Domain\Property\Models\Property;
use BackedEnum;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;
use Tek2991\Accounting\Enums\BillStatus;
use Tek2991\Accounting\Enums\InvoiceStatus;
use Tek2991\Accounting\Models\Account;
use Tek2991\Accounting\Models\Bill;
use Tek2991\Accounting\Models\Invoice;
use App\Domain\Finance\Models\OwnerPayout;
use Tek2991\Accounting\Models\Transaction;
use Tek2991\Accounting\Services\BillService;
use Tek2991\Accounting\Services\InvoiceService;

class FinancialOperationsHub extends Page
{
    protected string $view = 'filament.pages.billing.financial-operations-hub';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static \UnitEnum|string|null $navigationGroup = 'Billing & Finance';

    protected static ?string $navigationLabel = 'Financial Operations Hub';

    protected static ?int $navigationSort = 0;

    public string $activeTab = 'security_deposits';

    public string $search = '';

    public ?string $propertyFilter = null;

    public string $depositSubFilter = 'all';

    public string $maintenanceSubFilter = 'all';

    public string $rentSubFilter = 'all';

    public string $vendorSubFilter = 'all';

    public string $ownerSubFilter = 'all';

    public function getTitle(): string|Htmlable
    {
        return 'Financial Operations & Collections Hub';
    }

    public function getSubheading(): ?string
    {
        return 'Track security deposits, maintenance receivables, overdue rent demands, contractor payables, and owner advances in real-time.';
    }

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                $this->recordDepositReceiptAction(),
                $this->recordDepositPlacementAction(),
                $this->recordDepositSettlementAction(),
                $this->recordInvoicePaymentAction(),
                $this->recordBillPaymentAction(),
            ])
            ->label('Record / Settle')
            ->icon('heroicon-m-plus-circle')
            ->color('primary')
            ->button(),
        ];
    }

    public static function canAccess(): bool
    {
        return true;
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    public function getMetrics(): array
    {
        $rawRentDue = Invoice::where('reference_type', TenancyAgreement::class)
            ->where('status', '!=', InvoiceStatus::Cancelled)
            ->where('balance_due', '>', 0)
            ->sum('balance_due');

        $rawMaintDue = Invoice::where(function ($q) {
            $q->where('reference_type', MaintenanceRequest::class)
              ->orWhere('notes', 'like', '%Maintenance%');
        })
        ->where('status', '!=', InvoiceStatus::Cancelled)
        ->where('balance_due', '>', 0)
        ->sum('balance_due');

        $activeDeposits = TenancyAgreement::where('status', 'active')
            ->sum('security_deposit');

        $pendingMoveOuts = TenancyAgreement::where(function ($q) {
            $q->whereIn('status', ['terminated', 'expired', 'notice_served', 'vacating'])
              ->orWhere(function ($sub) {
                  $sub->whereNotNull('vacating_date')
                      ->whereDate('vacating_date', '<=', now()->toDateString());
              });
        })->count();

        $rawVendorPayable = Bill::where('status', '!=', BillStatus::Cancelled)
            ->where('balance_due', '>', 0)
            ->sum('balance_due');

        return [
            'total_rent_due' => (float) ($rawRentDue / 100),
            'total_maintenance_due' => (float) ($rawMaintDue / 100),
            'total_active_deposits' => (float) $activeDeposits,
            'pending_moveouts_count' => (int) $pendingMoveOuts,
            'total_vendor_payables' => (float) ($rawVendorPayable / 100),
        ];
    }

    public function getTabCounts(): array
    {
        $depositCount = TenancyAgreement::count();

        $maintCount = Invoice::where(function ($q) {
            $q->where('reference_type', MaintenanceRequest::class)
              ->orWhere('notes', 'like', '%Maintenance%');
        })
        ->where('status', '!=', InvoiceStatus::Cancelled)
        ->where('balance_due', '>', 0)
        ->count();

        $rentCount = Invoice::where('reference_type', TenancyAgreement::class)
            ->where('status', '!=', InvoiceStatus::Cancelled)
            ->where('balance_due', '>', 0)
            ->count();

        $vendorCount = Bill::where('status', '!=', BillStatus::Cancelled)
            ->where('balance_due', '>', 0)
            ->count();

        $ownerCount = Property::count();

        return [
            'security_deposits' => $depositCount,
            'maintenance_invoices' => $maintCount,
            'rent_invoices' => $rentCount,
            'vendor_payables' => $vendorCount,
            'owner_advances' => $ownerCount,
        ];
    }

    /**
     * Get security deposits collection
     */
    public function getSecurityDeposits(): Collection
    {
        $query = TenancyAgreement::with(['property.owner', 'roles.party'])
            ->when($this->propertyFilter, fn ($q) => $q->where('property_id', $this->propertyFilter));

        if (!empty($this->search)) {
            $term = '%' . strtolower($this->search) . '%';
            $query->where(function ($q) use ($term) {
                $q->where('code', 'like', $term)
                  ->orWhereHas('property', fn ($p) => $p->where('building_name', 'like', $term)->orWhere('code', 'like', $term))
                  ->orWhereHas('roles.party', fn ($party) => $party->where('display_name', 'like', $term));
            });
        }

        $agreements = $query->latest()->get();

        return $agreements->map(function (TenancyAgreement $agr) {
            $primaryRole = $agr->roles->where('is_primary', true)->first() ?? $agr->roles->first();
            $tenantName = $primaryRole?->party?->display_name ?? 'Tenant';
            $propertyName = $agr->property?->building_name ?? 'Property';
            $propertyCode = $agr->property?->code ?? '-';

            // Check if receipt recorded
            $receiptTxn = Transaction::where('reference', "DEP-REC-{$agr->id}")
                ->orWhere('description', 'like', "Security Deposit Receipt: %({$agr->code})%")
                ->latest()->first();

            $placementTxn = Transaction::where('reference', "DEP-PLACE-{$agr->id}")
                ->orWhere('description', 'like', "Security Deposit Placement: {$agr->code}%")
                ->latest()->first();

            $settleTxn = Transaction::where('reference', "DEP-SETTLE-{$agr->id}")
                ->orWhere('description', 'like', "%Deposit Settlement%{$agr->code}%")
                ->latest()->first();

            $isVacating = in_array($agr->status, ['terminated', 'expired', 'notice_served', 'vacating']) 
                || ($agr->vacating_date && Carbon::parse($agr->vacating_date)->isPast());

            $isReceived = $receiptTxn !== null || !empty($agr->deposit_received_at);
            $isSettled = $settleTxn !== null;

            $placementStatus = 'Held in Bank';
            if ($placementTxn) {
                $placementStatus = 'Placed (Owner/FD)';
            } elseif (! $isReceived) {
                $placementStatus = 'Pending Collection';
            }

            return [
                'id' => $agr->id,
                'code' => $agr->code,
                'tenant_name' => $tenantName,
                'property_name' => $propertyName,
                'property_code' => $propertyCode,
                'security_deposit' => (float) ($agr->security_deposit ?? 0),
                'deposit_received' => $isReceived,
                'placement_status' => $placementStatus,
                'is_vacating' => $isVacating,
                'is_settled' => $isSettled,
                'start_date' => $agr->start_date ? Carbon::parse($agr->start_date)->format('d M Y') : '—',
                'vacating_date' => $agr->vacating_date ? Carbon::parse($agr->vacating_date)->format('d M Y') : ($agr->end_date ? Carbon::parse($agr->end_date)->format('d M Y') : '—'),
                'status' => $agr->status,
            ];
        })->when($this->depositSubFilter !== 'all', function ($collection) {
            return match ($this->depositSubFilter) {
                'pending_collection' => $collection->filter(fn ($i) => !$i['deposit_received']),
                'in_custody' => $collection->filter(fn ($i) => $i['deposit_received'] && !$i['is_settled']),
                'pending_settlement' => $collection->filter(fn ($i) => $i['is_vacating'] && !$i['is_settled']),
                default => $collection,
            };
        });
    }

    /**
     * Get unpaid maintenance invoices
     */
    public function getMaintenanceInvoices(): Collection
    {
        $query = Invoice::where(function ($q) {
            $q->where('reference_type', MaintenanceRequest::class)
              ->orWhere('notes', 'like', '%Maintenance%');
        })
        ->where('status', '!=', InvoiceStatus::Cancelled)
        ->where('balance_due', '>', 0)
        ->with(['contact']);

        if (!empty($this->search)) {
            $term = '%' . strtolower($this->search) . '%';
            $query->where(function ($q) use ($term) {
                $q->where('invoice_number', 'like', $term)
                  ->orWhereHas('contact', fn ($c) => $c->where('name', 'like', $term))
                  ->orWhere('notes', 'like', $term);
            });
        }

        $invoices = $query->latest('issue_date')->get();
        $maintIds = $invoices->pluck('reference_id')->filter();
        $maintRequests = MaintenanceRequest::with('property')->whereIn('id', $maintIds)->get()->keyBy('id');

        return $invoices->map(function (Invoice $inv) use ($maintRequests) {
            $maint = $maintRequests->get($inv->reference_id);
            $propertyName = $maint?->property?->building_name ?? $maint?->property?->name ?? '—';
            $propertyCode = $maint?->property?->code ?? '—';
            $propertyId = $maint?->property_id;

            $isOverdue = $inv->due_date && Carbon::parse($inv->due_date)->isPast();
            $overdueDays = $inv->due_date && $isOverdue ? Carbon::parse($inv->due_date)->diffInDays(now()) : 0;

            return [
                'id' => $inv->id,
                'invoice_number' => $inv->invoice_number,
                'contact_name' => $inv->contact?->name ?? 'Contact',
                'property_id' => $propertyId,
                'property_name' => $propertyName,
                'property_code' => $propertyCode,
                'issue_date' => $inv->issue_date ? Carbon::parse($inv->issue_date)->format('d M Y') : '—',
                'due_date' => $inv->due_date ? Carbon::parse($inv->due_date)->format('d M Y') : '—',
                'grand_total' => (float) $inv->grand_total,
                'amount_paid' => (float) $inv->amount_paid,
                'balance_due' => (float) $inv->balance_due,
                'status' => $inv->status instanceof \BackedEnum ? $inv->status->value : (string) $inv->status,
                'is_overdue' => $isOverdue,
                'overdue_days' => $overdueDays,
                'notes' => $inv->notes,
            ];
        })
        ->when($this->propertyFilter, fn ($c) => $c->filter(fn ($i) => (string) $i['property_id'] === (string) $this->propertyFilter))
        ->when($this->maintenanceSubFilter !== 'all', function ($collection) {
            return match ($this->maintenanceSubFilter) {
                'overdue' => $collection->filter(fn ($i) => $i['is_overdue']),
                'partially_paid' => $collection->filter(fn ($i) => $i['amount_paid'] > 0),
                'unpaid' => $collection->filter(fn ($i) => $i['amount_paid'] == 0),
                default => $collection,
            };
        });
    }

    /**
     * Get unpaid rent invoices
     */
    public function getRentInvoices(): Collection
    {
        $query = Invoice::where('reference_type', TenancyAgreement::class)
            ->where('status', '!=', InvoiceStatus::Cancelled)
            ->where('balance_due', '>', 0)
            ->with(['contact']);

        if (!empty($this->search)) {
            $term = '%' . strtolower($this->search) . '%';
            $query->where(function ($q) use ($term) {
                $q->where('invoice_number', 'like', $term)
                  ->orWhereHas('contact', fn ($c) => $c->where('name', 'like', $term))
                  ->orWhere('notes', 'like', $term);
            });
        }

        $invoices = $query->latest('issue_date')->get();
        $agrIds = $invoices->pluck('reference_id')->filter();
        $agreements = TenancyAgreement::with('property')->whereIn('id', $agrIds)->get()->keyBy('id');

        return $invoices->map(function (Invoice $inv) use ($agreements) {
            $agr = $agreements->get($inv->reference_id);
            $propertyName = $agr?->property?->building_name ?? $agr?->property?->name ?? '—';
            $propertyCode = $agr?->property?->code ?? '—';
            $propertyId = $agr?->property_id;

            $isOverdue = $inv->due_date && Carbon::parse($inv->due_date)->isPast();
            $overdueDays = $inv->due_date && $isOverdue ? Carbon::parse($inv->due_date)->diffInDays(now()) : 0;

            return [
                'id' => $inv->id,
                'invoice_number' => $inv->invoice_number,
                'tenant_name' => $inv->contact?->name ?? 'Tenant',
                'property_id' => $propertyId,
                'property_name' => $propertyName,
                'property_code' => $propertyCode,
                'billing_period' => $inv->billing_period_formatted ?? ($inv->issue_date ? Carbon::parse($inv->issue_date)->format('M Y') : '—'),
                'issue_date' => $inv->issue_date ? Carbon::parse($inv->issue_date)->format('d M Y') : '—',
                'due_date' => $inv->due_date ? Carbon::parse($inv->due_date)->format('d M Y') : '—',
                'grand_total' => (float) $inv->grand_total,
                'amount_paid' => (float) $inv->amount_paid,
                'balance_due' => (float) $inv->balance_due,
                'status' => $inv->status instanceof \BackedEnum ? $inv->status->value : (string) $inv->status,
                'is_overdue' => $isOverdue,
                'overdue_days' => $overdueDays,
            ];
        })
        ->when($this->propertyFilter, fn ($c) => $c->filter(fn ($i) => (string) $i['property_id'] === (string) $this->propertyFilter))
        ->when($this->rentSubFilter !== 'all', function ($collection) {
            return match ($this->rentSubFilter) {
                'overdue' => $collection->filter(fn ($i) => $i['is_overdue']),
                'partially_paid' => $collection->filter(fn ($i) => $i['amount_paid'] > 0),
                'unpaid' => $collection->filter(fn ($i) => $i['amount_paid'] == 0),
                default => $collection,
            };
        });
    }

    /**
     * Get vendor payables
     */
    public function getVendorBills(): Collection
    {
        $query = Bill::where('status', '!=', BillStatus::Cancelled)
            ->where('balance_due', '>', 0)
            ->with(['contact']);

        if (!empty($this->search)) {
            $term = '%' . strtolower($this->search) . '%';
            $query->where(function ($q) use ($term) {
                $q->where('bill_number', 'like', $term)
                  ->orWhere('vendor_reference', 'like', $term)
                  ->orWhereHas('contact', fn ($c) => $c->where('name', 'like', $term));
            });
        }

        return $query->latest('issue_date')->get()->map(function (Bill $bill) {
            $isOverdue = $bill->due_date && Carbon::parse($bill->due_date)->isPast();

            return [
                'id' => $bill->id,
                'bill_number' => $bill->bill_number,
                'vendor_reference' => $bill->vendor_reference ?? '-',
                'vendor_name' => $bill->contact?->name ?? 'Vendor',
                'issue_date' => $bill->issue_date ? Carbon::parse($bill->issue_date)->format('d M Y') : '—',
                'due_date' => $bill->due_date ? Carbon::parse($bill->due_date)->format('d M Y') : '—',
                'grand_total' => (float) $bill->grand_total,
                'amount_paid' => (float) $bill->amount_paid,
                'balance_due' => (float) $bill->balance_due,
                'status' => $bill->status instanceof \BackedEnum ? $bill->status->value : (string) $bill->status,
                'is_overdue' => $isOverdue,
            ];
        });
    }

    /**
     * Get owner advances and reserves
     */
    public function getOwnerAdvances(): Collection
    {
        $query = Property::with(['owner'])
            ->when($this->propertyFilter, fn ($q) => $q->where('id', $this->propertyFilter));

        if (!empty($this->search)) {
            $term = '%' . strtolower($this->search) . '%';
            $query->where(function ($q) use ($term) {
                $q->where('building_name', 'like', $term)
                  ->orWhere('code', 'like', $term)
                  ->orWhereHas('owner', fn ($o) => $o->where('display_name', 'like', $term));
            });
        }

        $properties = $query->get();
        $propIds = $properties->pluck('id');
        $payoutsByProp = OwnerPayout::whereIn('property_id', $propIds)->get()->groupBy('property_id');

        return $properties->map(function (Property $prop) use ($payoutsByProp) {
            $payouts = $payoutsByProp->get($prop->id, collect());
            $latestPayout = $payouts->sortByDesc('processed_at')->first();
            $advanceOffsetTotal = (float) $payouts->sum('advance_offset');

            return [
                'id' => $prop->id,
                'property_name' => $prop->building_name ?? 'Property',
                'property_code' => $prop->code ?? '-',
                'owner_name' => $prop->owner?->display_name ?? 'Owner',
                'total_offset_recovered' => $advanceOffsetTotal,
                'last_payout_date' => $latestPayout?->processed_at ? Carbon::parse($latestPayout->processed_at)->format('d M Y') : '—',
                'last_payout_amount' => (float) ($latestPayout?->amount ?? 0),
            ];
        });
    }

    /**
     * Filament Page Actions & Modals
     */
    protected function getActions(): array
    {
        return [
            $this->recordDepositReceiptAction(),
            $this->recordDepositPlacementAction(),
            $this->recordDepositSettlementAction(),
            $this->recordInvoicePaymentAction(),
            $this->recordBillPaymentAction(),
        ];
    }

    public function recordDepositReceiptAction(): Action
    {
        return Action::make('recordDepositReceipt')
            ->label('Record Deposit Receipt')
            ->icon('heroicon-o-arrow-down-left')
            ->color('success')
            ->modalHeading('Record Tenant Security Deposit Receipt')
            ->modalWidth(Width::Large)
            ->modalDescription('Post double-entry transaction (DR Bank Account, CR Tenant Deposit Liability).')
            ->fillForm(fn (array $arguments) => $arguments)
            ->form([
                Select::make('tenancy_agreement_id')
                    ->label('Tenancy Agreement')
                    ->options(fn () => TenancyAgreement::with('property')->get()->mapWithKeys(fn ($agr) => [
                        $agr->id => "{$agr->code} - {$agr->property?->building_name} (Deposit: ₹" . number_format($agr->security_deposit ?? 0, 2) . ")"
                    ]))
                    ->searchable()
                    ->required()
                    ->live()
                    ->afterStateUpdated(function ($set, $state) {
                        if ($state) {
                            $agr = TenancyAgreement::find($state);
                            if ($agr) {
                                $set('amount', $agr->security_deposit);
                            }
                        }
                    }),

                TextInput::make('amount')
                    ->label('Deposit Amount Received (₹)')
                    ->numeric()
                    ->prefix('₹')
                    ->required(),

                Select::make('bank_account_id')
                    ->label('Receiving Bank / Cash Account')
                    ->options(fn () => Account::where('type', 'asset')->pluck('name', 'id'))
                    ->required(),

                DatePicker::make('payment_date')
                    ->label('Receipt Date')
                    ->default(now())
                    ->required(),

                TextInput::make('reference')
                    ->label('Reference / UTR Number')
                    ->placeholder('e.g. UTR-DEP-12345'),
            ])
            ->action(function (array $data, SecurityDepositService $service) {
                $agreement = TenancyAgreement::findOrFail($data['tenancy_agreement_id']);
                $bankAccount = Account::find($data['bank_account_id']);

                $service->recordDepositReceipt(
                    $agreement,
                    (float) $data['amount'],
                    $bankAccount,
                    $data['reference'] ?? null,
                    $data['payment_date']
                );

                Notification::make()
                    ->title('Deposit Receipt Recorded')
                    ->body("Successfully recorded ₹" . number_format($data['amount'], 2) . " deposit receipt for {$agreement->code}")
                    ->success()
                    ->send();
            });
    }

    public function recordDepositPlacementAction(): Action
    {
        return Action::make('recordDepositPlacement')
            ->label('Place Security Deposit')
            ->icon('heroicon-o-building-library')
            ->color('warning')
            ->modalHeading('Place Deposit: Owner Transfer vs FD Escrow')
            ->modalWidth(Width::Large)
            ->modalDescription('Transfer security deposit to Property Owner and/or place into Fixed Deposit Escrow.')
            ->fillForm(fn (array $arguments) => $arguments)
            ->form([
                Select::make('tenancy_agreement_id')
                    ->label('Tenancy Agreement')
                    ->options(fn () => TenancyAgreement::with('property')->get()->mapWithKeys(fn ($agr) => [
                        $agr->id => "{$agr->code} - {$agr->property?->building_name} (Agreed: ₹" . number_format($agr->security_deposit ?? 0, 2) . ")"
                    ]))
                    ->searchable()
                    ->required(),

                TextInput::make('owner_amount')
                    ->label('Transfer to Owner (₹)')
                    ->numeric()
                    ->prefix('₹')
                    ->default(0)
                    ->helperText('Amount to transfer directly to the Property Owner deposit account.'),

                TextInput::make('fd_amount')
                    ->label('Fixed Deposit Escrow (₹)')
                    ->numeric()
                    ->prefix('₹')
                    ->default(0)
                    ->helperText('Amount to lock into Company FD Escrow account.'),

                Select::make('bank_account_id')
                    ->label('Disbursement Bank Account')
                    ->options(fn () => Account::where('type', 'asset')->pluck('name', 'id'))
                    ->required(),

                DatePicker::make('placement_date')
                    ->label('Placement Date')
                    ->default(now())
                    ->required(),

                TextInput::make('reference')
                    ->label('Transaction Reference')
                    ->placeholder('e.g. FD-REF-9988'),
            ])
            ->action(function (array $data, SecurityDepositService $service) {
                $agreement = TenancyAgreement::findOrFail($data['tenancy_agreement_id']);
                $bankAccount = Account::find($data['bank_account_id']);

                $service->recordDepositPlacement(
                    $agreement,
                    (float) ($data['owner_amount'] ?? 0),
                    (float) ($data['fd_amount'] ?? 0),
                    $bankAccount,
                    $data['reference'] ?? null,
                    $data['placement_date']
                );

                Notification::make()
                    ->title('Deposit Placement Recorded')
                    ->body("Recorded deposit placement for {$agreement->code}")
                    ->success()
                    ->send();
            });
    }

    public function recordDepositSettlementAction(): Action
    {
        return Action::make('recordDepositSettlement')
            ->label('Process Move-Out Settlement')
            ->icon('heroicon-o-check-badge')
            ->color('danger')
            ->modalHeading('Process Move-Out Deposit Settlement & Refunds')
            ->modalWidth(Width::Large)
            ->modalDescription('Apply repair/damage deductions and refund remaining security deposit to tenant.')
            ->fillForm(fn (array $arguments) => $arguments)
            ->form([
                Select::make('tenancy_agreement_id')
                    ->label('Tenancy Agreement')
                    ->options(fn () => TenancyAgreement::with('property')->get()->mapWithKeys(fn ($agr) => [
                        $agr->id => "{$agr->code} - {$agr->property?->building_name} (Deposit: ₹" . number_format($agr->security_deposit ?? 0, 2) . ")"
                    ]))
                    ->searchable()
                    ->required(),

                TextInput::make('deduction_amount')
                    ->label('Repair / Damage Deduction (₹)')
                    ->numeric()
                    ->prefix('₹')
                    ->default(0)
                    ->helperText('Amount to deduct from tenant deposit towards painting/repairs.'),

                Select::make('contractor_party_id')
                    ->label('Contractor / Painter for Repair Deduction')
                    ->options(fn () => Party::pluck('display_name', 'id'))
                    ->searchable()
                    ->nullable(),

                TextInput::make('fd_liquidation')
                    ->label('Refund from FD Escrow (₹)')
                    ->numeric()
                    ->prefix('₹')
                    ->default(0),

                TextInput::make('owner_refund')
                    ->label('Refund from Owner Held Deposit (₹)')
                    ->numeric()
                    ->prefix('₹')
                    ->default(0),

                DatePicker::make('settlement_date')
                    ->label('Settlement Date')
                    ->default(now())
                    ->required(),
            ])
            ->action(function (array $data, SecurityDepositService $service) {
                $agreement = TenancyAgreement::findOrFail($data['tenancy_agreement_id']);
                $contractor = !empty($data['contractor_party_id']) ? Party::find($data['contractor_party_id']) : null;

                $service->recordDepositSettlement(
                    $agreement,
                    (float) ($data['deduction_amount'] ?? 0),
                    $contractor,
                    (float) ($data['fd_liquidation'] ?? 0),
                    (float) ($data['owner_refund'] ?? 0),
                    $data['settlement_date']
                );

                Notification::make()
                    ->title('Move-Out Settlement Completed')
                    ->body("Processed deposit settlement and refunds for {$agreement->code}")
                    ->success()
                    ->send();
            });
    }

    public function recordInvoicePaymentAction(): Action
    {
        return Action::make('recordInvoicePayment')
            ->label('Record Invoice Payment')
            ->icon('heroicon-o-banknotes')
            ->color('success')
            ->modalHeading('Record Payment against Invoice')
            ->modalWidth(Width::Large)
            ->fillForm(fn (array $arguments) => $arguments)
            ->form([
                Select::make('invoice_id')
                    ->label('Invoice')
                    ->options(fn () => Invoice::where('balance_due', '>', 0)->pluck('invoice_number', 'id'))
                    ->searchable()
                    ->required()
                    ->live()
                    ->afterStateUpdated(function ($set, $state) {
                        if ($state) {
                            $inv = Invoice::find($state);
                            if ($inv) {
                                $set('amount', $inv->balance_due);
                            }
                        }
                    }),

                TextInput::make('amount')
                    ->label('Payment Amount (₹)')
                    ->numeric()
                    ->prefix('₹')
                    ->required(),

                Select::make('bank_account_id')
                    ->label('Deposit / Bank Account')
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
            ->action(function (array $data, RentBillingService $service) {
                $invoice = Invoice::findOrFail($data['invoice_id']);

                $service->recordPayment(
                    $invoice,
                    (float) $data['amount'],
                    (int) $data['bank_account_id'],
                    $data['payment_date'],
                    $data['reference'] ?? null,
                    $data['notes'] ?? null
                );

                Notification::make()
                    ->title('Payment Recorded')
                    ->body("Recorded payment of ₹" . number_format($data['amount'], 2) . " for Invoice {$invoice->invoice_number}")
                    ->success()
                    ->send();
            });
    }

    public function recordBillPaymentAction(): Action
    {
        return Action::make('recordBillPayment')
            ->label('Record Vendor Bill Payment')
            ->icon('heroicon-o-currency-rupee')
            ->color('primary')
            ->modalHeading('Record Payment against Vendor Bill')
            ->modalWidth(Width::Large)
            ->fillForm(fn (array $arguments) => $arguments)
            ->form([
                Select::make('bill_id')
                    ->label('Vendor Bill')
                    ->options(fn () => Bill::where('balance_due', '>', 0)->pluck('bill_number', 'id'))
                    ->searchable()
                    ->required()
                    ->live()
                    ->afterStateUpdated(function ($set, $state) {
                        if ($state) {
                            $bill = Bill::find($state);
                            if ($bill) {
                                $set('amount', $bill->balance_due);
                            }
                        }
                    }),

                TextInput::make('amount')
                    ->label('Payment Amount (₹)')
                    ->numeric()
                    ->prefix('₹')
                    ->required(),

                Select::make('bank_account_id')
                    ->label('Payment / Bank Account')
                    ->options(fn () => Account::where('type', 'asset')->pluck('name', 'id'))
                    ->required(),

                DatePicker::make('payment_date')
                    ->label('Payment Date')
                    ->default(now())
                    ->required(),

                TextInput::make('reference')
                    ->label('Payment Reference / UTR Number')
                    ->placeholder('e.g. UTR-BILL-1234'),
            ])
            ->action(function (array $data, BillService $billService) {
                $bill = Bill::findOrFail($data['bill_id']);
                $bankAccount = Account::findOrFail($data['bank_account_id']);

                $billService->recordPayment(
                    $bill,
                    $bankAccount,
                    (float) $data['amount'],
                    $data['payment_date'],
                    $data['reference'] ?? null
                );

                Notification::make()
                    ->title('Vendor Payment Recorded')
                    ->body("Recorded payment of ₹" . number_format($data['amount'], 2) . " for Bill {$bill->bill_number}")
                    ->success()
                    ->send();
            });
    }
}
