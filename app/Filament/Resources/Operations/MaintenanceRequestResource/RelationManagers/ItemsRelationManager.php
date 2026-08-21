<?php

namespace App\Filament\Resources\Operations\MaintenanceRequestResource\RelationManagers;

use App\Domain\Property\Models\PropertyInventory;
use App\Domain\Property\Models\PropertyRoom;
use App\Domain\Property\Models\PropertyUtility;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'Defect Items & Assessment';

    protected static \BackedEnum|string|null $icon = 'heroicon-o-clipboard-document-list';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Select::make('itemable_type')
                    ->label('Item Category')
                    ->placeholder('Select Category')
                    ->options([
                        'general' => '🏢 General Property Area',
                        PropertyRoom::class => '🚪 Room',
                        PropertyInventory::class => '📦 Inventory Item',
                        PropertyUtility::class => '⚡ Utility',
                    ])
                    ->default('general')
                    ->formatStateUsing(fn ($state) => $state ?? 'general')
                    ->dehydrateStateUsing(fn ($state) => $state === 'general' ? null : $state)
                    ->required()
                    ->live()
                    ->columnSpan(fn (Get $get) => ($get('itemable_type') === 'general' || blank($get('itemable_type'))) ? 2 : 1),

                Select::make('itemable_id')
                    ->label('Specific Item')
                    ->placeholder('Select Item')
                    ->options(function (Get $get, RelationManager $livewire) {
                        $propertyId = $livewire->getOwnerRecord()->property_id;
                        $type = $get('itemable_type');

                        if (!$propertyId || !$type || $type === 'general') {
                            return [];
                        }

                        if ($type === PropertyRoom::class) {
                            return PropertyRoom::where('property_id', $propertyId)
                                ->get()
                                ->mapWithKeys(fn ($r) => [
                                    $r->id => $r->custom_name ?: ($r->roomDefinition?->name ?? "Room #{$r->id}")
                                ]);
                        }

                        if ($type === PropertyInventory::class) {
                            return PropertyInventory::where('property_id', $propertyId)
                                ->get()
                                ->mapWithKeys(fn ($i) => [
                                    $i->id => ($i->inventoryType?->name ?? "Item #{$i->id}") . " (Qty: {$i->count})"
                                ]);
                        }

                        if ($type === PropertyUtility::class) {
                            return PropertyUtility::where('property_id', $propertyId)
                                ->get()
                                ->mapWithKeys(fn ($u) => [
                                    $u->id => ($u->utilityType?->name ?? "Utility #{$u->id}") . " (Paid by: {$u->paid_by})"
                                ]);
                        }

                        return [];
                    })
                    ->visible(fn (Get $get) => filled($get('itemable_type')) && $get('itemable_type') !== 'general')
                    ->required(fn (Get $get) => filled($get('itemable_type')) && $get('itemable_type') !== 'general')
                    ->dehydrateStateUsing(fn ($state, Get $get) => $get('itemable_type') === 'general' ? null : $state)
                    ->searchable()
                    ->columnSpan(1),

                Textarea::make('issue_description')
                    ->label('Specific Defect / Issue')
                    ->placeholder('Describe the damage, leak, or issue...')
                    ->rows(2)
                    ->required()
                    ->columnSpanFull(),

                TextInput::make('repair_action')
                    ->label('Action Required')
                    ->placeholder('e.g. Repair, Replace, Service')
                    ->required()
                    ->columnSpanFull(),

                SpatieMediaLibraryFileUpload::make('issue_photos')
                    ->collection('issue_photos')
                    ->multiple()
                    ->panelLayout('grid')
                    ->imagePreviewHeight('140')
                    ->reorderable()
                    ->required()
                    ->label('Defect Photos / Videos (Before Repair Proof)')
                    ->helperText('Upload clear photos/videos showing the reported defect or damage.')
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
            ->recordAction(null)
            ->recordUrl(null)
            ->heading('Defect Items & Initial Assessment')
            ->description(fn (RelationManager $livewire) => (bool) $livewire->getOwnerRecord()?->isLocked()
                ? '🔒 Defect items and initial assessment are locked because the maintenance quotation has been approved.'
                : 'Log all defect items and before-repair evidence required for assessment and quotations.')
            ->columns([
                TextColumn::make('item_name')
                    ->label('Target Item')
                    ->state(function ($record) {
                        if (!$record->itemable) {
                            return 'General Property Area';
                        }
                        if ($record->itemable instanceof PropertyRoom) {
                            return '🚪 Room: ' . ($record->itemable->custom_name ?: ($record->itemable->roomDefinition?->name ?? "Room #{$record->itemable->id}"));
                        }
                        if ($record->itemable instanceof PropertyInventory) {
                            return '📦 Inventory: ' . ($record->itemable->inventoryType?->name ?? "Item #{$record->itemable->id}");
                        }
                        if ($record->itemable instanceof PropertyUtility) {
                            return '⚡ Utility: ' . ($record->itemable->utilityType?->name ?? "Utility #{$record->itemable->id}");
                        }
                        return 'Item';
                    })
                    ->weight('bold')
                    ->searchable(),

                TextColumn::make('issue_description')
                    ->label('Defect Description')
                    ->limit(50)
                    ->searchable(),

                TextColumn::make('repair_action')
                    ->label('Action Required')
                    ->badge()
                    ->placeholder('Pending Triage'),

                TextColumn::make('photos_summary')
                    ->label('Defect Photos')
                    ->html()
                    ->state(function ($record) {
                        $count = $record->getMedia('issue_photos')->count();
                        if ($count === 0) {
                            return "<span style=\"display: inline-flex; align-items: center; gap: 3px; padding: 2px 8px; border-radius: 9999px; font-size: 11px; font-weight: 500; background: rgba(128, 128, 128, 0.1); color: #94a3b8;\">No Photos</span>";
                        }
                        return "<span style=\"display: inline-flex; align-items: center; gap: 3px; padding: 2px 8px; border-radius: 9999px; font-size: 11px; font-weight: 600; background: rgba(37, 99, 235, 0.1); color: #2563eb; cursor: pointer;\" title=\"Click to inspect photos\">📸 {$count} Photo" . ($count > 1 ? 's' : '') . "</span>";
                    })
                    ->action(
                        Action::make('viewDefectPhotosModal')
                            ->label('Inspect Defect Photos')
                            ->modalHeading(fn ($record) => 'Defect Photos: ' . ($record->issue_description ?: 'Defect Item'))
                            ->modalDescription(function ($record) {
                                $target = 'General Property Area';
                                if ($record->itemable instanceof PropertyRoom) {
                                    $target = '🚪 Room: ' . ($record->itemable->custom_name ?: ($record->itemable->roomDefinition?->name ?? "Room #{$record->itemable->id}"));
                                } elseif ($record->itemable instanceof PropertyInventory) {
                                    $target = '📦 Inventory: ' . ($record->itemable->inventoryType?->name ?? "Item #{$record->itemable->id}");
                                } elseif ($record->itemable instanceof PropertyUtility) {
                                    $target = '⚡ Utility: ' . ($record->itemable->utilityType?->name ?? "Utility #{$record->itemable->id}");
                                }
                                return "Target: {$target} &bull; Click any thumbnail to open fullscreen swipe lightbox.";
                            })
                            ->modalWidth('3xl')
                            ->modalSubmitAction(false)
                            ->modalCancelActionLabel('Close')
                            ->schema([
                                View::make('filament.forms.components.defect-photos-lightbox'),
                            ])
                    ),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Add Defect Item & Photos')
                    ->modalHeading('Log Defect Item & Attach Photos')
                    ->modalWidth('3xl')
                    ->visible(fn (RelationManager $livewire) => ! (bool) $livewire->getOwnerRecord()?->isLocked()),
            ])
            ->recordActions([
                EditAction::make()
                    ->label('Edit / Update Photos')
                    ->modalHeading('Edit Defect Item & Photos')
                    ->modalWidth('3xl')
                    ->visible(fn (RelationManager $livewire) => ! (bool) $livewire->getOwnerRecord()?->isLocked()),

                ViewAction::make()
                    ->label('View Defect Item & Photos')
                    ->modalHeading('View Defect Item & Photos')
                    ->modalWidth('3xl')
                    ->visible(fn (RelationManager $livewire) => (bool) $livewire->getOwnerRecord()?->isLocked()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([]),
            ]);
    }
}
