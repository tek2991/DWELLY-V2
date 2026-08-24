<?php

namespace Tek2991\Accounting\Filament\Resources\Sales\Invoices\Pages;

use Tek2991\Accounting\Filament\Resources\Sales\Invoices\InvoiceResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Tek2991\Accounting\Enums\InvoiceStatus;
use Tek2991\Accounting\Services\InvoiceService;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DatePicker;
use Tek2991\Accounting\Models\Account;
use Tek2991\Accounting\Enums\AccountType;

class ViewInvoice extends ViewRecord
{
    protected static string $resource = InvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->visible(fn ($record) => $record->status === InvoiceStatus::Draft),
            Actions\Action::make('post')
                ->label('Post')
                ->icon('heroicon-o-paper-airplane')
                ->requiresConfirmation()
                ->visible(fn ($record) => $record->status === InvoiceStatus::Draft)
                ->action(function ($record, ViewInvoice $livewire) {
                    app(InvoiceService::class)->post($record);
                    \Filament\Notifications\Notification::make()->title('Invoice posted')->success()->send();
                    $livewire->getRecord()->refresh();
                    $livewire->fillForm();
                }),
            Actions\Action::make('download_pdf')
                ->label('Download PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->action(function ($record) {
                    $path = app(InvoiceService::class)->generatePdf($record);
                    $disk = config('accounting.pdf.disk', 'public');
                    return response()->download(\Illuminate\Support\Facades\Storage::disk($disk)->path($path));
                }),
            Actions\Action::make('download_receipt')
                ->label('Download Receipt')
                ->icon('heroicon-o-receipt-percent')
                ->color('success')
                ->visible(fn ($record) => $record->payments()->exists())
                ->url(fn ($record) => route('billing.receipt.pdf', [
                    'invoice' => $record->id,
                    'payment' => $record->payments()->latest()->first()?->id ?? 0,
                ]), shouldOpenInNewTab: true),
            Actions\Action::make('record_payment')
                ->label('Record Payment')
                ->icon('heroicon-o-banknotes')
                ->visible(fn ($record) => in_array($record->status, [InvoiceStatus::Sent, InvoiceStatus::PartiallyPaid]))
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
                        ->label('Deposit To (Bank / Cash Account)')
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
                ->action(function ($record, array $data, ViewInvoice $livewire) {
                    $payment = app(InvoiceService::class)->recordPayment($record, $data);
                    \Filament\Notifications\Notification::make()
                        ->title('Payment recorded successfully')
                        ->body("Payment of ₹" . number_format((float) $data['amount'], 2) . " recorded.")
                        ->actions([
                            \Filament\Notifications\Actions\Action::make('download_receipt')
                                ->label('Download Receipt')
                                ->icon('heroicon-o-arrow-down-tray')
                                ->button()
                                ->url(route('billing.receipt.pdf', ['invoice' => $record->id, 'payment' => $payment->id]), shouldOpenInNewTab: true),
                        ])
                        ->success()
                        ->send();
                    $livewire->getRecord()->refresh();
                    $livewire->fillForm();
                }),
            Actions\Action::make('cancel')
                ->label('Cancel')
                ->icon('heroicon-o-x-circle')
                ->requiresConfirmation()
                ->color('danger')
                ->visible(fn ($record) => $record->status !== InvoiceStatus::Cancelled && $record->status !== InvoiceStatus::Paid)
                ->action(function ($record, ViewInvoice $livewire) {
                    app(InvoiceService::class)->cancel($record);
                    \Filament\Notifications\Notification::make()->title('Invoice cancelled')->success()->send();
                    $livewire->getRecord()->refresh();
                    $livewire->fillForm();
                }),
            Actions\ActionGroup::make([
                Actions\Action::make('issue_credit_note')
                    ->label('Issue Credit Note')
                    ->icon('heroicon-o-document-minus')
                    ->action(function ($record) {
                        $quantities = [];
                        foreach ($record->items as $item) {
                            $quantities[$item->id] = $item->quantity;
                        }
                        $cn = app(\Tek2991\Accounting\Services\CreditNoteService::class)->createFromInvoice($record, $quantities);
                        return redirect(\Tek2991\Accounting\Filament\Resources\Sales\CreditNotes\CreditNoteResource::getUrl('edit', ['record' => $cn->id]));
                    })
            ])->label('More')->icon('heroicon-m-ellipsis-vertical'),
        ];
    }
}
