<?php

namespace App\Filament\Pages\Billing;

use App\Domain\Agreement\Models\TenancyAgreement;
use App\Domain\Finance\Services\RentBillingService;
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

class BulkGenerateMonthlyRent extends Page
{
    use WithPagination;

    protected string $view = 'filament.pages.billing.bulk-generate-monthly-rent';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static \UnitEnum|string|null $navigationGroup = 'Billing & Finance';

    protected static ?string $navigationLabel = 'Bulk Generate Rent';

    protected static ?int $navigationSort = 1;

    public int $month;

    public int $year;

    public ?string $propertyId = null;

    public string $search = '';

    public string $statusFilter = 'all'; // 'all', 'ready', 'already_generated', 'ineligible'

    public int $perPage = 25;

    public array $selectedAgreements = [];

    public string $issueDate = '';

    public string $dueDate = '';

    public bool $autoPostToLedger = true;

    public ?array $lastGenerationSummary = null;

    public function mount(): void
    {
        $this->month = (int) date('n');
        $this->year = (int) date('Y');
        $this->issueDate = now()->toDateString();
        $this->dueDate = Carbon::create($this->year, $this->month, 5)->toDateString();
        $this->refreshSelectedAgreements();
    }

    public function getTitle(): string|Htmlable
    {
        return 'Bulk Generate Monthly Rent';
    }

    public function getSubheading(): ?string
    {
        return 'Review active tenancies, calculate prorations, preview rent demands, and batch-generate monthly rent notices & ledger charges.';
    }

