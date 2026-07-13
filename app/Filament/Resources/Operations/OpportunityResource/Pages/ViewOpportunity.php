<?php

namespace App\Filament\Resources\Operations\OpportunityResource\Pages;

use App\Filament\Resources\Operations\OpportunityResource;
use App\Domain\Opportunity\Models\Opportunity;
use App\Domain\Opportunity\Enums\OpportunityStatus;
use App\Domain\Opportunity\Services\OpportunityWorkflowService;
use App\Domain\Opportunity\Services\OpportunityReadinessService;
use App\Domain\Mou\Models\Mou;
use App\Filament\Resources\Operations\MOUResource;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\App;

class ViewOpportunity extends ViewRecord
{
    protected static string $resource = OpportunityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->visible(fn (Opportunity $record) => OpportunityResource::canEdit($record)),
            
            Actions\Action::make('markReadyForMou')
                ->label('Ready For MOU')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->visible(fn (Opportunity $record) => $record->status === OpportunityStatus::NEW)
                ->action(function (Opportunity $record) {
                    $readiness = app(OpportunityReadinessService::class)->canCreateMOU($record);
                    if (!$readiness['is_ready']) {
                        \Filament\Notifications\Notification::make()
                            ->title('Cannot Mark as Ready')
                            ->body(implode(' ', $readiness['errors']))
                            ->danger()
                            ->send();
                        return;
                    }
                    app(OpportunityWorkflowService::class)->markReadyForMou($record);
                    \Filament\Notifications\Notification::make()->title('Opportunity marked as Ready for MOU')->success()->send();
                }),

            Actions\Action::make('manageMou')
                ->label(fn (Opportunity $record) => Mou::where('opportunity_id', $record->id)->exists() ? 'Open MOU' : 'Create MOU')
                ->icon('heroicon-o-document-text')
                ->color('primary')
                ->visible(fn (Opportunity $record) => in_array($record->status, [OpportunityStatus::READY_FOR_MOU, OpportunityStatus::CONVERTED]))
                ->url(function (Opportunity $record) {
                    $mou = Mou::where('opportunity_id', $record->id)->first();
                    if ($mou) {
                        return MOUResource::getUrl('view', ['record' => $mou]);
                    }
                    return MOUResource::getUrl('create', ['opportunity_id' => $record->id]);
                }),
                
            Actions\Action::make('closeLost')
                ->label('Close Lost')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn (Opportunity $record) => !in_array($record->status, [OpportunityStatus::CLOSED_LOST, OpportunityStatus::CANCELLED, OpportunityStatus::CONVERTED]))
                ->form([
                    Forms\Components\Textarea::make('notes')->label('Notes'),
                ])
                ->action(fn (Opportunity $record, array $data) => app(OpportunityWorkflowService::class)->closeLost($record, $data['notes'] ?? null)),
        ];
    }
}
