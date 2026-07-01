<?php

namespace Tek2991\Accounting\Filament\Resources\Purchases\Bills\Pages;

use Tek2991\Accounting\Filament\Resources\Purchases\Bills\BillResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Tek2991\Accounting\Enums\BillStatus;
use Tek2991\Accounting\Services\BillService;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DatePicker;
use Tek2991\Accounting\Models\Account;
use Tek2991\Accounting\Enums\AccountType;

class ViewBill extends ViewRecord
{
    protected static string $resource = BillResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->visible(fn ($record) => $record->status === BillStatus::Draft),
            Actions\Action::make('post')
                ->label('Post')
                ->icon('heroicon-o-paper-airplane')
                ->requiresConfirmation()
                ->visible(fn ($record) => $record->status === BillStatus::Draft)
                ->action(function ($record) {
                    try {
                        app(BillService::class)->post($record);
                        \Filament\Notifications\Notification::make()->title('Bill posted')->success()->send();
                    } catch (\Exception $e) {
                        \Filament\Notifications\Notification::make()->title('Failed to post bill')->body($e->getMessage())->danger()->send();
                    }
                }),
            Actions\Action::make('record_payment')
                ->label('Record Payment')
                ->icon('heroicon-o-banknotes')
                ->visible(fn ($record) => in_array($record->status, [BillStatus::Received, BillStatus::PartiallyPaid]))
                ->form([
                    TextInput::make('amount')
                        ->numeric()
                        ->required()
                        ->default(fn ($record) => $record->balance_due),
                    Select::make('payment_account_id')
                        ->label('Payment Account')
                        ->options(Account::where('type', \Tek2991\Accounting\Enums\AccountType::Asset)->pluck('name', 'id'))
                        ->required(),
                    DatePicker::make('payment_date')
                        ->default(now())
                        ->required(),
                    TextInput::make('reference'),
                ])
                ->action(function ($record, array $data) {
                    try {
                                                app(BillService::class)->recordPayment($record, $data);
                        \Filament\Notifications\Notification::make()->title('Payment recorded')->success()->send();
                    } catch (\Exception $e) {
                        \Filament\Notifications\Notification::make()->title('Payment failed')->body($e->getMessage())->danger()->send();
                    }
                }),
            Actions\Action::make('cancel')
                ->label('Cancel')
                ->icon('heroicon-o-x-circle')
                ->requiresConfirmation()
                ->color('danger')
                ->visible(fn ($record) => $record->status !== BillStatus::Cancelled && $record->status !== BillStatus::Paid)
                ->action(function ($record) {
                    try {
                        app(BillService::class)->cancel($record);
                        \Filament\Notifications\Notification::make()->title('Bill cancelled')->success()->send();
                    } catch (\Exception $e) {
                        \Filament\Notifications\Notification::make()->title('Failed to cancel bill')->body($e->getMessage())->danger()->send();
                    }
                }),
            Actions\ActionGroup::make([
                Actions\Action::make('issue_debit_note')
                    ->label('Issue Debit Note')
                    ->icon('heroicon-o-document-minus')
                    ->action(function ($record) {
                        $quantities = [];
                        foreach ($record->items as $item) {
                            $quantities[$item->id] = $item->quantity;
                        }
                        $dn = app(\Tek2991\Accounting\Services\DebitNoteService::class)->createFromBill($record, $quantities);
                        return redirect(\Tek2991\Accounting\Filament\Resources\Purchases\DebitNotes\DebitNoteResource::getUrl('edit', ['record' => $dn->id]));
                    })
            ])->label('More')->icon('heroicon-m-ellipsis-vertical'),
        ];
    }
}
