<?php

namespace Tek2991\Accounting\Filament\Pages\Reports;

use Tek2991\Accounting\Filament\Pages\Reports\Concerns\HasReportExports;

use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Schema;
use Filament\Pages\Page;
use Tek2991\Accounting\Enums\AccountType;
use Tek2991\Accounting\Services\AccountService;
use Carbon\Carbon;

class ProfitAndLoss extends Page implements HasForms
{
    use InteractsWithForms;
    use HasReportExports;

    protected static \UnitEnum|string|null $navigationGroup = 'Reports';

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-chart-bar';

    protected string $view = 'accounting::filament.pages.reports.profit-and-loss';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'start_date' => Carbon::now()->startOfMonth()->format('Y-m-d'),
            'end_date' => Carbon::now()->endOfMonth()->format('Y-m-d'),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(2)->schema([
                    DatePicker::make('start_date')
                        ->label('Start Date')
                        ->required()
                        ->live(),
                    DatePicker::make('end_date')
                        ->label('End Date')
                        ->required()
                        ->live(),
                ]),
            ])
            ->statePath('data');
    }

    public function getReportDataProperty(): array
    {
        $startDate = $this->data['start_date'] ?? Carbon::now()->startOfMonth()->format('Y-m-d');
        $endDate = $this->data['end_date'] ?? Carbon::now()->endOfMonth()->format('Y-m-d');

        $service = app(AccountService::class);

        $revenue = collect($service->getTypeBalances(AccountType::Revenue, $startDate, $endDate));
        $expenses = collect($service->getTypeBalances(AccountType::Expense, $startDate, $endDate));

        $totalRevenue = $service->getTypeTotal(AccountType::Revenue, $startDate, $endDate);
        $totalExpenses = $service->getTypeTotal(AccountType::Expense, $startDate, $endDate);

        $netIncomeAmount = $totalRevenue->getAmount() - $totalExpenses->getAmount();
        $netIncome = new \Tek2991\Accounting\ValueObjects\Money($netIncomeAmount, \Tek2991\Accounting\Facades\Accounting::getCurrency());

        return [
            'title' => 'Profit & Loss',
            'revenue' => $this->groupBalancesByClass($revenue),
            'expenses' => $this->groupBalancesByClass($expenses),
            'totalRevenue' => $totalRevenue,
            'totalExpenses' => $totalExpenses,
            'netIncome' => $netIncome,
            'startDate' => Carbon::parse($startDate)->format('F j, Y'),
            'endDate' => Carbon::parse($endDate)->format('F j, Y'),
        ];
    }

    public function generateCsvRows($handle, $data): void
    {
        fputcsv($handle, ['Category', 'Account Code', 'Account Name', 'Balance']);
        
        fputcsv($handle, ['REVENUE', '', '', '']);
        foreach ($data['revenue'] as $class => $items) {
            fputcsv($handle, [$class, '', '', '']);
            foreach ($items as $row) {
                fputcsv($handle, ['', $row['account']->code ?? '', $row['account']->name, $row['balance']->getDecimal()]);
            }
        }
        fputcsv($handle, ['Total Revenue', '', '', $data['totalRevenue']->getDecimal()]);
        fputcsv($handle, []);

        fputcsv($handle, ['EXPENSES', '', '', '']);
        foreach ($data['expenses'] as $class => $items) {
            fputcsv($handle, [$class, '', '', '']);
            foreach ($items as $row) {
                fputcsv($handle, ['', $row['account']->code ?? '', $row['account']->name, $row['balance']->getDecimal()]);
            }
        }
        fputcsv($handle, ['Total Expenses', '', '', $data['totalExpenses']->getDecimal()]);
        fputcsv($handle, []);

        fputcsv($handle, ['NET INCOME', '', '', $data['netIncome']->getDecimal()]);
    }

    protected function groupBalancesByClass(\Illuminate\Support\Collection $balances): \Illuminate\Support\Collection
    {
        return $balances->filter(function ($item) {
            return $item['balance']->getAmount() !== 0;
        })->groupBy(function ($item) {
            return $item['account']->reporting_class->getLabel();
        });
    }
}
