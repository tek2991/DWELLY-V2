<?php

namespace App\Filament\Pages\Billing;

use App\Domain\Finance\Services\OwnerPayoutService;
use App\Domain\Property\Models\Property;
use BackedEnum;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Livewire\WithPagination;
use Tek2991\Accounting\Models\Account;

class BulkGenerateOwnerPayouts extends Page
{
    use WithPagination;

    protected string $view = 'filament.pages.billing.bulk-generate-owner-payouts';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static \UnitEnum|string|null $navigationGroup = 'Billing & Finance';

    protected static ?string $navigationLabel = 'Bulk Disburse Payouts';

    protected static ?string $title = 'Bulk Disburse Owner Payouts';

    protected static ?int $navigationSort = 2;

    public int $month;

    public int $year;

    public ?int $bankAccountId = null;

    public string $payoutDate = '';

    public string $search = '';

    public string $statusFilter = 'all'; // 'all', 'ready', 'already_processed', 'ineligible'

    public int $perPage = 25;

    public array $selectedProperties = [];

    public ?array $lastExecutionSummary = null;

    public function mount(): void
    {
        $this->month = (int) date('n');
        $this->year = (int) date('Y');
        $this->payoutDate = now()->toDateString();
        $this->bankAccountId = \Tek2991\Accounting\Facades\Accounting::getDefaultBankAccountId();
        $this->refreshSelectedProperties();
    }

    public function getTitle(): string|Htmlable
    {
        return 'Bulk Disburse Owner Payouts';
    }

    public function getSubheading(): ?string
    {
        return 'Review monthly gross rental receipts, automatic 10% management commissions, maintenance deductions, and batch-disburse net payouts to property owners.';
    }

