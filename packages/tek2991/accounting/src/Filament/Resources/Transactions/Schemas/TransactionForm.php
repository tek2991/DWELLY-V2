<?php

namespace Tek2991\Accounting\Filament\Resources\Transactions\Schemas;

use Filament\Forms;
use Tek2991\Accounting\Enums\JournalEntryType;
use Tek2991\Accounting\Enums\TransactionType;
use Tek2991\Accounting\Models\Account;
use Tek2991\Accounting\Models\Transaction;

class TransactionForm
{
    public static function getDynamicFormSchema(Transaction $record): array
    {
        if ($record->type === TransactionType::Journal) {
            return [
                Forms\Components\DatePicker::make('posted_at')->required(),
                Forms\Components\TextInput::make('reference'),
                Forms\Components\Textarea::make('description')->required(),
                Forms\Components\Repeater::make('journalEntries')
                    ->schema([
                        Forms\Components\Select::make('account_id')
                            ->options(fn() => Account::postable()->pluck('name', 'id'))
                            ->searchable()
                            ->required()
                            ->columnSpan(2),
                        Forms\Components\Select::make('type')
                            ->options(JournalEntryType::class)
                            ->required()
                            ->columnSpan(1),
                        Forms\Components\TextInput::make('amount')
                            ->numeric()
                            ->required()
                            ->columnSpan(1),
                        Forms\Components\TextInput::make('description')
                            ->nullable()
                            ->columnSpan(2),
                    ])
                    ->columns(6)
                    ->minItems(2)
                    ->required()
            ];
        }

        return [
            Forms\Components\Select::make('bank_account_id')
                ->label(fn (Transaction $record) => $record->type === TransactionType::Deposit ? 'Deposit To (Asset/Bank)' : 'Withdraw From (Asset/Bank)')
                ->options(fn() => Account::pluck('name', 'id'))
                ->disabled(),
            Forms\Components\Select::make('category_account_id')
                ->label(fn (Transaction $record) => $record->type === TransactionType::Deposit ? 'From Account (Revenue/Equity)' : 'To Account (Expense/Asset)')
                ->options(fn() => Account::pluck('name', 'id'))
                ->disabled(),
            Forms\Components\TextInput::make('amount')
                ->numeric()
                ->disabled(),
            Forms\Components\DatePicker::make('posted_at')->required(),
            Forms\Components\TextInput::make('reference'),
            Forms\Components\Textarea::make('description')->required(),
        ];
    }

    public static function mutateRecordDataForForm(array $data, Transaction $record): array
    {
        if ($record->type === TransactionType::Journal) {
            $data['journalEntries'] = $record->journalEntries->map(fn($e) => [
                'account_id' => $e->account_id,
                'type' => $e->type->value,
                'amount' => $e->amount,
                'description' => $e->description,
            ])->toArray();
        } else {
            $debit = $record->journalEntries->where('type', JournalEntryType::Debit)->first();
            $credit = $record->journalEntries->where('type', JournalEntryType::Credit)->first();

            if ($record->type === TransactionType::Deposit) {
                $data['bank_account_id'] = $debit?->account_id;
                $data['category_account_id'] = $credit?->account_id;
            } else if ($record->type === TransactionType::Withdrawal) {
                $data['bank_account_id'] = $credit?->account_id;
                $data['category_account_id'] = $debit?->account_id;
            }
        }
        return $data;
    }
}
