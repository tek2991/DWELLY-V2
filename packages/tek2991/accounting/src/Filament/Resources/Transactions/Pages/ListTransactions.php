<?php

namespace Tek2991\Accounting\Filament\Resources\Transactions\Pages;

use Filament\Actions;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Tek2991\Accounting\Enums\JournalEntryType;
use Tek2991\Accounting\Enums\TransactionType;
use Tek2991\Accounting\Filament\Resources\Transactions\TransactionResource;
use Tek2991\Accounting\Models\Transaction;
use Tek2991\Accounting\Services\TransactionService;

class ListTransactions extends ListRecords
{
    protected static string $resource = TransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // ── Deposit ────────────────────────────────────────────
            Actions\Action::make('createDeposit')
                ->label('New Deposit')
                ->icon('heroicon-o-arrow-down-circle')
                ->color('success')
                ->modalWidth('lg')
                ->form($this->depositForm())
                ->action(function (array $data) {
                    $service = app(TransactionService::class);
                    // For a Deposit: money flows INTO the bank account
                    // Debit: bank account's chart account | Credit: revenue/other account
                    $bankAccount = \Tek2991\Accounting\Models\BankAccount::find($data['bank_account_id']);

                    if (! $bankAccount) {
                        Notification::make()->danger()->title('Bank account not found')->send();

                        return;
                    }

                    $transaction = $service->createSimpleTransaction(
                        branchId: Filament::getTenant()->id,
                        debitAccountId: $bankAccount->account_id,   // Debit the cash/bank account
                        creditAccountId: $data['account_id'],        // Credit the revenue/equity account
                        amount: (float) $data['amount'],  // Model handles minor unit conversion
                        description: $data['description'],
                        type: TransactionType::Deposit,
                        postedAt: $data['posted_at'],
                        reference: $data['reference'] ?? null,
                    );

                    // Link the bank account to the transaction
                    $transaction->update(['bank_account_id' => $data['bank_account_id']]);

                    Notification::make()->success()->title('Deposit created')->send();
                }),

            // ── Withdrawal ─────────────────────────────────────────
            Actions\Action::make('createWithdrawal')
                ->label('New Withdrawal')
                ->icon('heroicon-o-arrow-up-circle')
                ->color('danger')
                ->modalWidth('lg')
                ->form($this->withdrawalForm())
                ->action(function (array $data) {
                    $service     = app(TransactionService::class);
                    $bankAccount = \Tek2991\Accounting\Models\BankAccount::find($data['bank_account_id']);

                    if (! $bankAccount) {
                        Notification::make()->danger()->title('Bank account not found')->send();

                        return;
                    }

                    $transaction = $service->createSimpleTransaction(
                        branchId: Filament::getTenant()->id,
                        debitAccountId: $data['account_id'],         // Debit the expense/asset account
                        creditAccountId: $bankAccount->account_id,   // Credit the cash/bank account
                        amount: (float) $data['amount'],  // Model handles minor unit conversion
                        description: $data['description'],
                        type: TransactionType::Withdrawal,
                        postedAt: $data['posted_at'],
                        reference: $data['reference'] ?? null,
                    );

                    $transaction->update(['bank_account_id' => $data['bank_account_id']]);

                    Notification::make()->success()->title('Withdrawal created')->send();
                }),

