<?php

namespace App\Livewire\Operations;

use App\Domain\Audit\Models\Audit;
use App\Domain\Audit\Models\AuditCategory;
use App\Domain\Audit\Models\AuditItem;
use App\Domain\Audit\Enums\ItemCondition;
use App\Domain\Audit\Enums\ItemStatus;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Illuminate\Support\Facades\Log;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Actions\Action;
use Livewire\WithFileUploads;
use Livewire\Component;

class AuditInspectionComponent extends Component implements HasForms, HasActions
{
    use InteractsWithForms;
    use InteractsWithActions;
    use WithFileUploads;

    public Audit $audit;
    public $referenceItems = [];
    public $activeCategoryId = null;

    public function mount(Audit $audit)
    {
        $this->audit = $audit->load(['categories.items.source']);
        if ($this->audit->categories->isNotEmpty()) {
            $this->activeCategoryId = $this->audit->categories->first()->id;
        }

        if ($this->audit->reference_audit_id) {
            // Load the previous conditions to display side-by-side
            $referenceAudit = Audit::with('items')->find($this->audit->reference_audit_id);
            if ($referenceAudit) {
                foreach ($referenceAudit->items as $refItem) {
                    $key = $refItem->source_type . '_' . $refItem->source_id;
                    $this->referenceItems[$key] = $refItem->condition?->getLabel();
                }
            }
        }
    }

    public function setActiveCategory($categoryId)
    {
        $this->activeCategoryId = $categoryId;
    }

    public function createRoomAction(): Action
    {
        return Action::make('createRoom')
            ->label('Add Room')
            ->icon('heroicon-o-plus')
            ->button()
            ->modalHeading('Add Room (Staged for Audit)')
            ->form([
                Select::make('room_type_id')
                    ->label('Room Type')
                    ->options(\App\Domain\Property\Models\RoomType::query()->pluck('name', 'id'))
                    ->live()
                    ->required(),
                Select::make('room_definition_id')
                    ->label('Room Definition')
                    ->options(function (callable $get) {
                        $typeId = $get('room_type_id');
                        if (!$typeId) return [];
                        return \App\Domain\Property\Models\RoomDefinition::query()
                            ->where('room_type_id', $typeId)
                            ->pluck('name', 'id');
                    })
                    ->searchable()
                    ->preload()
                    ->required()
                    ->visible(fn (callable $get) => filled($get('room_type_id'))),
                Select::make('condition')
                    ->label('Initial Condition')
                    ->options(ItemCondition::class)
                    ->required(),
            ])
            ->action(function (array $data) {
                $category = AuditCategory::firstOrCreate(
                    ['audit_id' => $this->audit->id, 'name' => 'Rooms'],
                    ['sort_order' => 10]
                );

                $roomDef = \App\Domain\Property\Models\RoomDefinition::find($data['room_definition_id']);
                $displayName = $roomDef?->name ?? 'Room';

                $item = AuditItem::create([
                    'audit_category_id' => $category->id,
                    'name' => $displayName,
                    'source_type' => null,
                    'source_id' => null,
                    'status' => ItemStatus::INSPECTED,
                    'condition' => $data['condition'],
                    'remarks' => null,
                    'snapshot_data' => [
                        'is_new' => true,
                        'staged_type' => 'room',
                        'room_type_id' => $data['room_type_id'],
                        'room_definition_id' => $data['room_definition_id'],
                        'room_definition' => $displayName,
                        'display_name' => $displayName,
                    ],
                ]);

                $item->revisions()->create([
                    'updated_by_id' => auth()->id(),
                    'snapshot_data' => [
                        'condition' => $data['condition'],
                        'remarks' => null,
                    ],
                ]);

                activity()
                    ->performedOn($item)
                    ->log('Added room during audit inspection: ' . $displayName);

                $this->activeCategoryId = $category->id;
                $this->audit->load('categories.items');
                \Filament\Notifications\Notification::make()->title('Room added to audit staging')->success()->send();
            });
    }

