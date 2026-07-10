<?php

namespace Tek2991\Accounting\Filament\Pages\Reports;

use Tek2991\Accounting\Filament\Pages\Reports\Concerns\HasReportExports;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Schema;
use Filament\Pages\Page;
use Tek2991\Accounting\Enums\AccountType;
use Tek2991\Accounting\Services\AccountService;
use Carbon\Carbon;

class BalanceSheet extends Page implements HasForms
{
    use InteractsWithForms;
    use HasReportExports;

    protected static \UnitEnum|string|null $navigationGroup = 'Reports';

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-document-text';

    protected string $view = 'accounting::filament.pages.reports.balance-sheet';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'end_date' => Carbon::now()->format('Y-m-d'),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                DatePicker::make('end_date')
                    ->label('As of Date')
                    ->required()
                    ->default(now())
                    ->live()
            ])
            ->statePath('data');
    }

    public function getReportDataProperty(): array
    {
        $endDate = $this->data['end_date'] ?? Carbon::now()->format('Y-m-d');
        $lastClosedPeriod = \Tek2991\Accounting\Models\FiscalPeriod::query()
            ->where('status', '!=', \Tek2991\Accounting\Enums\FiscalPeriodStatus::Open)
            ->where('end_date', '<', $endDate)
            ->orderBy('end_date', 'desc')
            ->first();

        $currentFyStart = $lastClosedPeriod ? $lastClosedPeriod->end_date->copy()->addDay()->format('Y-m-d') : '1970-01-01';

        $service = app(AccountService::class);

        $assets = collect($service->getTypeBalances(AccountType::Asset, '1970-01-01', $endDate));
        $liabilities = collect($service->getTypeBalances(AccountType::Liability, '1970-01-01', $endDate));
        $equity = collect($service->getTypeBalances(AccountType::Equity, '1970-01-01', $endDate));

        $totalAssets = $service->getTypeTotal(AccountType::Asset, '1970-01-01', $endDate);
        $totalLiabilities = $service->getTypeTotal(AccountType::Liability, '1970-01-01', $endDate);
        $totalEquityBase = $service->getTypeTotal(AccountType::Equity, '1970-01-01', $endDate);

        $retainedEarningsSnapshots = \Tek2991\Accounting\Models\FiscalPeriod::query()
            ->where('status', '!=', \Tek2991\Accounting\Enums\FiscalPeriodStatus::Open)
            ->where('end_date', '<', $endDate)
            ->sum('closing_profit_loss');

        $revenueTotal = $service->getTypeTotal(AccountType::Revenue, $currentFyStart, $endDate);
        $expenseTotal = $service->getTypeTotal(AccountType::Expense, $currentFyStart, $endDate);
        $currentProfitAmount = $revenueTotal->getAmount() - $expenseTotal->getAmount();
        
        $currentYearProfit = new \Tek2991\Accounting\ValueObjects\Money($currentProfitAmount, \Tek2991\Accounting\Facades\Accounting::getCurrency());
        $historicalRetained = new \Tek2991\Accounting\ValueObjects\Money($retainedEarningsSnapshots, \Tek2991\Accounting\Facades\Accounting::getCurrency());

        $totalLiabilitiesAndEquityAmount = $totalLiabilities->getAmount() + $totalEquityBase->getAmount() + $retainedEarningsSnapshots + $currentProfitAmount;
        $totalLiabilitiesAndEquity = new \Tek2991\Accounting\ValueObjects\Money($totalLiabilitiesAndEquityAmount, \Tek2991\Accounting\Facades\Accounting::getCurrency());

        $totalEquityAmount = $totalEquityBase->getAmount() + $retainedEarningsSnapshots + $currentProfitAmount;
        $totalEquity = new \Tek2991\Accounting\ValueObjects\Money($totalEquityAmount, \Tek2991\Accounting\Facades\Accounting::getCurrency());

        return [
            'title' => 'Balance Sheet',
            'assets' => $this->groupBalancesByClass($assets),
            'liabilities' => $this->groupBalancesByClass($liabilities),
            'equity' => $this->groupBalancesByClass($equity),
            'historicalRetained' => $historicalRetained,
            'currentYearProfit' => $currentYearProfit,
            'totalAssets' => $totalAssets,
            'totalLiabilities' => $totalLiabilities,
            'totalEquity' => $totalEquity,
            'totalLiabilitiesAndEquity' => $totalLiabilitiesAndEquity,
            'endDate' => Carbon::parse($endDate)->format('F j, Y'),
        ];
    }

    public function generateCsvRows($handle, $data): void
    {
        fputcsv($handle, ['Category', 'Account Code', 'Account Name', 'Balance']);
        
        fputcsv($handle, ['ASSETS', '', '', '']);
        foreach ($data['assets'] as $class => $items) {
            fputcsv($handle, [$class, '', '', '']);
            foreach ($items as $row) {
                fputcsv($handle, ['', $row['account']->code ?? '', $row['account']->name, $row['balance']->getDecimal()]);
            }
        }
        fputcsv($handle, ['Total Assets', '', '', $data['totalAssets']->getDecimal()]);
        fputcsv($handle, []);

        fputcsv($handle, ['LIABILITIES', '', '', '']);
        foreach ($data['liabilities'] as $class => $items) {
            fputcsv($handle, [$class, '', '', '']);
            foreach ($items as $row) {
                fputcsv($handle, ['', $row['account']->code ?? '', $row['account']->name, $row['balance']->getDecimal()]);
            }
        }
        fputcsv($handle, ['Total Liabilities', '', '', $data['totalLiabilities']->getDecimal()]);
        fputcsv($handle, []);

        fputcsv($handle, ['EQUITY', '', '', '']);
        foreach ($data['equity'] as $class => $items) {
            fputcsv($handle, [$class, '', '', '']);
            foreach ($items as $row) {
                fputcsv($handle, ['', $row['account']->code ?? '', $row['account']->name, $row['balance']->getDecimal()]);
            }
        }
        fputcsv($handle, ['Historical Retained Earnings (from closed periods)', '', '', $data['historicalRetained']->getDecimal()]);
        fputcsv($handle, ['Current Year Profit/Loss', '', '', $data['currentYearProfit']->getDecimal()]);
        fputcsv($handle, ['Total Equity', '', '', $data['totalEquity']->getDecimal()]);
        fputcsv($handle, []);
        
        fputcsv($handle, ['Total Liabilities & Equity', '', '', $data['totalLiabilitiesAndEquity']->getDecimal()]);
    }

    protected function groupBalancesByClass(\Illuminate\Support\Collection $balances): \Illuminate\Support\Collection
    {
        // Filter out zero balances for cleaner reports
        return $balances->filter(function ($item) {
            return $item['balance']->getAmount() !== 0;
        })->groupBy(function ($item) {
            return $item['account']->reporting_class?->getLabel() ?? 'Unclassified';
        });
    }
}
