<?php

namespace Tek2991\Accounting\Filament\Pages\Reports;

use Tek2991\Accounting\Filament\Pages\Reports\Concerns\HasReportExports;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Schema;
use Filament\Pages\Page;
use Tek2991\Accounting\Models\Account;
use Tek2991\Accounting\Services\AccountService;
use Tek2991\Accounting\ValueObjects\Money;
use Carbon\Carbon;
use Tek2991\Accounting\Enums\JournalEntryType;

class TrialBalance extends Page implements HasForms
{
    use InteractsWithForms;
    use HasReportExports;

    protected static \UnitEnum|string|null $navigationGroup = 'Reports';

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-scale';

    protected string $view = 'accounting::filament.pages.reports.trial-balance';

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
        // Trial balance includes opening balances for real accounts, and period balances for nominal accounts.
        // Actually, a simple trial balance as of a date includes ALL transactions up to that date.
        $startDate = '1970-01-01'; 

        $service = app(AccountService::class);
        $accounts = Account::active()->orderBy('code')->get();

        $rows = [];
        $totalDebitAmount = 0;
        $totalCreditAmount = 0;
        $currency = \Tek2991\Accounting\Facades\Accounting::getCurrency();

        foreach ($accounts as $account) {
            $balance = $service->getEndingBalance($account, $startDate, $endDate);
            $amount = $balance->getAmount();

            if ($amount === 0) {
                continue;
            }

            $isDebitNormal = $account->type->getDefaultBalanceType() === JournalEntryType::Debit;
            
            // If the balance is positive, it's on the normal side.
            // If negative, it's on the opposite side.
            $debit = 0;
            $credit = 0;

            if ($isDebitNormal) {
                if ($amount >= 0) {
                    $debit = $amount;
                } else {
                    $credit = abs($amount);
                }
            } else {
                if ($amount >= 0) {
                    $credit = $amount;
                } else {
                    $debit = abs($amount);
                }
            }

            $totalDebitAmount += $debit;
            $totalCreditAmount += $credit;

            $rows[] = [
                'account' => $account,
                'debit' => new Money($debit, $currency),
                'credit' => new Money($credit, $currency),
            ];
        }

        return [
            'title' => 'Trial Balance',
            'rows' => $rows,
            'totalDebit' => new Money($totalDebitAmount, $currency),
            'totalCredit' => new Money($totalCreditAmount, $currency),
            'endDate' => Carbon::parse($endDate)->format('F j, Y'),
        ];
    }

    public function generateCsvRows($handle, $data): void
    {
        fputcsv($handle, ['Account Code', 'Account Name', 'Debit', 'Credit']);
        
        foreach ($data['rows'] as $row) {
            fputcsv($handle, [
                $row['account']->code ?? '',
                $row['account']->name,
                $row['debit']->getAmount() > 0 ? $row['debit']->getDecimal() : '',
                $row['credit']->getAmount() > 0 ? $row['credit']->getDecimal() : ''
            ]);
        }
        fputcsv($handle, []);
        fputcsv($handle, ['Totals', '', $data['totalDebit']->getDecimal(), $data['totalCredit']->getDecimal()]);
    }
}