    public function createInventoryAction(): Action
    {
        return Action::make('createInventory')
            ->label('Add Inventory Item')
            ->icon('heroicon-o-plus')
            ->button()
            ->modalHeading('Add Inventory Item (Staged for Audit)')
            ->form([
                Select::make('property_room_id')
                    ->label('Room')
                    ->options(function () {
                        $options = [];
                        if ($this->audit->property) {
                            foreach ($this->audit->property->rooms as $room) {
                                $label = $room->custom_name ?: ($room->roomDefinition?->name ?? 'Room ' . $room->id);
                                $options['existing_' . $room->id] = $label;
                            }
                        }
                        foreach ($this->audit->items as $item) {
                            if (($item->snapshot_data['staged_type'] ?? null) === 'room') {
                                $options['staged_' . $item->id] = ($item->snapshot_data['display_name'] ?? $item->name) . ' (Staged Room)';
                            }
                        }
                        return $options;
                    })
                    ->nullable()
                    ->searchable(),
                Select::make('inventory_type_id')
                    ->label('Inventory Type')
                    ->options(\App\Domain\Property\Models\InventoryType::query()->pluck('name', 'id'))
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('count')
                    ->label('Quantity / Count')
                    ->numeric()
                    ->default(1)
                    ->minValue(1)
                    ->required(),
                Select::make('condition')
                    ->label('Initial Condition')
                    ->options(ItemCondition::class)
                    ->required(),
            ])
            ->action(function (array $data) {
                $category = AuditCategory::firstOrCreate(
                    ['audit_id' => $this->audit->id, 'name' => 'Inventory'],
                    ['sort_order' => 20]
                );

                $invType = \App\Domain\Property\Models\InventoryType::find($data['inventory_type_id']);
                $typeName = $invType?->name ?? 'Item';

                $roomLabel = null;
                $realRoomId = null;
                $stagedRoomId = null;
                if (!empty($data['property_room_id'])) {
                    $val = $data['property_room_id'];
                    if (str_starts_with($val, 'existing_')) {
                        $realRoomId = substr($val, 9);
                        $room = \App\Domain\Property\Models\PropertyRoom::find($realRoomId);
                        $roomLabel = $room?->custom_name ?: ($room?->roomDefinition?->name ?? 'Room');
                    } elseif (str_starts_with($val, 'staged_')) {
                        $stagedRoomId = substr($val, 7);
                        $stagedItem = AuditItem::find($stagedRoomId);
                        $roomLabel = $stagedItem?->name ?? 'Staged Room';
                    }
                }

                $displayName = $typeName . ($roomLabel ? ' (' . $roomLabel . ')' : '');

                $item = AuditItem::create([
                    'audit_category_id' => $category->id,
                    'name' => $displayName,
                    'source_type' => null,
                    'source_id' => null,
                    'status' => ItemStatus::INSPECTED,
                    'condition' => $data['condition'],
                    'remarks' => null,
                    'snapshot_data' => [
                        'is_new' => true,
                        'staged_type' => 'inventory',
                        'inventory_type_id' => $data['inventory_type_id'],
                        'inventory_type' => $typeName,
                        'property_room_id' => $realRoomId,
                        'staged_room_item_id' => $stagedRoomId,
                        'room_name' => $roomLabel,
                        'count' => (int) $data['count'],
                        'display_name' => $displayName,
                    ],
                ]);

                $item->revisions()->create([
                    'updated_by_id' => auth()->id(),
                    'snapshot_data' => [
                        'condition' => $data['condition'],
                        'remarks' => null,
                    ],
                ]);

                activity()
                    ->performedOn($item)
                    ->log('Added inventory item during audit inspection: ' . $displayName);

                $this->activeCategoryId = $category->id;
                $this->audit->load('categories.items');
                \Filament\Notifications\Notification::make()->title('Inventory item added to audit staging')->success()->send();
            });
    }

