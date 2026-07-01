<?php

namespace Tek2991\Accounting\Filament\Resources\Purchases\DebitNotes\Pages;

use Tek2991\Accounting\Filament\Resources\Purchases\DebitNotes\DebitNoteResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Tek2991\Accounting\Enums\DebitNoteStatus;
use Tek2991\Accounting\Services\DebitNoteService;

class ViewDebitNote extends ViewRecord
{
    protected static string $resource = DebitNoteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->visible(fn ($record) => $record->status === DebitNoteStatus::Draft),
            Actions\Action::make('post')
                ->label('Post')
                ->icon('heroicon-o-paper-airplane')
                ->requiresConfirmation()
                ->visible(fn ($record) => $record->status === DebitNoteStatus::Draft)
                ->action(function ($record) {
                    app(DebitNoteService::class)->post($record);
                    \Filament\Notifications\Notification::make()->title('Debit Note posted')->success()->send();
                }),
            Actions\Action::make('cancel')
                ->label('Cancel')
                ->icon('heroicon-o-x-circle')
                ->requiresConfirmation()
                ->color('danger')
                ->visible(fn ($record) => $record->status !== DebitNoteStatus::Cancelled && $record->status !== DebitNoteStatus::Applied)
                ->action(function ($record) {
                    app(DebitNoteService::class)->cancel($record);
                    \Filament\Notifications\Notification::make()->title('Debit Note cancelled')->success()->send();
                }),
        ];
    }
}
