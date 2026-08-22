<?php

namespace App\Filament\Resources\Operations\OpportunityResource\Tables;

use App\Domain\Mou\Models\Mou;
use App\Domain\Opportunity\Enums\OpportunityStatus;
use App\Domain\Opportunity\Models\Opportunity;
use App\Domain\Opportunity\Services\OpportunityReadinessService;
use App\Domain\Opportunity\Services\OpportunityWorkflowService;
use App\Filament\Resources\Operations\MOUResource;
use App\Filament\Resources\Operations\OpportunityResource;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class OpportunitiesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('number')
                    ->label('Opportunity #')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->copyable()
                    ->copyMessage('Opportunity number copied')
                    ->tooltip('Click to copy Opportunity #'),

                TextColumn::make('title')
                    ->label('Lead Title')
                    ->searchable()
                    ->weight('medium')
                    ->description(fn (Opportunity $record) => $record->owner_name ? "👤 {$record->owner_name}" : null),

                TextColumn::make('status')
                    ->badge()
                    ->sortable(),

                TextColumn::make('estimatedPropertyType.name')
                    ->label('Type')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('estimated_bhk')
                    ->label('BHK')
                    ->placeholder('—')
                    ->badge()
                    ->color('gray')
                    ->toggleable(),

                TextColumn::make('expected_rent')
                    ->label('Expected Rent')
                    ->money('INR')
                    ->sortable()
                    ->placeholder('—'),

                TextColumn::make('opportunitySource.name')
                    ->label('Source')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('source_phone')
                    ->label('Source Phone')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('assignedUser.name')
                    ->label('Assigned To')
                    ->placeholder('Unassigned')
                    ->searchable(),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('d M Y, H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordUrl(
                fn (Opportunity $record): string => OpportunityResource::getUrl('view', ['record' => $record]),
            )
            ->filters([
                TrashedFilter::make(),
                SelectFilter::make('status')
                    ->options(OpportunityStatus::class),
                SelectFilter::make('opportunity_source_id')
                    ->label('Source')
                    ->relationship('opportunitySource', 'name'),
                SelectFilter::make('assigned_user_id')
                    ->label('Assigned Staff')
                    ->relationship('assignedUser', 'name'),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make()
                    ->visible(fn ($record) => OpportunityResource::canEdit($record)),

                ActionGroup::make([
                    Action::make('markReadyForMou')
                        ->label('Ready For MOU')
                        ->icon('heroicon-o-check-badge')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Mark as Ready for MOU')
                        ->modalDescription('Are you sure you want to mark this opportunity as Ready for MOU? This will verify prerequisite commercial and contact details.')
                        ->modalSubmitActionLabel('Confirm & Mark Ready')
                        ->visible(fn (Opportunity $record) => $record->status === OpportunityStatus::NEW)
                        ->action(function (Opportunity $record) {
                            $readiness = app(OpportunityReadinessService::class)->canCreateMOU($record);
                            if (! $readiness['is_ready']) {
                                Notification::make()
                                    ->title('Cannot Mark as Ready')
                                    ->body(implode(' ', $readiness['errors']))
                                    ->danger()
                                    ->send();

                                return;
                            }
                            app(OpportunityWorkflowService::class)->markReadyForMou($record);
                            Notification::make()->title('Opportunity marked as Ready for MOU')->success()->send();
                        }),

                    Action::make('manageMou')
                        ->label(fn (Opportunity $record) => Mou::where('opportunity_id', $record->id)->exists() ? 'Open MOU' : 'Create MOU')
                        ->icon('heroicon-o-document-text')
                        ->color('primary')
                        ->visible(fn (Opportunity $record) => in_array($record->status, [OpportunityStatus::READY_FOR_MOU, OpportunityStatus::CONVERTED]))
                        ->requiresConfirmation(fn (Opportunity $record) => ! Mou::where('opportunity_id', $record->id)->exists())
                        ->modalHeading('Create MOU')
                        ->modalDescription('Are you sure you want to create an onboarding MOU agreement for this opportunity?')
                        ->modalSubmitActionLabel('Yes, Create MOU')
                        ->action(function (Opportunity $record) {
                            $mou = Mou::where('opportunity_id', $record->id)->first();
                            if ($mou) {
                                return redirect(MOUResource::getUrl('view', ['record' => $mou]));
                            }

                            return redirect(MOUResource::getUrl('create', ['opportunity_id' => $record->id]));
                        }),

                    Action::make('closeLost')
                        ->label('Close Lost')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->visible(fn (Opportunity $record) => ! in_array($record->status, [OpportunityStatus::CONVERTED, OpportunityStatus::CLOSED_LOST, OpportunityStatus::CANCELLED, OpportunityStatus::MOU_SIGNED]))
                        ->form([
                            Textarea::make('notes')->label('Reason for losing')->placeholder('Enter details regarding why this lead was lost...')->required(),
                        ])
                        ->action(fn (Opportunity $record, array $data) => app(OpportunityWorkflowService::class)->closeLost($record, $data['notes'] ?? null)),
                ])->label('Actions'),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