    public function createUtilityAction(): Action
    {
        return Action::make('createUtility')
            ->label('Add Utility')
            ->icon('heroicon-o-plus')
            ->button()
            ->modalHeading('Add Utility Configuration (Staged for Audit)')
            ->form([
                Select::make('utility_type_id')
                    ->label('Utility Type')
                    ->options(\App\Domain\Property\Models\UtilityType::query()->pluck('name', 'id'))
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('paid_by')
                    ->label('Paid By')
                    ->options([
                        'owner' => 'Owner',
                        'tenant' => 'Tenant',
                        'dwelly' => 'Dwelly',
                    ])
                    ->required(),
                Select::make('condition')
                    ->label('Initial Condition')
                    ->options(ItemCondition::class)
                    ->required(),
            ])
            ->action(function (array $data) {
                $category = AuditCategory::firstOrCreate(
                    ['audit_id' => $this->audit->id, 'name' => 'Utilities'],
                    ['sort_order' => 30]
                );

                $utilityType = \App\Domain\Property\Models\UtilityType::find($data['utility_type_id']);
                $typeName = $utilityType?->name ?? 'Utility';

                $item = AuditItem::create([
                    'audit_category_id' => $category->id,
                    'name' => $typeName,
                    'source_type' => null,
                    'source_id' => null,
                    'status' => ItemStatus::INSPECTED,
                    'condition' => $data['condition'],
                    'remarks' => null,
                    'snapshot_data' => [
                        'is_new' => true,
                        'staged_type' => 'utility',
                        'utility_type_id' => $data['utility_type_id'],
                        'utility_type' => $typeName,
                        'paid_by' => $data['paid_by'],
                        'display_name' => $typeName,
                    ],
                ]);

                $item->revisions()->create([
                    'updated_by_id' => auth()->id(),
                    'snapshot_data' => [
                        'condition' => $data['condition'],
                        'remarks' => null,
                    ],
                ]);

                activity()
                    ->performedOn($item)
                    ->log('Added utility during audit inspection: ' . $typeName);

                $this->activeCategoryId = $category->id;
                $this->audit->load('categories.items');
                \Filament\Notifications\Notification::make()->title('Utility added to audit staging')->success()->send();
            });
    }

    public function createItemAction(): Action
    {
        return Action::make('createItem')
            ->label('Add New Item')
            ->icon('heroicon-o-plus')
            ->button()
            ->modalHeading('Add Found Item')
            ->form([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Select::make('condition')
                    ->options(ItemCondition::class)
                    ->required(),
                Textarea::make('remarks')
                    ->maxLength(65535),
            ])
            ->action(function (array $data) {
                if (!$this->activeCategoryId) return;

                $item = AuditItem::create([
                    'audit_category_id' => $this->activeCategoryId,
                    'name' => $data['name'],
                    'status' => ItemStatus::INSPECTED,
                    'condition' => $data['condition'],
                    'remarks' => $data['remarks'],
                    'snapshot_data' => [
                        'is_new' => true,
                    ],
                ]);

                // Create initial revision
                $item->revisions()->create([
                    'updated_by_id' => auth()->id(),
                    'snapshot_data' => [
                        'condition' => $data['condition'],
                        'remarks' => $data['remarks'],
                    ],
                ]);

                activity()
                    ->performedOn($item)
                    ->log('Added new item during inspection: ' . $item->name);

                $this->audit->load('categories.items');
            });
    }

