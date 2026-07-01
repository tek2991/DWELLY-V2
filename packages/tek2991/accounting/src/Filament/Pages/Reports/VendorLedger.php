<?php

namespace Tek2991\Accounting\Filament\Pages\Reports;

use Tek2991\Accounting\Filament\Pages\Reports\Concerns\HasReportExports;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Schema;
use Filament\Pages\Page;
use Tek2991\Accounting\Models\Contact;
use Tek2991\Accounting\Models\JournalEntry;
use Tek2991\Accounting\Services\AccountService;
use Carbon\Carbon;
use Tek2991\Accounting\Enums\JournalEntryType;
use Tek2991\Accounting\Enums\ContactType;
use Tek2991\Accounting\ValueObjects\Money;

class VendorLedger extends Page implements HasForms
{
    use InteractsWithForms;
    use HasReportExports;

    protected static \UnitEnum|string|null $navigationGroup = 'Reports';

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-truck';

    protected string $view = 'accounting::filament.pages.reports.ledger';

    protected static ?string $navigationLabel = 'Vendor Ledger';
    
    protected ?string $heading = 'Vendor Ledger';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'start_date' => Carbon::now()->startOfMonth()->format('Y-m-d'),
            'end_date' => Carbon::now()->endOfMonth()->format('Y-m-d'),
            'vendor_id' => null,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(3)->schema([
                    Select::make('vendor_id')
                        ->label('Vendor')
                        ->options(Contact::whereIn('type', [ContactType::Vendor, ContactType::Both])->pluck('name', 'id'))
                        ->searchable()
                        ->required()
                        ->live(),
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
        $vendorId = $this->data['vendor_id'] ?? null;
        if (!$vendorId) {
            return ['showReport' => false];
        }

        $startDate = $this->data['start_date'] ?? Carbon::now()->startOfMonth()->format('Y-m-d');
        $endDate = $this->data['end_date'] ?? Carbon::now()->endOfMonth()->format('Y-m-d');
        
        $vendor = Contact::find($vendorId);
        if (!$vendor) {
            return ['showReport' => false];
        }

        $account = $vendor->payableAccount;
        if (!$account) {
            return [
                'showReport' => true,
                'title' => $vendor->name . ' Ledger',
                'subtitle' => 'No Payable Account mapped to this vendor.',
                'startDate' => Carbon::parse($startDate)->format('F j, Y'),
                'endDate' => Carbon::parse($endDate)->format('F j, Y'),
                'rows' => [],
            ];
        }

        $service = app(AccountService::class);
        $currency = \Tek2991\Accounting\Facades\Accounting::getCurrency();
        
        // Opening balance
        $openingBalanceMoney = $service->getStartingBalance($account, $startDate, true) ?? Money::zero($currency);
        $runningBalance = $openingBalanceMoney->getAmount();

        $rows = [];
        
        // Opening balance row
        $rows[] = [
            'is_summary' => true,
            'description' => 'Opening Balance',
            'balance' => $openingBalanceMoney,
        ];

        // Fetch transactions
        $entries = JournalEntry::with(['transaction', 'transaction.voucherable'])
            ->where(config('accounting.table_prefix', 'acc_') . 'journal_entries.account_id', $account->id)
            ->whereHas('transaction', function ($q) use ($startDate, $endDate) {
                $q->whereNotNull('posted_at')
                  ->whereBetween('posted_at', [$startDate, $endDate]);
            })
            ->join(config('accounting.table_prefix', 'acc_') . 'transactions', 'transaction_id', '=', config('accounting.table_prefix', 'acc_') . 'transactions.id')
            ->orderBy(config('accounting.table_prefix', 'acc_') . 'transactions.posted_at')
            ->orderBy('transaction_id')
            ->select(config('accounting.table_prefix', 'acc_') . 'journal_entries.*')
            ->get();

        $isDebitNormal = $account->type->getDefaultBalanceType() === JournalEntryType::Debit;

        foreach ($entries as $entry) {
            $isDebit = $entry->isDebit();
            $amount = $entry->amount * 100; // raw cents
            
            // Adjust running balance
            if ($isDebitNormal) {
                $runningBalance += $isDebit ? $amount : -$amount;
            } else {
                $runningBalance += $isDebit ? -$amount : $amount;
            }

            $debitMoney = $isDebit ? new Money($amount, $currency) : null;
            $creditMoney = !$isDebit ? new Money($amount, $currency) : null;

            $ref = $entry->transaction->reference ?? $entry->transaction->id;
            if ($entry->transaction->voucherable) {
                if (method_exists($entry->transaction->voucherable, 'getDocumentNumber')) {
                    $ref = $entry->transaction->voucherable->getDocumentNumber();
                } else {
                    $ref = class_basename($entry->transaction->voucherable) . ' #' . $entry->transaction->voucherable->id;
                }
            }

            $rows[] = [
                'is_summary' => false,
                'date' => Carbon::parse($entry->transaction->posted_at)->format('Y-m-d'),
                'ref' => $ref,
                'description' => $entry->description ?: $entry->transaction->description,
                'debit' => $debitMoney,
                'credit' => $creditMoney,
                'balance' => new Money($runningBalance, $currency),
            ];
        }

        // Closing balance row
        $rows[] = [
            'is_summary' => true,
            'description' => 'Closing Balance',
            'balance' => new Money($runningBalance, $currency),
        ];

        return [
            'showReport' => true,
            'title' => $vendor->name . ' Ledger',
            'subtitle' => 'Vendor Ledger (Accounts Payable)',
            'startDate' => Carbon::parse($startDate)->format('F j, Y'),
            'endDate' => Carbon::parse($endDate)->format('F j, Y'),
            'rows' => $rows,
        ];
    }

    public function generateCsvRows($handle, $data): void
    {
        fputcsv($handle, ['Date', 'Ref', 'Description', 'Debit', 'Credit', 'Balance']);
        
        foreach ($data['rows'] as $row) {
            if ($row['is_summary'] ?? false) {
                fputcsv($handle, ['', '', $row['description'], isset($row['debit']) ? $row['debit']->getDecimal() : '', isset($row['credit']) ? $row['credit']->getDecimal() : '', $row['balance']->getDecimal()]);
            } else {
                fputcsv($handle, [$row['date'], $row['ref'], $row['description'], isset($row['debit']) ? $row['debit']->getDecimal() : '', isset($row['credit']) ? $row['credit']->getDecimal() : '', $row['balance']->getDecimal()]);
            }
        }
    }
}
