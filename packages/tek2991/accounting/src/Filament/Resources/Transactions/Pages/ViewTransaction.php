<?php

namespace Tek2991\Accounting\Filament\Resources\Transactions\Pages;

use Filament\Actions;
use Filament\Schemas\Schema;
use Filament\Infolists;
use Filament\Resources\Pages\ViewRecord;
use Tek2991\Accounting\Enums\TransactionType;
use Tek2991\Accounting\Filament\Resources\Transactions\TransactionResource;
use Tek2991\Accounting\Models\Transaction;

class ViewTransaction extends ViewRecord
{
    protected static string $resource = TransactionResource::class;

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                \Filament\Schemas\Components\Section::make('Transaction Details')
                    ->columns(3)
                    ->schema([
                        Infolists\Components\TextEntry::make('type')
                            ->badge(),

                        Infolists\Components\TextEntry::make('posted_at')
                            ->label('Date')
                            ->date(),

                        Infolists\Components\TextEntry::make('amount')
                            ->label('Amount')
                            ->money(fn () => \Tek2991\Accounting\Facades\Accounting::getCurrency()),

                        Infolists\Components\TextEntry::make('bankAccount.account.name')
                            ->label('Bank Account')
                            ->placeholder('—'),

                        Infolists\Components\TextEntry::make('account.name')
                            ->label('Category')
                            ->placeholder('Journal Entry'),

                        Infolists\Components\IconEntry::make('reviewed')
                            ->boolean()
                            ->trueIcon('heroicon-o-check-circle')
                            ->falseIcon('heroicon-o-clock'),

                        Infolists\Components\TextEntry::make('reference')
                            ->placeholder('—'),

                        Infolists\Components\TextEntry::make('description')
                            ->columnSpanFull(),
                    ]),

                \Filament\Schemas\Components\Section::make('Journal Entries')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('journalEntries')
                            ->label('')
                            ->schema([
                                Infolists\Components\TextEntry::make('account.code')
                                    ->label('Code')
                                    ->badge()
                                    ->color('gray'),

                                Infolists\Components\TextEntry::make('account.name')
                                    ->label('Account'),

                                Infolists\Components\TextEntry::make('type')
                                    ->badge(),

                                Infolists\Components\TextEntry::make('amount')
                                    ->label('Amount')
                                    ->money(fn () => \Tek2991\Accounting\Facades\Accounting::getCurrency()),

                                Infolists\Components\TextEntry::make('description')
                                    ->label('Note')
                                    ->placeholder('—'),
                            ])
                            ->columns(5),
                    ]),

                \Filament\Schemas\Components\Section::make('Meta')
                    ->columns(2)
                    ->collapsed()
                    ->schema([
                        Infolists\Components\TextEntry::make('created_at')
                            ->dateTime(),

                        Infolists\Components\TextEntry::make('updated_at')
                            ->dateTime(),
                    ]),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('markReviewed')
                ->label('Mark as Reviewed')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->requiresConfirmation()
                ->hidden(fn (Transaction $record) => $record->reviewed)
                ->action(function (Transaction $record) {
                    $record->update(['reviewed' => true]);
                    \Filament\Notifications\Notification::make()->success()->title('Marked as reviewed')->send();
                    $this->refreshFormData(['reviewed']);
                }),

            Actions\Action::make('reverse')
                ->label('Reverse Transaction')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->requiresConfirmation()
                ->hidden(fn (Transaction $record) => ! $record->allow_reversal)
                ->action(function (Transaction $record) {
                    $service = new \Tek2991\Accounting\Services\TransactionService();
                    $service->reverseTransaction($record);
                    \Filament\Notifications\Notification::make()->success()->title('Transaction reversed')->send();
                    $this->redirect(TransactionResource::getUrl('index'));
                }),
        ];
    }
}
