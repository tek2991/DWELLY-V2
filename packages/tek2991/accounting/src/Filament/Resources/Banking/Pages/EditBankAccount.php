<?php

namespace Tek2991\Accounting\Filament\Resources\Banking\Pages;

use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Tek2991\Accounting\Filament\Resources\Banking\BankAccountResource;

class EditBankAccount extends EditRecord
{
    protected static string $resource = BankAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->before(function (\Tek2991\Accounting\Models\BankAccount $record, Actions\DeleteAction $action) {
                    if ($record->transactions()->exists()) {
                        \Filament\Notifications\Notification::make()
                            ->danger()
                            ->title('Cannot Delete')
                            ->body('This bank account has transactions and cannot be deleted.')
                            ->send();
                        $action->halt();
                    }
                }),
        ];
    }
}
