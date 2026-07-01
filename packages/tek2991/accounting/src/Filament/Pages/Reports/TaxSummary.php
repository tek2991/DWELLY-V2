<?php

namespace Tek2991\Accounting\Filament\Pages\Reports;

use Tek2991\Accounting\Filament\Pages\Reports\Concerns\HasReportExports;

use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Schema;
use Filament\Pages\Page;
use Tek2991\Accounting\Models\Tax;
use Tek2991\Accounting\Services\AccountService;
use Carbon\Carbon;
use Tek2991\Accounting\ValueObjects\Money;

class TaxSummary extends Page implements HasForms
{
    use InteractsWithForms;
    use HasReportExports;

    protected static \UnitEnum|string|null $navigationGroup = 'Reports';

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-receipt-percent';

    protected string $view = 'accounting::filament.pages.reports.tax-summary';

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
        $taxes = Tax::with('components.salesAccount', 'components.purchaseAccount')->get();

        $rows = [];
        $totalOutputAmount = 0;
        $totalInputAmount = 0;
        $totalPayableAmount = 0;
        $currency = \Tek2991\Accounting\Facades\Accounting::getCurrency();

        foreach ($taxes as $tax) {
            $taxOutput = 0;
            $taxInput = 0;

            $components = [];

            foreach ($tax->components as $component) {
                $componentOutput = 0;
                $componentInput = 0;

                if ($component->sales_account_id) {
                    $balances = $service->getAccountBalances($startDate, $endDate, [$component->sales_account_id]);
                    if ($balances->isNotEmpty()) {
                        $bal = $balances->first();
                        // Output tax is typically a credit to liability
                        $componentOutput = ($bal->total_credit ?? 0) - ($bal->total_debit ?? 0);
                    }
                }

                if ($component->purchase_account_id) {
                    $balances = $service->getAccountBalances($startDate, $endDate, [$component->purchase_account_id]);
                    if ($balances->isNotEmpty()) {
                        $bal = $balances->first();
                        // Input tax is typically a debit to asset or liability
                        $componentInput = ($bal->total_debit ?? 0) - ($bal->total_credit ?? 0);
                    }
                }

                $taxOutput += $componentOutput;
                $taxInput += $componentInput;

                $components[] = [
                    'name' => $component->name,
                    'output' => new Money($componentOutput, $currency),
                    'input' => new Money($componentInput, $currency),
                    'payable' => new Money($componentOutput - $componentInput, $currency),
                ];
            }

            $totalOutputAmount += $taxOutput;
            $totalInputAmount += $taxInput;
            $totalPayableAmount += ($taxOutput - $taxInput);

            $rows[] = [
                'tax' => $tax,
                'components' => $components,
                'output' => new Money($taxOutput, $currency),
                'input' => new Money($taxInput, $currency),
                'payable' => new Money($taxOutput - $taxInput, $currency),
            ];
        }

        return [
            'title' => 'Tax Summary',
            'rows' => $rows,
            'totalOutput' => new Money($totalOutputAmount, $currency),
            'totalInput' => new Money($totalInputAmount, $currency),
            'totalPayable' => new Money($totalPayableAmount, $currency),
            'startDate' => Carbon::parse($startDate)->format('F j, Y'),
            'endDate' => Carbon::parse($endDate)->format('F j, Y'),
        ];
    }

    public function generateCsvRows($handle, $data): void
    {
        fputcsv($handle, ['Tax Group / Component', 'Output Tax (Collected)', 'Input Tax (Paid)', 'Net Payable']);
        
        foreach ($data['rows'] as $row) {
            fputcsv($handle, [$row['tax']->name, $row['output']->getDecimal(), $row['input']->getDecimal(), $row['payable']->getDecimal()]);
            
            foreach ($row['components'] as $component) {
                fputcsv($handle, ['  - ' . $component['name'], $component['output']->getDecimal(), $component['input']->getDecimal(), $component['payable']->getDecimal()]);
            }
        }
        fputcsv($handle, []);
        fputcsv($handle, ['Total Tax Payable', $data['totalOutput']->getDecimal(), $data['totalInput']->getDecimal(), $data['totalPayable']->getDecimal()]);
    }
}