            // ── Journal Entry ──────────────────────────────────────
            Actions\Action::make('createJournal')
                ->label('Journal Entry')
                ->icon('heroicon-o-document-text')
                ->color('gray')
                ->modalWidth('4xl')
                ->form($this->journalForm())
                ->action(function (array $data, Actions\Action $action) {
                    $service = app(TransactionService::class);
                    try {
                        $entries = $data['journalEntries'];
                        unset($data['journalEntries']);

                        $data['branch_id'] = Filament::getTenant()->id;
                        $data['type']       = TransactionType::Journal;

                        $formattedEntries = [];
                        foreach ($entries as $entry) {
                            $formattedEntries[] = [
                                'account_id'  => $entry['account_id'],
                                'type'        => $entry['type'],
                                'amount'      => (float) $entry['amount'],
                                'description' => $entry['description'] ?? null,
                            ];
                        }

                        $service->createTransaction($data, $formattedEntries);
                        Notification::make()->success()->title('Journal entry created')->send();
                    } catch (\InvalidArgumentException $e) {
                        Notification::make()->danger()->title('Validation Error')->body($e->getMessage())->send();
                        $action->halt();
                    }
                }),
        ];
    }

    // ──────────────────────────────────────────────────────────────
    // Form builders
    // ──────────────────────────────────────────────────────────────

    private function depositForm(): array
    {
        return [
            Forms\Components\Select::make('bank_account_id')
                ->label('Deposit To')
                ->helperText('Select the bank account receiving the funds.')
                ->options(fn () => \Tek2991\Accounting\Models\BankAccount::getGroupedSelectOptions())
                ->searchable()
                ->required(),

            Forms\Components\Select::make('account_id')
                ->label('Income Account')
                ->helperText('The revenue or equity account this deposit comes from.')
                ->options(fn () => Transaction::getCategoryAccountOptions(TransactionType::Deposit))
                ->searchable()
                ->required(),

            \Filament\Schemas\Components\Grid::make(2)->schema([
                Forms\Components\TextInput::make('amount')
                    ->label('Amount')
                    ->numeric()
                    ->minValue(0.01)
                    ->step(0.01)
                    ->required()
                    ->prefix(fn () => \Tek2991\Accounting\Facades\Accounting::getCurrency()),

                Forms\Components\DatePicker::make('posted_at')
                    ->label('Date')
                    ->default(now()->toDateString())
                    ->required(),
            ]),

            Forms\Components\TextInput::make('reference')
                ->label('Reference / Invoice #')
                ->nullable(),

            Forms\Components\Textarea::make('description')
                ->label('Description')
                ->required()
                ->rows(2),
        ];
    }

    private function withdrawalForm(): array
    {
        return [
            Forms\Components\Select::make('bank_account_id')
                ->label('Pay From')
                ->helperText('Select the bank account funds are leaving from.')
                ->options(fn () => \Tek2991\Accounting\Models\BankAccount::getGroupedSelectOptions())
                ->searchable()
                ->required(),

            Forms\Components\Select::make('account_id')
                ->label('Expense / Category Account')
                ->helperText('The expense or liability account this payment belongs to.')
                ->options(fn () => Transaction::getCategoryAccountOptions(TransactionType::Withdrawal))
                ->searchable()
                ->required(),

            \Filament\Schemas\Components\Grid::make(2)->schema([
                Forms\Components\TextInput::make('amount')
                    ->label('Amount')
                    ->numeric()
                    ->minValue(0.01)
                    ->step(0.01)
                    ->required()
                    ->prefix(fn () => \Tek2991\Accounting\Facades\Accounting::getCurrency()),

                Forms\Components\DatePicker::make('posted_at')
                    ->label('Date')
                    ->default(now()->toDateString())
                    ->required(),
            ]),

            Forms\Components\TextInput::make('reference')
                ->label('Reference / Bill #')
                ->nullable(),

            Forms\Components\Textarea::make('description')
                ->label('Description')
                ->required()
                ->rows(2),
        ];
    }

    private function journalForm(): array
    {
        return [
            \Filament\Schemas\Components\Grid::make(2)->schema([
                Forms\Components\DatePicker::make('posted_at')
                    ->label('Date')
                    ->default(now()->toDateString())
                    ->required(),

                Forms\Components\TextInput::make('reference')
                    ->label('Reference')
                    ->nullable(),
            ]),

            Forms\Components\Textarea::make('description')
                ->label('Memo / Description')
                ->required()
                ->rows(2),

            Forms\Components\Repeater::make('journalEntries')
                ->label('Journal Lines')
                ->schema([
                    Forms\Components\Select::make('account_id')
                        ->label('Account')
                        ->options(fn () => Transaction::getJournalAccountOptions())
                        ->searchable()
                        ->required()
                        ->columnSpan(2),

                    Forms\Components\Select::make('type')
                        ->label('Type')
                        ->options(JournalEntryType::class)
                        ->required()
                        ->columnSpan(1),

                    Forms\Components\TextInput::make('amount')
                        ->label('Amount')
                        ->numeric()
                        ->minValue(0.01)
                        ->step(0.01)
                        ->required()
                        ->columnSpan(1),

                    Forms\Components\TextInput::make('description')
                        ->label('Line Note')
                        ->nullable()
                        ->columnSpan(2),
                ])
                ->columns(6)
                ->minItems(2)
                ->required()
                ->helperText('Total debits must equal total credits for a valid journal entry.'),
        ];
    }
}