    public function editItemAction(): Action
    {
        return \Filament\Actions\EditAction::make('editItem')
            ->label('Inspect')
            ->button()
            ->slideOver()
            ->modalHeading(fn (AuditItem $record) => 'Inspect: ' . $record->name)
            ->modalDescription(function (AuditItem $record) {
                $code = $this->audit->property->code ?? 'N/A';
                return 'Property Code: ' . $code;
            })
            ->record(function (array $arguments) {
                $this->currentItemId = $arguments['item_id'];
                return AuditItem::find($arguments['item_id']);
            })
            ->form([
                \Filament\Schemas\Components\Section::make('Inspection Details')
                    ->schema([
                        Select::make('condition')
                            ->options(ItemCondition::class)
                            ->required()
                            ->disabled(fn (AuditItem $record) => !$record->isEditable()),
                        Textarea::make('remarks')
                            ->maxLength(65535)
                            ->disabled(fn (AuditItem $record) => !$record->isEditable()),
                    ]),
                \Filament\Schemas\Components\Section::make('Evidence')
                    ->heading(function (AuditItem $record) {
                        $count = $this->currentItemId ? \App\Domain\Audit\Models\AuditItem::find($this->currentItemId)?->evidence()->count() ?? 0 : 0;
                        return new \Illuminate\Support\HtmlString(view('livewire.operations.evidence-section-heading', ['count' => $count, 'isEditable' => $record->isEditable()])->render());
                    })
                    ->schema([
                        \Filament\Schemas\Components\View::make('livewire.operations.evidence-gallery-form-field')
                    ])
            ])
            ->modalSubmitAction(fn ($action, AuditItem $record) => $record->isEditable() ? $action : $action->hidden())
            ->using(function (AuditItem $record, array $data, Action $action): AuditItem {
                if (!$record->isEditable()) {
                    return $record;
                }

                $data['status'] = ItemStatus::INSPECTED;
                $record->update($data);

                // Log revision
                $record->revisions()->create([
                    'updated_by_id' => auth()->id(),
                    'snapshot_data' => [
                        'condition' => $data['condition'] ?? null,
                        'remarks' => $data['remarks'] ?? null,
                        'evidence_count' => $record->evidence()->count(),
                    ],
                ]);

                activity()
                    ->performedOn($record)
                    ->log('Inspection: ' . $record->name . ' updated');

                \Filament\Notifications\Notification::make()
                    ->title('Inspection details saved')
                    ->success()
                    ->send();

                $this->audit->load('categories.items');

                $action->halt();

                return $record;
            })
            ->after(function () {
                $this->audit->load('categories.items');
            });
    }

    public ?string $editingEvidenceId = null;
    public ?string $currentItemId = null;
    public $uploads = [];

    public function updatedUploads()
    {
        $this->uploadEvidence();
    }

    public function uploadEvidence()
    {
        // called from the modal view via wire:click after file selection
        if (empty($this->uploads) || !$this->currentItemId) return;

        $item = AuditItem::find($this->currentItemId);
        if (!$item || !$item->isEditable()) {
            $this->uploads = [];
            return;
        }

        $service = app(\App\Domain\Audit\Services\EvidenceService::class);
        $dtos = $service->createFromUpload($item, $this->uploads);
        $this->uploads = [];

        if ($dtos->isNotEmpty()) {
            $this->openEditor($dtos->first()->id);
        }
    }

    public function openEditor(string $evidenceId)
    {
        Log::info('openEditor called: ' . $evidenceId);
        $this->editingEvidenceId = $evidenceId;
        
        // Close the evidence gallery modal since we're going full-screen
        $this->unmountAction(false);
    }

    public function deleteEvidence(string $evidenceId)
    {
        $evidence = \App\Domain\Audit\Models\AuditEvidence::with('auditItem')->find($evidenceId);
        if ($evidence && $evidence->auditItem?->isEditable()) {
            $service = app(\App\Domain\Audit\Services\EvidenceService::class);
            $service->deleteEvidence($evidence);
        }
    }

    #[Livewire\Attributes\On('annotation-saved')]
    public function closeEditor()
    {
        $this->editingEvidenceId = null;
    }

    public function render()
    {
        return view('livewire.operations.audit-inspection-component');
    }
}
