<?php

namespace App\Filament\Resources\Operations\AuditResource\Pages;

use App\Filament\Resources\Operations\AuditResource;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;

class EditAudit extends EditRecord
{
    protected static string $resource = AuditResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('inspect')
                ->label('Perform Inspection')
                ->icon('heroicon-o-clipboard-document-check')
                ->color('primary')
                ->url(fn () => AuditResource::getUrl('inspect', ['record' => $this->getRecord()])),

            Action::make('startAudit')
                ->label('Start Audit')
                ->icon('heroicon-o-play')
                ->color('info')
                ->visible(fn () => $this->getRecord()->status === \App\Domain\Audit\Enums\AuditStatus::DRAFT)
                ->action(function () {
                    $this->getRecord()->update(['status' => \App\Domain\Audit\Enums\AuditStatus::IN_PROGRESS]);
                    
                    \Filament\Notifications\Notification::make()
                        ->title('Audit started successfully')
                        ->body('You can now inspect items and submit for review once all items are inspected.')
                        ->success()
                        ->send();

                    $this->refreshFormData(['status']);
                }),

            Action::make('review')
                ->label('Open Review Page')
                ->icon('heroicon-o-clipboard-document-check')
                ->color('warning')
                ->visible(fn () => $this->getRecord()->canReview())
                ->url(fn () => AuditResource::getUrl('review', ['record' => $this->getRecord()])),

            Actions\DeleteAction::make(),
        ];
    }
}
