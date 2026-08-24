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
                ->action(function ($record, ViewBill $livewire) {
                    try {
                        app(BillService::class)->post($record);
                        \Filament\Notifications\Notification::make()->title('Bill posted')->success()->send();
                        $livewire->getRecord()->refresh();
                        $livewire->fillForm();
                    } catch (\Exception $e) {
                        \Filament\Notifications\Notification::make()->title('Failed to post bill')->body($e->getMessage())->danger()->send();
                    }
                }),
            Actions\Action::make('record_payment')
                ->label('Record Payment')
                ->icon('heroicon-o-banknotes')
                ->visible(fn ($record) => in_array($record->status, [BillStatus::Received, BillStatus::PartiallyPaid]))
                ->fillForm(function ($record): array {
                    $defaultBankId = \Tek2991\Accounting\Facades\Accounting::getDefaultBankAccountId();

                    return [
                        'amount' => $record->balance_due,
                        'payment_account_id' => $defaultBankId,
                        'payment_date' => now()->toDateString(),
                    ];
                })
                ->form([
                    TextInput::make('amount')
                        ->numeric()
                        ->required(),
                    Select::make('payment_account_id')
                        ->label('Paid From (Bank / Cash Account)')
                        ->options(function () {
                            $defaultId = \Tek2991\Accounting\Facades\Accounting::getDefaultBankAccountId();

                            return Account::where('type', \Tek2991\Accounting\Enums\AccountType::Asset)
                                ->where(function ($q) {
                                    $q->whereIn('system_role', [
                                        \Tek2991\Accounting\Enums\SystemRole::Bank,
                                        \Tek2991\Accounting\Enums\SystemRole::Cash,
                                    ])
                                    ->orWhere('code', 'like', '11%')
                                    ->orWhere('name', 'like', '%Current Account%')
                                    ->orWhere('name', 'like', '%Savings Account%')
                                    ->orWhere('name', 'like', '%Bank%')
                                    ->orWhere('name', 'like', '%Cash%');
                                })
                                ->where('is_control_account', false)
                                ->get()
                                ->mapWithKeys(function (Account $acc) use ($defaultId) {
                                    if ($acc->id === $defaultId) {
                                        return [$acc->id => "<div style='display: flex; align-items: center; justify-content: space-between; width: 100%;'><span>{$acc->name}</span><span style='font-size: 10px; font-weight: 700; background: #dbeafe; color: #1e40af; padding: 2px 8px; border-radius: 9999px; text-transform: uppercase;'>Default</span></div>"];
                                    }
                                    return [$acc->id => "<div>{$acc->name}</div>"];
                                });
                        })
                        ->allowHtml()
                        ->searchable()
                        ->preload()
                        ->required(),
                    DatePicker::make('payment_date')
                        ->required(),
                    TextInput::make('reference')
                        ->label('Payment Reference (UTR / Cheque #)'),
                ])
                ->action(function ($record, array $data, ViewBill $livewire) {
                    try {
                        app(BillService::class)->recordPayment($record, $data);
                        \Filament\Notifications\Notification::make()->title('Payment recorded')->success()->send();
                        $livewire->getRecord()->refresh();
                        $livewire->fillForm();
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
                ->action(function ($record, ViewBill $livewire) {
                    try {
                        app(BillService::class)->cancel($record);
                        \Filament\Notifications\Notification::make()->title('Bill cancelled')->success()->send();
                        $livewire->getRecord()->refresh();
                        $livewire->fillForm();
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
