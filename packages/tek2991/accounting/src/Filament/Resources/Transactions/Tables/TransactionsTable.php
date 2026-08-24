<?php

namespace Tek2991\Accounting\Filament\Resources\Transactions\Tables;

use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions;
use Illuminate\Support\Facades\DB;
use Tek2991\Accounting\Enums\JournalEntryType;
use Tek2991\Accounting\Enums\TransactionType;
use Tek2991\Accounting\Filament\Resources\Transactions\Schemas\TransactionForm;
use Tek2991\Accounting\Filament\Resources\Transactions\TransactionResource;
use Tek2991\Accounting\Models\BankAccount;
use Tek2991\Accounting\Models\Transaction;
use Tek2991\Accounting\Filament\Exports\TransactionExporter;

class TransactionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['bankAccount.account', 'account', 'journalEntries.account']))
            ->columns([
                Tables\Columns\TextColumn::make('posted_at')
                    ->label('Date')
                    ->date('M d, Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('description')
                    ->label('Description')
                    ->searchable(['description', 'reference'])
                    ->weight('medium')
                    ->description(fn (Transaction $record) => $record->reference ? "Ref: {$record->reference}" : null)
                    ->wrap()
                    ->limit(45),

                Tables\Columns\TextColumn::make('bank_account_display')
                    ->label('Bank / Account')
                    ->getStateUsing(function (Transaction $record) {
                        if ($record->bankAccount?->account?->name) {
                            return $record->bankAccount->account->name;
                        }
                        $bankEntry = $record->journalEntries->first(function ($je) {
                            return $je->account && (
                                in_array($je->account->system_role, [
                                    \Tek2991\Accounting\Enums\SystemRole::Bank,
                                    \Tek2991\Accounting\Enums\SystemRole::Cash,
                                ])
                                || str_contains(strtolower($je->account->name), 'bank')
                                || str_contains(strtolower($je->account->name), 'current')
                                || str_contains(strtolower($je->account->name), 'savings')
                                || str_contains(strtolower($je->account->name), 'cash')
                            );
                        });
                        return $bankEntry?->account?->name ?? '—';
                    })
                    ->icon(fn ($state) => $state !== '—' ? 'heroicon-m-building-library' : null)
                    ->iconColor('gray')
                    ->color(fn ($state) => $state === '—' ? 'gray' : null)
                    ->limit(24)
                    ->tooltip(fn ($state) => $state !== '—' ? $state : null)
                    ->toggleable(),

                Tables\Columns\TextColumn::make('journalEntries.account.name')
                    ->label('Accounts Affected')
                    ->badge()
                    ->color('gray')
                    ->limitList(2)
                    ->expandableLimitedList()
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Amount')
                    ->money(fn () => \Tek2991\Accounting\Facades\Accounting::getCurrency())
                    ->sortable()
                    ->alignEnd()
                    ->weight('bold')
                    ->color(fn (Transaction $record) => match ($record->type) {
                        TransactionType::Deposit, TransactionType::PaymentIn => 'success',
                        TransactionType::Withdrawal, TransactionType::PaymentOut => 'danger',
                        default => null,
                    }),

                Tables\Columns\IconColumn::make('document_path')
                    ->label('Doc')
                    ->alignCenter()
                    ->icon(fn ($state) => $state ? 'heroicon-m-paper-clip' : null)
                    ->color('primary')
                    ->tooltip(fn ($state) => $state ? 'Supporting document attached (click to view)' : null)
                    ->url(fn (Transaction $record) => $record->document_path ? \Illuminate\Support\Facades\Storage::url($record->document_path) : null, shouldOpenInNewTab: true)
                    ->toggleable(),

                Tables\Columns\TextColumn::make('reviewed')
                    ->label('Status')
                    ->alignCenter()
                    ->badge()
                    ->getStateUsing(fn (Transaction $record) => $record->reviewed ? 'Reviewed' : 'Pending')
                    ->color(fn (Transaction $record) => $record->reviewed ? 'success' : 'gray')
                    ->icon(fn (Transaction $record) => $record->reviewed ? 'heroicon-m-check-circle' : 'heroicon-m-clock'),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('posted_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options(TransactionType::class),

                Tables\Filters\SelectFilter::make('bank_account_id')
                    ->label('Bank Account')
                    ->options(fn () => BankAccount::getSelectOptions()),

                Tables\Filters\TernaryFilter::make('reviewed')
                    ->label('Review Status')
                    ->trueLabel('Reviewed')
                    ->falseLabel('Pending review')
                    ->placeholder('All'),

                Tables\Filters\Filter::make('posted_at')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('from')->label('From'),
                        \Filament\Forms\Components\DatePicker::make('until')->label('Until'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'], fn ($q, $date) => $q->whereDate('posted_at', '>=', $date))
                            ->when($data['until'], fn ($q, $date) => $q->whereDate('posted_at', '<=', $date));
                    }),
            ])
            ->recordUrl(fn (Transaction $record) => TransactionResource::getUrl('view', ['record' => $record]))
            ->recordActions([
                Actions\ActionGroup::make([
                    Actions\Action::make('markReviewed')
                        ->label('Mark as Reviewed')
                        ->icon('heroicon-o-check-badge')
                        ->color('success')
                        ->visible(fn (Transaction $record) => ! $record->reviewed)
                        ->requiresConfirmation()
                        ->action(function (Transaction $record) {
                            $record->update(['reviewed' => true]);
                            \Filament\Notifications\Notification::make()->success()->title('Marked as reviewed')->send();
                        }),

                    Actions\EditAction::make()
                        ->visible(fn (Transaction $record) => !$record->isPosted())
                        ->form(fn (Transaction $record) => TransactionForm::getDynamicFormSchema($record))
                        ->mutateRecordDataUsing(fn (array $data, Transaction $record) => TransactionForm::mutateRecordDataForForm($data, $record))
                        ->using(function (Transaction $record, array $data) {
                            if ($record->type === TransactionType::Journal) {
                                $entries     = $data['journalEntries'] ?? [];
                                unset($data['journalEntries']);

                                $totalDebit  = 0;
                                $totalCredit = 0;
                                foreach ($entries as $entry) {
                                    if ($entry['type'] === JournalEntryType::Debit->value) {
                                        $totalDebit += $entry['amount'];
                                    } else {
                                        $totalCredit += $entry['amount'];
                                    }
                                }

                                if (round($totalDebit, 2) !== round($totalCredit, 2) || round($totalDebit, 2) == 0) {
                                    \Filament\Notifications\Notification::make()
                                        ->danger()
                                        ->title('Unbalanced entries')
                                        ->body('Total debits must equal total credits.')
                                        ->send();
                                    throw new \Filament\Support\Exceptions\Halt();
                                }

                                DB::transaction(function () use ($record, $data, $entries, $totalDebit) {
                                    $data['amount'] = $totalDebit;
                                    $record->update($data);
                                    $record->journalEntries()->delete();
                                    foreach ($entries as $entry) {
                                        $record->journalEntries()->create([
                                            'account_id'  => $entry['account_id'],
                                            'type'        => $entry['type'],
                                            'amount'      => $entry['amount'],
                                            'description' => $entry['description'] ?? null,
                                        ]);
                                    }
                                });
                            } else {
                                $record->update($data);
                            }

                            return $record;
                        }),

                    Actions\ReplicateAction::make()
                        ->label('Duplicate')
                        ->icon('heroicon-o-document-duplicate')
                        ->excludeAttributes(['reference', 'posted_at', 'reviewed', 'allow_reversal'])
                        ->beforeReplicaSaved(function (Transaction $replica) {
                            $replica->posted_at      = now();
                            $replica->reviewed       = false;
                            $replica->allow_reversal = true;
                        })
                        ->after(function (Transaction $replica, Transaction $record) {
                            foreach ($record->journalEntries as $entry) {
                                $replica->journalEntries()->create([
                                    'account_id'  => $entry->account_id,
                                    'type'        => $entry->type,
                                    'amount'      => $entry->amount,
                                    'description' => $entry->description,
                                ]);
                            }
                        }),

                    Actions\Action::make('reverse')
                        ->label('Reverse')
                        ->icon('heroicon-o-arrow-path')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->visible(fn (Transaction $record) => $record->allow_reversal)
                        ->action(function (Transaction $record) {
                            $service = new \Tek2991\Accounting\Services\TransactionService();
                            $service->reverseTransaction($record);
                            \Filament\Notifications\Notification::make()->success()->title('Transaction Reversed')->send();
                        }),
                ]),
            ])
            ->groupedBulkActions([
                Actions\ExportBulkAction::make()
                    ->exporter(TransactionExporter::class),
                Actions\BulkAction::make('delete')
                    ->label('Delete Selected')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (\Illuminate\Database\Eloquent\Collection $records) {
                        $skipped = 0;
                        foreach ($records as $record) {
                            if (!$record->isPosted()) {
                                $record->journalEntries()->delete();
                                $record->delete();
                            } else {
                                $skipped++;
                            }
                        }
                        
                        if ($skipped > 0) {
                            \Filament\Notifications\Notification::make()
                                ->warning()
                                ->title("{$skipped} posted transaction(s) could not be deleted.")
                                ->body('Posted transactions are immutable and must be reversed instead.')
                                ->send();
                        }
                    })
                    ->deselectRecordsAfterCompletion(),
            ]);
    }
}
