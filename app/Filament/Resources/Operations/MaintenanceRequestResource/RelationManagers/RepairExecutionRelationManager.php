<?php

namespace App\Filament\Resources\Operations\MaintenanceRequestResource\RelationManagers;

use App\Domain\Maintenance\Enums\MaintenanceStatus;
use App\Domain\Maintenance\Models\MaintenanceRequestItem;
use App\Domain\Maintenance\Services\MaintenanceAuditTriggerService;
use App\Domain\Property\Models\PropertyInventory;
use App\Domain\Property\Models\PropertyRoom;
use App\Domain\Property\Models\PropertyUtility;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class RepairExecutionRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'Repair Execution & Completion';

    protected static \BackedEnum|string|null $icon = 'heroicon-o-check-badge';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Defect Context')
                    ->schema([
                        Placeholder::make('item_summary')
                            ->label('Target Area & Defect')
                            ->content(function ($record) {
                                if (!$record) return '';
                                $name = 'General Area';
                                if ($record->itemable instanceof PropertyRoom) {
                                    $name = 'Room: ' . ($record->itemable->custom_name ?: ($record->itemable->roomDefinition?->name ?? "Room #{$record->itemable->id}"));
                                } elseif ($record->itemable instanceof PropertyInventory) {
                                    $name = 'Inventory: ' . ($record->itemable->inventoryType?->name ?? "Item #{$record->itemable->id}");
                                } elseif ($record->itemable instanceof PropertyUtility) {
                                    $name = 'Utility: ' . ($record->itemable->utilityType?->name ?? "Utility #{$record->itemable->id}");
                                }
                                $desc = e($record->issue_description);
                                return new HtmlString("<strong>{$name}</strong><br><span style=\"color: gray;\">Reported Defect: {$desc}</span>");
                            }),
                    ]),

                Section::make('Completed Repair Work & Proof')
                    ->columns(2)
                    ->schema([
                        TextInput::make('repair_action')
                            ->label('Action Taken / Resolution Notes')
                            ->placeholder('e.g. Replaced leaking valve, renewed sealants, tested water flow')
                            ->required()
                            ->columnSpanFull(),

                        TextInput::make('actual_cost')
                            ->label('Actual Completed Cost (₹)')
                            ->numeric()
                            ->prefix('₹')
                            ->default(0.00)
                            ->required()
                            ->columnSpanFull(),

                        SpatieMediaLibraryFileUpload::make('repaired_photos')
                            ->collection('repaired_photos')
                            ->multiple()
                            ->required()
                            ->label('Repaired Photos / Videos (After Repair Proof)')
                            ->helperText('Upload clear photos/videos proving the physical work has been completed.')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('issue_description')
            ->heading('On-Site Repair Execution Tracking')
            ->description('Update repair details and upload after-repair proof for all items. All items must be updated to mark the work completed and trigger the verification audit.')
            ->columns([
                TextColumn::make('item_name')
                    ->label('Target Item')
                    ->state(function ($record) {
                        if (!$record->itemable) {
                            return 'General Property Area';
                        }
                        if ($record->itemable instanceof PropertyRoom) {
                            return '🚪 ' . ($record->itemable->custom_name ?: ($record->itemable->roomDefinition?->name ?? "Room #{$record->itemable->id}"));
                        }
                        if ($record->itemable instanceof PropertyInventory) {
                            return '📦 ' . ($record->itemable->inventoryType?->name ?? "Item #{$record->itemable->id}");
                        }
                        if ($record->itemable instanceof PropertyUtility) {
                            return '⚡ ' . ($record->itemable->utilityType?->name ?? "Utility #{$record->itemable->id}");
                        }
                        return 'Item';
                    })
                    ->weight('bold'),

                TextColumn::make('issue_description')
                    ->label('Defect Description')
                    ->limit(40),

                SpatieMediaLibraryImageColumn::make('issue_photos')
                    ->collection('issue_photos')
                    ->label('Before Photos')
                    ->circular()
                    ->stacked()
                    ->limit(2),

                TextColumn::make('repair_action')
                    ->label('Resolution / Work Done')
                    ->placeholder('Pending Work Log')
                    ->limit(35),

                TextColumn::make('actual_cost')
                    ->label('Cost (₹)')
                    ->money('INR'),

                SpatieMediaLibraryImageColumn::make('repaired_photos')
                    ->collection('repaired_photos')
                    ->label('After Photos')
                    ->circular()
                    ->stacked()
                    ->limit(3),

                TextColumn::make('status')
                    ->label('Repair Status')
                    ->badge()
                    ->state(function ($record) {
                        $hasPhotos = $record->hasMedia('repaired_photos');
                        $hasAction = filled($record->repair_action);
                        if ($hasPhotos && $hasAction) {
                            return 'COMPLETED';
                        }
                        if ($hasPhotos || $hasAction) {
                            return 'IN PROGRESS';
                        }
                        return 'PENDING REPAIR';
                    })
                    ->color(function (string $state): string {
                        return match ($state) {
                            'COMPLETED' => 'success',
                            'IN PROGRESS' => 'info',
                            default => 'warning',
                        };
                    }),
            ])
            ->headerActions([
                Action::make('markWorkCompletedAndTriggerAudit')
                    ->label('Mark Work Completed & Trigger Audit')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->button()
                    ->size('sm')
                    ->visible(function (RelationManager $livewire) {
                        $ticket = $livewire->getOwnerRecord();
                        if (!$ticket) return false;

                        return empty($ticket->triggered_audit_id) && in_array($ticket->status, [
                            MaintenanceStatus::IN_PROGRESS,
                            MaintenanceStatus::SUBMITTED,
                            MaintenanceStatus::VENDOR_ASSIGNED,
                            MaintenanceStatus::QUOTED,
                            MaintenanceStatus::QUOTATION_APPROVED,
                        ]);
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Mark Repairs Completed & Initiate Verification Audit')
                    ->modalDescription('Confirm that on-site technicians have finished all repairs and uploaded after-repair proof for all items. This will create a Post-Repair Verification Audit for quality inspection.')
                    ->modalSubmitActionLabel('Confirm & Trigger Audit')
                    ->action(function (RelationManager $livewire) {
                        $ticket = $livewire->getOwnerRecord();
                        $ticket->loadMissing('items');

                        if ($ticket->items->isEmpty()) {
                            Notification::make()
                                ->title('No Items Found')
                                ->body('There are no defect items recorded on this maintenance ticket.')
                                ->warning()
                                ->persistent()
                                ->send();
                            return;
                        }

                        // Validate that ALL items have repair work and repaired photos uploaded
                        $incompleteItems = [];
                        foreach ($ticket->items as $index => $item) {
                            $itemNum = $index + 1;
                            $hasPhotos = $item->hasMedia('repaired_photos');
                            $hasAction = filled($item->repair_action);

                            if (!$hasPhotos || !$hasAction) {
                                $targetName = 'Item #' . $itemNum;
                                if ($item->itemable instanceof PropertyRoom) {
                                    $targetName = $item->itemable->custom_name ?: ($item->itemable->roomDefinition?->name ?? "Room #{$itemNum}");
                                } elseif ($item->itemable instanceof PropertyInventory) {
                                    $targetName = $item->itemable->inventoryType?->name ?? "Inventory #{$itemNum}";
                                }
                                $missing = [];
                                if (!$hasAction) $missing[] = 'work resolution notes';
                                if (!$hasPhotos) $missing[] = 'after-repair photos';
                                $incompleteItems[] = "<strong>{$targetName}</strong> (missing " . implode(' & ', $missing) . ")";
                            }
                        }

                        if (!empty($incompleteItems)) {
                            $listHtml = implode('<br>&bull; ', $incompleteItems);
                            Notification::make()
                                ->title('Incomplete Repair Items')
                                ->body(new HtmlString("All repair items must be updated with work details and after-repair photos before completing work:<br>&bull; {$listHtml}"))
                                ->danger()
                                ->persistent()
                                ->send();
                            return;
                        }

                        // Mark all items as completed
                        foreach ($ticket->items as $item) {
                            $item->update(['status' => 'completed']);
                        }

                        // Trigger Audit using the service
                        $service = app(MaintenanceAuditTriggerService::class);
                        $audit = $service->triggerAudit($ticket);

                        Notification::make()
                            ->title('Repair Work Completed')
                            ->body("All items verified. Post-Repair Verification Audit #{$audit->audit_number} created successfully.")
                            ->success()
                            ->send();

                        $livewire->dispatch('$refresh');
                    }),
            ])
            ->recordActions([
                EditAction::make('updateRepairWork')
                    ->label('Update Repair Work')
                    ->icon('heroicon-o-wrench')
                    ->color('primary')
                    ->modalHeading('Update Completed Repair Details & Photos')
                    ->modalWidth('3xl')
                    ->after(function (MaintenanceRequestItem $record, RelationManager $livewire) {
                        if ($record->hasMedia('repaired_photos')) {
                            $record->update(['status' => 'completed']);
                        }
                        $livewire->getOwnerRecord()->syncQuotationTotals();
                        $livewire->dispatch('$refresh');
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([]),
            ]);
    }
}
