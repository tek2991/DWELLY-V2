<?php

namespace Tek2991\Accounting\Filament\Resources\Sales\CreditNotes\Pages;

use Tek2991\Accounting\Filament\Resources\Sales\CreditNotes\CreditNoteResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Tek2991\Accounting\Enums\CreditNoteStatus;
use Tek2991\Accounting\Services\CreditNoteService;

class ViewCreditNote extends ViewRecord
{
    protected static string $resource = CreditNoteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->visible(fn ($record) => $record->status === CreditNoteStatus::Draft),
            Actions\Action::make('post')
                ->label('Post')
                ->icon('heroicon-o-paper-airplane')
                ->requiresConfirmation()
                ->visible(fn ($record) => $record->status === CreditNoteStatus::Draft)
                ->action(function ($record) {
                    app(CreditNoteService::class)->post($record);
                    \Filament\Notifications\Notification::make()->title('Credit Note posted')->success()->send();
                }),
            Actions\Action::make('cancel')
                ->label('Cancel')
                ->icon('heroicon-o-x-circle')
                ->requiresConfirmation()
                ->color('danger')
                ->visible(fn ($record) => $record->status !== CreditNoteStatus::Cancelled && $record->status !== CreditNoteStatus::Applied)
                ->action(function ($record) {
                    app(CreditNoteService::class)->cancel($record);
                    \Filament\Notifications\Notification::make()->title('Credit Note cancelled')->success()->send();
                }),
        ];
    }
}
