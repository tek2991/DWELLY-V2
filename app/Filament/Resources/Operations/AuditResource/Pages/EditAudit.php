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

            Action::make('review')
                ->label('Open Review Page')
                ->icon('heroicon-o-clipboard-document-check')
                ->color('warning')
                ->visible(fn () => $this->getRecord()->canReview())
                ->url(fn () => AuditResource::getUrl('review', ['record' => $this->getRecord()])),

            Actions\DeleteAction::make(),
        ];
    }

    public function getSubheading(): string | \Illuminate\Support\HtmlString | null
    {
        $record = $this->getRecord();
        if (!$record) {
            return null;
        }

        return new \Illuminate\Support\HtmlString(
            view('components.audit-header', ['audit' => $record->loadMissing(['property', 'inspector'])])->render()
        );
    }
}