    public static function canAccess(): bool
    {
        return true;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('view_payouts_ledger')
                ->label('Owner Payouts Register')
                ->icon('heroicon-o-banknotes')
                ->color('gray')
                ->url(fn (): string => \App\Filament\Resources\OwnerPayouts\OwnerPayoutResource::getUrl('index')),

            Action::make('bulk_generate_rent')
                ->label('Bulk Generate Rent')
                ->icon('heroicon-o-document-text')
                ->color('gray')
                ->url(fn (): string => \App\Filament\Pages\Billing\BulkGenerateMonthlyRent::getUrl()),
        ];
    }

    public function updatedMonth(): void
    {
        $this->resetPage();
        $this->refreshSelectedProperties();
    }

    public function updatedYear(): void
    {
        $this->resetPage();
        $this->refreshSelectedProperties();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        $this->resetPage();
    }

    public function previousMonth(): void
    {
        $date = Carbon::create($this->year, $this->month, 1)->subMonth();
        $this->month = (int) $date->month;
        $this->year = (int) $date->year;
        $this->resetPage();
        $this->refreshSelectedProperties();
    }

    public function currentMonth(): void
    {
        $this->month = (int) date('n');
        $this->year = (int) date('Y');
        $this->resetPage();
        $this->refreshSelectedProperties();
    }

    public function nextMonth(): void
    {
        $date = Carbon::create($this->year, $this->month, 1)->addMonth();
        $this->month = (int) $date->month;
        $this->year = (int) $date->year;
        $this->resetPage();
        $this->refreshSelectedProperties();
    }

    public function setStatusFilter(string $status): void
    {
        $this->statusFilter = $status;
        $this->resetPage();
    }

    public function refreshSelectedProperties(): void
    {
        $service = app(OwnerPayoutService::class);
        $preview = $service->getBulkPayoutPreview($this->month, $this->year);
        $readyIds = array_values(array_map('strval', array_column(array_filter($preview['items'], fn ($i) => $i['status'] === 'ready'), 'property_id')));
        $this->selectedProperties = $readyIds;
    }

    public function selectAllReady(): void
    {
        $service = app(OwnerPayoutService::class);
        $preview = $service->getBulkPayoutPreview($this->month, $this->year);
        $readyIds = array_values(array_map('strval', array_column(array_filter($preview['items'], fn ($i) => $i['status'] === 'ready'), 'property_id')));
        $this->selectedProperties = $readyIds;
    }

    public function deselectAll(): void
    {
        $this->selectedProperties = [];
    }

    public function toggleProperty(string $propertyId): void
    {
        $id = (string) $propertyId;
        if (in_array($id, $this->selectedProperties, true)) {
            $this->selectedProperties = array_values(array_diff($this->selectedProperties, [$id]));
        } else {
            $this->selectedProperties[] = $id;
        }
    }

    public function getPreviewData(): array
    {
        $service = app(OwnerPayoutService::class);
        return $service->getBulkPayoutPreview($this->month, $this->year);
    }

    public function getFilteredItems(): array
    {
        $preview = $this->getPreviewData();
        $items = $preview['items'];

        if ($this->statusFilter !== 'all') {
            $items = array_filter($items, fn ($i) => $i['status'] === $this->statusFilter);
        }

        if (!empty(trim($this->search))) {
            $term = strtolower(trim($this->search));
            $items = array_filter($items, function ($i) use ($term) {
                return str_contains(strtolower($i['property_name'] ?? ''), $term)
                    || str_contains(strtolower($i['owner_name'] ?? ''), $term)
                    || str_contains(strtolower($i['agreement_code'] ?? ''), $term);
            });
        }

        return array_values($items);
    }

    public function getPaginatedItems(): LengthAwarePaginator
    {
        $items = $this->getFilteredItems();
        $total = count($items);
        $page = $this->getPage();
        $perPage = $this->perPage > 0 ? $this->perPage : 25;
        $offset = ($page - 1) * $perPage;
        $sliced = array_slice($items, $offset, $perPage);

        return new LengthAwarePaginator(
            $sliced,
            $total,
            $perPage,
            $page,
            ['path' => request()->url()]
        );
    }

    public function getSelectedSummary(): array
    {
        $preview = $this->getPreviewData();
        $selectedIds = array_map('strval', $this->selectedProperties);

        $selectedItems = array_filter($preview['items'], function ($item) use ($selectedIds) {
            return $item['status'] === 'ready' && in_array((string) $item['property_id'], $selectedIds, true);
        });

        $count = count($selectedItems);
        $totalGross = array_sum(array_column($selectedItems, 'gross_rent'));
        $totalFee = array_sum(array_column($selectedItems, 'management_fee'));
        $totalAdvance = array_sum(array_column($selectedItems, 'advance_offset'));
        $totalNet = array_sum(array_column($selectedItems, 'net_payout'));

        return [
            'count' => $count,
            'total_gross_rent' => $totalGross,
            'total_management_fee' => $totalFee,
            'total_advance_offset' => $totalAdvance,
            'total_net_payout' => $totalNet,
            'items' => array_values($selectedItems),
        ];
    }

    public function getBankAccountsProperty(): Collection
    {
        return Account::where('type', \Tek2991\Accounting\Enums\AccountType::Asset)
            ->where(function ($q) {
                $q->whereIn('system_role', [
                    \Tek2991\Accounting\Enums\SystemRole::Bank,
                    \Tek2991\Accounting\Enums\SystemRole::Cash,
                ])
                ->orWhere('code', 'like', '11%')
                ->orWhere('name', 'like', '%Current Account%')
                ->orWhere('name', 'like', '%Savings Account%')
                ->orWhere('name', 'like', '%Bank%')
                ->orWhere('name', 'like', '%Cash%');
            })
            ->where('is_control_account', false)
            ->get();
    }

    /**
     * Execute bulk payout disbursement for selected properties
     */
    public function disburseSelected(): void
    {
        $selectedSummary = $this->getSelectedSummary();
        if ($selectedSummary['count'] === 0) {
            Notification::make()
                ->title('No Properties Selected')
                ->body('Please select at least one ready property to disburse owner payouts.')
                ->warning()
                ->send();
            return;
        }

        $service = app(OwnerPayoutService::class);
        $options = [
            'bank_account_id' => $this->bankAccountId,
            'payout_date' => $this->payoutDate ?: now()->toDateString(),
        ];

        try {
            $summary = $service->bulkProcessOwnerPayoutsWithSummary(
                $this->month,
                $this->year,
                $this->selectedProperties,
                auth()->user(),
                $options
            );

            $this->lastExecutionSummary = $summary;
            $this->refreshSelectedProperties();

            $monthName = date('F Y', mktime(0, 0, 0, $this->month, 1, $this->year));

            if ($summary['count'] > 0) {
                Notification::make()
                    ->title('Bulk Owner Payouts Disbursed')
                    ->body("Successfully disbursed {$summary['count']} owner payouts totaling ₹" . number_format($summary['total_amount'], 2) . " for {$monthName}.")
                    ->success()
                    ->send();
            } else {
                Notification::make()
                    ->title('No Payouts Processed')
                    ->body('No eligible owner payouts were processed. Check property status.')
                    ->warning()
                    ->send();
            }
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Disbursement Failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function dismissExecutionSummary(): void
    {
        $this->lastExecutionSummary = null;
    }
}