    public static function canAccess(): bool
    {
        return true;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('view_rent_invoices')
                ->label('Rent Demands & Collections')
                ->icon('heroicon-o-banknotes')
                ->color('gray')
                ->url(fn (): string => \App\Filament\Resources\Billing\RentDemandsResource::getUrl('index')),

            Action::make('operations_hub')
                ->label('Financial Operations Hub')
                ->icon('heroicon-o-scale')
                ->color('gray')
                ->url(fn (): string => \App\Filament\Pages\Billing\FinancialOperationsHub::getUrl()),
        ];
    }

    public function updatedMonth(): void
    {
        $this->dueDate = Carbon::create($this->year, $this->month, 5)->toDateString();
        $this->resetPage();
        $this->refreshSelectedAgreements();
    }

    public function updatedYear(): void
    {
        $this->dueDate = Carbon::create($this->year, $this->month, 5)->toDateString();
        $this->resetPage();
        $this->refreshSelectedAgreements();
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

    public function updatedPropertyId(): void
    {
        $this->resetPage();
        $this->refreshSelectedAgreements();
    }

    public function previousMonth(): void
    {
        $date = Carbon::create($this->year, $this->month, 1)->subMonth();
        $this->month = (int) $date->month;
        $this->year = (int) $date->year;
        $this->dueDate = Carbon::create($this->year, $this->month, 5)->toDateString();
        $this->resetPage();
        $this->refreshSelectedAgreements();
    }

    public function currentMonth(): void
    {
        $this->month = (int) date('n');
        $this->year = (int) date('Y');
        $this->dueDate = Carbon::create($this->year, $this->month, 5)->toDateString();
        $this->resetPage();
        $this->refreshSelectedAgreements();
    }

    public function nextMonth(): void
    {
        $date = Carbon::create($this->year, $this->month, 1)->addMonth();
        $this->month = (int) $date->month;
        $this->year = (int) $date->year;
        $this->dueDate = Carbon::create($this->year, $this->month, 5)->toDateString();
        $this->resetPage();
        $this->refreshSelectedAgreements();
    }

    public function setStatusFilter(string $status): void
    {
        $this->statusFilter = $status;
        $this->resetPage();
    }

    public function refreshSelectedAgreements(): void
    {
        $service = app(RentBillingService::class);
        $preview = $service->getBulkGenerationPreview($this->month, $this->year, $this->propertyId);
        $readyIds = array_values(array_map('strval', array_column(array_filter($preview['items'], fn ($i) => $i['status'] === 'ready'), 'agreement_id')));
        $this->selectedAgreements = $readyIds;
    }

    public function selectAllReady(): void
    {
        $service = app(RentBillingService::class);
        $preview = $service->getBulkGenerationPreview($this->month, $this->year, $this->propertyId);
        $readyIds = array_values(array_map('strval', array_column(array_filter($preview['items'], fn ($i) => $i['status'] === 'ready'), 'agreement_id')));
        $this->selectedAgreements = $readyIds;
    }

    public function deselectAll(): void
    {
        $this->selectedAgreements = [];
    }

    public function toggleAgreement(string $agreementId): void
    {
        $id = (string) $agreementId;
        if (in_array($id, $this->selectedAgreements, true)) {
            $this->selectedAgreements = array_values(array_diff($this->selectedAgreements, [$id]));
        } else {
            $this->selectedAgreements[] = $id;
        }
    }

    public function getPreviewData(): array
    {
        $service = app(RentBillingService::class);
        return $service->getBulkGenerationPreview($this->month, $this->year, $this->propertyId);
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
                return str_contains(strtolower($i['agreement_code'] ?? ''), $term)
                    || str_contains(strtolower($i['tenant_name'] ?? ''), $term)
                    || str_contains(strtolower($i['property_name'] ?? ''), $term)
                    || str_contains(strtolower($i['property_code'] ?? ''), $term);
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
        $selectedIds = array_map('strval', $this->selectedAgreements);

        $selectedItems = array_filter($preview['items'], function ($item) use ($selectedIds) {
            return $item['status'] === 'ready' && in_array((string) $item['agreement_id'], $selectedIds, true);
        });

        $count = count($selectedItems);
        $totalBaseRent = array_sum(array_column($selectedItems, 'rent_amount'));
        $totalMaintenance = array_sum(array_column($selectedItems, 'maintenance_amount'));
        $totalAmount = array_sum(array_column($selectedItems, 'total_amount'));

        return [
            'count' => $count,
            'total_base_rent' => $totalBaseRent,
            'total_maintenance_amount' => $totalMaintenance,
            'total_amount' => $totalAmount,
            'items' => array_values($selectedItems),
        ];
    }

    public function getPropertiesProperty(): Collection
    {
        return Property::orderBy('building_name')->get();
    }

    /**
     * Generate single rent invoice
     */
    public function generateSingle(string $agreementId): void
    {
        $agreement = TenancyAgreement::find($agreementId);
        if (!$agreement) {
            Notification::make()
                ->title('Agreement not found')
                ->danger()
                ->send();
            return;
        }

        $service = app(RentBillingService::class);
        try {
            $invoice = $service->generateRentDemand($agreement, $this->month, $this->year, [
                'issue_date' => $this->issueDate ?: now()->toDateString(),
                'due_date' => $this->dueDate ?: null,
            ]);

            $this->refreshSelectedAgreements();

            Notification::make()
                ->title('Rent Demand Generated')
                ->body("Created demand #{$invoice->invoice_number} for agreement {$agreement->code}")
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Generation Failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * Bulk generate rent demands for selected agreements
     */
    public function generateSelected(): void
    {
        $selectedSummary = $this->getSelectedSummary();
        if ($selectedSummary['count'] === 0) {
            Notification::make()
                ->title('No Demands Selected')
                ->body('Please select at least one ready tenancy agreement to generate rent demands.')
                ->warning()
                ->send();
            return;
        }

        $service = app(RentBillingService::class);
        $options = [
            'issue_date' => $this->issueDate ?: now()->toDateString(),
            'due_date' => $this->dueDate ?: null,
        ];

        $summary = $service->bulkGenerateRentDemandsWithSummary(
            $this->month,
            $this->year,
            $this->selectedAgreements,
            $this->propertyId,
            $options
        );

        $this->lastGenerationSummary = $summary;
        $this->refreshSelectedAgreements();

        $monthName = date('F Y', mktime(0, 0, 0, $this->month, 1, $this->year));

        if ($summary['count'] > 0) {
            Notification::make()
                ->title('Bulk Generation Completed')
                ->body("Successfully generated {$summary['count']} rent demands totaling ₹" . number_format($summary['total_amount'], 2) . " for {$monthName}.")
                ->success()
                ->send();
        } else {
            Notification::make()
                ->title('No Demands Generated')
                ->body('No eligible rent demands were created. Check the preview status.')
                ->warning()
                ->send();
        }
    }

    public function dismissGenerationSummary(): void
    {
        $this->lastGenerationSummary = null;
    }
}
