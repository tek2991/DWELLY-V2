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
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
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
                // 1. Previous Before-Repair Photos Thumbnails & Interactive Swipe Gallery Lightbox
                View::make('filament.forms.components.defect-photos-lightbox')
                    ->columnSpanFull(),

                // 2. Action Taken / Resolution Notes
                Textarea::make('repair_action')
                    ->label('Action Taken / Resolution Notes')
                    ->placeholder('Describe physical repair work, parts replaced, sealants applied, or testing performed...')
                    ->rows(3)
                    ->required()
                    ->columnSpanFull(),

                // 3. Upload Repaired Photos / Videos
                SpatieMediaLibraryFileUpload::make('repaired_photos')
                    ->collection('repaired_photos')
                    ->multiple()
                    ->panelLayout('grid')
                    ->imagePreviewHeight('140')
                    ->reorderable()
                    ->required()
                    ->label('Repaired Photos / Videos (After Repair Proof)')
                    ->helperText('Upload clear photos/videos proving the physical work has been completed.')
                    ->openable()
                    ->downloadable()
                    ->previewable()
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'video/mp4', 'video/quicktime'])
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('issue_description')
            ->heading('On-Site Repair Execution Tracking')
            ->description('Update repair details and upload after-repair proof for all defect items.')
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

                TextColumn::make('photos_summary')
                    ->label('Evidence Photos')
                    ->html()
                    ->state(function (MaintenanceRequestItem $record) {
                        $beforeCount = $record->getMedia('issue_photos')->count();
                        $afterCount = $record->getMedia('repaired_photos')->count();

                        $beforeHtml = "<span style=\"display: inline-flex; align-items: center; gap: 3px; padding: 2px 8px; border-radius: 9999px; font-size: 11px; font-weight: 600; background: rgba(37, 99, 235, 0.1); color: #2563eb;\">📸 {$beforeCount} Before</span>";

                        $afterHtml = $afterCount > 0
                            ? "<span style=\"display: inline-flex; align-items: center; gap: 3px; padding: 2px 8px; border-radius: 9999px; font-size: 11px; font-weight: 600; background: rgba(5, 150, 105, 0.1); color: #059669;\">🛠 {$afterCount} After</span>"
                            : "<span style=\"display: inline-flex; align-items: center; gap: 3px; padding: 2px 8px; border-radius: 9999px; font-size: 11px; font-weight: 500; background: rgba(128, 128, 128, 0.1); color: #94a3b8;\">⏳ Pending</span>";

                        return "<div style=\"display: inline-flex; align-items: center; gap: 6px; cursor: pointer;\" title=\"Click to inspect before & after gallery\">{$beforeHtml}{$afterHtml}</div>";
                    })
                    ->action(
                        Action::make('viewEvidenceModal')
                            ->label('Inspect Evidence Photos')
                            ->modalHeading(fn (MaintenanceRequestItem $record) => 'Evidence & Proof: ' . ($record->issue_description ?: 'Defect Item'))
                            ->modalDescription(function (MaintenanceRequestItem $record) {
                                $target = 'General Property Area';
                                if ($record->itemable instanceof PropertyRoom) {
                                    $target = '🚪 Room: ' . ($record->itemable->custom_name ?: ($record->itemable->roomDefinition?->name ?? "Room #{$record->itemable->id}"));
                                } elseif ($record->itemable instanceof PropertyInventory) {
                                    $target = '📦 Inventory: ' . ($record->itemable->inventoryType?->name ?? "Item #{$record->itemable->id}");
                                } elseif ($record->itemable instanceof PropertyUtility) {
                                    $target = '⚡ Utility: ' . ($record->itemable->utilityType?->name ?? "Utility #{$record->itemable->id}");
                                }
                                return "Target: {$target} &bull; Click any thumbnail to launch fullscreen swipe lightbox.";
                            })
                            ->modalWidth('4xl')
                            ->modalSubmitAction(false)
                            ->modalCancelActionLabel('Close')
                            ->schema([
                                View::make('filament.forms.components.defect-before-after-gallery'),
                            ])
                    ),

                TextColumn::make('repair_action')
                    ->label('Resolution / Work Done')
                    ->placeholder('Pending Work Log')
                    ->limit(35),

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
            ->headerActions([])
            ->recordActions([
                EditAction::make('updateRepairWork')
                    ->label('Update Repair Work')
                    ->icon('heroicon-o-wrench')
                    ->color('primary')
                    ->modalHeading(fn (MaintenanceRequestItem $record) => 'Reported Defect: ' . ($record->issue_description ?: 'Defect Item'))
                    ->modalDescription(function (MaintenanceRequestItem $record) {
                        $target = 'General Property Area';
                        if ($record->itemable instanceof PropertyRoom) {
                            $target = '🚪 Room: ' . ($record->itemable->custom_name ?: ($record->itemable->roomDefinition?->name ?? "Room #{$record->itemable->id}"));
                        } elseif ($record->itemable instanceof PropertyInventory) {
                            $target = '📦 Inventory: ' . ($record->itemable->inventoryType?->name ?? "Item #{$record->itemable->id}");
                        } elseif ($record->itemable instanceof PropertyUtility) {
                            $target = '⚡ Utility: ' . ($record->itemable->utilityType?->name ?? "Utility #{$record->itemable->id}");
                        }
                        return "Target Location: {$target}";
                    })
                    ->modalWidth('3xl')
                    ->after(function (MaintenanceRequestItem $record, RelationManager $livewire) {
                        if ($record->hasMedia('repaired_photos')) {
                            $record->update(['status' => 'completed']);
                        }
                        $livewire->dispatch('$refresh');
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([]),
            ]);
    }
}
