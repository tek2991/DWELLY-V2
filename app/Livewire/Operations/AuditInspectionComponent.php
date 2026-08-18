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
    public ?string $referenceAuditNumber = null;
    public $activeCategoryId = null;

    public function mount(Audit $audit)
    {
        $this->audit = $audit->load(['media', 'categories.items.evidence']);
        if ($this->audit->categories->isNotEmpty()) {
            $this->activeCategoryId = $this->audit->categories->first()->id;
        }
        $this->loadReferenceItems();
    }

    public function loadReferenceItems(): void
    {
        $this->referenceItems = [];
        $this->referenceAuditNumber = null;

        if ($this->audit->reference_audit_id) {
            $referenceAudit = Audit::with(['items.evidence.media'])->find($this->audit->reference_audit_id);
            if ($referenceAudit) {
                $this->referenceAuditNumber = $referenceAudit->audit_number ?? $referenceAudit->id;

                foreach ($referenceAudit->items as $refItem) {
                    $key = ($refItem->source_type && $refItem->source_id)
                        ? ($refItem->source_type . '_' . $refItem->source_id)
                        : ('name_' . mb_strtolower(trim($refItem->name)));

                    $evidenceList = [];
                    foreach ($refItem->evidence as $ev) {
                        $media = $ev->getFirstMedia('images');
                        $url = '';
                        if ($media) {
                            $url = $media->getUrl();
                            $appUrl = rtrim(config('app.url'), '/');
                            if (str_starts_with($url, $appUrl)) {
                                $url = substr($url, strlen($appUrl));
                                if (!str_starts_with($url, '/')) {
                                    $url = '/' . $url;
                                }
                            }
                        } else {
                            $url = $ev->getFirstMediaUrl();
                        }

                        if ($url) {
                            $hasAnnotations = !empty($ev->annotation_json) 
                                && isset($ev->annotation_json['canvas']['objects']) 
                                && count($ev->annotation_json['canvas']['objects']) > 0;

                            $evidenceList[] = [
                                'id' => $ev->id,
                                'url' => $url,
                                'annotation_json' => $ev->annotation_json,
                                'has_annotations' => $hasAnnotations,
                            ];
                        }
                    }

                    $this->referenceItems[$key] = [
                        'id' => $refItem->id,
                        'name' => $refItem->name,
                        'condition' => $refItem->condition ? $refItem->condition->getLabel() : 'Not Set',
                        'condition_color' => $refItem->condition?->getColor() ?? 'gray',
                        'remarks' => $refItem->remarks,
                        'evidence' => $evidenceList,
                    ];
                }
            }
        }
    }

    public function setActiveCategory($categoryId)
    {
        $this->activeCategoryId = $categoryId;
    }

    public function isAuditEditable(): bool
    {
        return !$this->audit->is_locked 
            && in_array($this->audit->status, [
                \App\Domain\Audit\Enums\AuditStatus::IN_PROGRESS,
                \App\Domain\Audit\Enums\AuditStatus::PARTIALLY_APPROVED
            ]) 
            && $this->audit->isInspector();
    }






    public function startAuditAction(): Action
    {
        return Action::make('startAudit')
            ->label('Start Audit')
            ->icon('heroicon-o-play')
            ->color('info')
            ->button()
            ->visible(fn () => $this->audit->canStart())
            ->action(function () {
                if (!$this->audit->canStart()) {
                    \Filament\Notifications\Notification::make()
                        ->title('Unauthorized')
                        ->body('Only the assigned inspector can start this audit.')
                        ->danger()
                        ->send();
                    return;
                }
                $this->audit->update(['status' => \App\Domain\Audit\Enums\AuditStatus::IN_PROGRESS]);
                $this->audit->refresh();
                \Filament\Notifications\Notification::make()
                    ->title('Audit started successfully')
                    ->body('You can now inspect items and submit for review once all items are inspected.')
                    ->success()
                    ->send();
            });
    }

    public function submitForReviewAction(): Action
    {
        $pendingCount = $this->audit->items()->where('status', \App\Domain\Audit\Enums\ItemStatus::PENDING)->count();
        $totalItems = $this->audit->items()->count();
        $requiresVideo = $this->audit->audit_type !== \App\Domain\Audit\Enums\AuditType::MAINTENANCE;
        $hasVideo = !$requiresVideo || ($this->audit->getFirstMedia('layout_video') !== null);
        $isComplete = ($pendingCount === 0) && ($totalItems > 0) && $hasVideo;

        $tooltip = null;
        if (!$isComplete) {
            if ($totalItems === 0) {
                $tooltip = 'No items in audit to inspect.';
            } elseif (!$hasVideo && $pendingCount > 0) {
                $tooltip = "Property layout video must be uploaded and {$pendingCount} item(s) pending inspection.";
            } elseif (!$hasVideo) {
                $tooltip = 'Property layout video must be uploaded before submitting for approval.';
            } else {
                $tooltip = "All items must be inspected (100% progress) before submitting for approval. ({$pendingCount} item(s) pending)";
            }
        }

        return Action::make('submitForReview')
            ->label('Submit Audit for Approval')
            ->icon('heroicon-o-paper-airplane')
            ->color('success')
            ->button()
            ->disabled(!$isComplete)
            ->tooltip($tooltip)
            ->requiresConfirmation()
            ->modalHeading('Submit Audit for Review')
            ->modalDescription('Once submitted, you will not be able to edit items until the reviewer reviews them or requests changes.')
            ->visible(fn () => $this->audit->canSubmit())
            ->action(function () {
                $pendingCount = $this->audit->items()->where('status', \App\Domain\Audit\Enums\ItemStatus::PENDING)->count();
                $totalItems = $this->audit->items()->count();
                $requiresVideo = $this->audit->audit_type !== \App\Domain\Audit\Enums\AuditType::MAINTENANCE;
                $hasVideo = !$requiresVideo || ($this->audit->getFirstMedia('layout_video') !== null);

                if ($pendingCount > 0 || $totalItems === 0 || !$hasVideo) {
                    $msg = !$hasVideo 
                        ? "Property layout video must be uploaded before submitting for review." 
                        : "All audit items have to be inspected before submitting for review.";

                    \Filament\Notifications\Notification::make()
                        ->title('Cannot Submit Audit')
                        ->body($msg)
                        ->warning()
                        ->send();
                    return;
                }

                app(\App\Domain\Audit\Services\AuditReviewService::class)->submitForReview($this->audit);
                $this->audit->refresh();
                \Filament\Notifications\Notification::make()
                    ->title('Audit submitted for approval successfully.')
                    ->success()
                    ->send();
            });
    }

    public function createRoomAction(): Action
    {
        return Action::make('createRoom')
            ->label('Add Room')
            ->icon('heroicon-o-plus')
            ->button()
            ->visible(fn () => $this->isAuditEditable())
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
            ->visible(fn () => $this->isAuditEditable())
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
            ->visible(fn () => $this->isAuditEditable())
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
            ->visible(fn () => $this->isAuditEditable())
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

    public function getCategoryItems(AuditItem $record)
    {
        $category = $this->audit->categories->firstWhere('id', $record->audit_category_id);
        if (!$category) {
            $category = AuditCategory::with('items')->find($record->audit_category_id);
        }

        return $category?->items ?? collect();
    }

    public function getPreviousItemId(AuditItem $record): ?string
    {
        $items = $this->getCategoryItems($record)->values();
        $currentIndex = $items->search(fn ($i) => (string)$i->id === (string)$record->id);

        if ($currentIndex !== false && $currentIndex > 0) {
            return (string) $items[$currentIndex - 1]->id;
        }

        return null;
    }

    public function getNextItemId(AuditItem $record): ?string
    {
        $items = $this->getCategoryItems($record)->values();
        $currentIndex = $items->search(fn ($i) => (string)$i->id === (string)$record->id);

        if ($currentIndex !== false && $currentIndex < $items->count() - 1) {
            return (string) $items[$currentIndex + 1]->id;
        }

        return null;
    }

    public function editItemAction(): Action
    {
        return \Filament\Actions\EditAction::make('editItem')
            ->label('Inspect')
            ->button()
            ->slideOver()
            ->modalHeading(function (?AuditItem $record = null) {
                $item = $this->currentItemId ? AuditItem::find($this->currentItemId) : $record;
                $items = $item ? $this->getCategoryItems($item)->values() : collect();
                $currentIndex = $item ? $items->search(fn ($i) => (string)$i->id === (string)$item->id) : false;
                $total = $items->count();

                $positionText = ($currentIndex !== false && $total > 0) ? ' (' . ($currentIndex + 1) . ' of ' . $total . ')' : '';

                return 'Inspect: ' . ($item?->name ?? '') . $positionText;
            })
            ->modalDescription(function (?AuditItem $record = null) {
                $code = $this->audit->property->code ?? 'N/A';
                return 'Property Code: ' . $code;
            })
            ->record(function (array $arguments) {
                $id = $arguments['item_id'] ?? $arguments['record'] ?? null;
                if ($id) {
                    $this->currentItemId = (string) $id;
                }
                return $this->currentItemId ? AuditItem::find($this->currentItemId) : null;
            })
            ->form([
                \Filament\Schemas\Components\Section::make('Reference Baseline')
                    ->icon('heroicon-o-document-duplicate')
                    ->collapsible()
                    ->visible(function (?AuditItem $record = null) {
                        $item = $this->currentItemId ? AuditItem::find($this->currentItemId) : $record;
                        if (!$item) return false;
                        $refKey = ($item->source_type && $item->source_id)
                            ? ($item->source_type . '_' . $item->source_id)
                            : ('name_' . mb_strtolower(trim($item->name)));
                        return !empty($this->referenceItems[$refKey]);
                    })
                    ->schema([
                        \Filament\Forms\Components\Placeholder::make('ref_baseline_info')
                            ->hiddenLabel()
                            ->content(function (?AuditItem $record = null) {
                                $item = $this->currentItemId ? AuditItem::find($this->currentItemId) : $record;
                                if (!$item) return 'N/A';
                                $refKey = ($item->source_type && $item->source_id)
                                    ? ($item->source_type . '_' . $item->source_id)
                                    : ('name_' . mb_strtolower(trim($item->name)));
                                $ref = $this->referenceItems[$refKey] ?? null;
                                if (!$ref) return 'No reference data';

                                $condLabel = e($ref['condition'] ?? 'N/A');
                                $remarksText = !empty($ref['remarks']) ? e($ref['remarks']) : '<span style="color: #9ca3af; font-style: italic;">No previous remarks</span>';

                                $photosHtml = '';
                                if (!empty($ref['evidence']) && count($ref['evidence']) > 0) {
                                    $count = count($ref['evidence']);
                                    $itemName = htmlspecialchars($item->name, ENT_QUOTES, 'UTF-8');

                                    $photosHtml = "<div style=\"margin-top: 0.625rem; border-top: 1px dashed #d1d5db; padding-top: 0.625rem;\">
                                        <div style=\"display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.375rem;\">
                                            <strong style=\"font-size: 0.8125rem; color: #374151;\">Previous Baseline Photos ({$count}):</strong>
                                        </div>
                                        <div style=\"display: flex; gap: 0.5rem; overflow-x: auto; padding-bottom: 0.25rem;\">";

                                    foreach ($ref['evidence'] as $photo) {
                                        $url = e($photo['url']);
                                        $jsonAnnotation = htmlspecialchars(json_encode($photo['annotation_json'] ?? null), ENT_QUOTES, 'UTF-8');
                                        $annotatedBadge = !empty($photo['has_annotations']) 
                                            ? '<span style="position: absolute; top: 3px; right: 3px; background: rgba(79, 70, 229, 0.95); color: white; padding: 2px 5px; border-radius: 4px; font-size: 10px; font-weight: 700; box-shadow: 0 1px 2px rgba(0,0,0,0.2);">🎨 Annotated</span>' 
                                            : '';

                                        $photosHtml .= '<div data-url="' . $url . '" data-json="' . $jsonAnnotation . '" data-item="' . $itemName . '" @click.stop="$dispatch(\'open-evidence-review-modal\', { imageUrl: $el.dataset.url, annotationJson: JSON.parse($el.dataset.json || \'null\'), itemName: $el.dataset.item + \' (Baseline)\' })" style="position: relative; flex-shrink: 0; cursor: pointer; border-radius: 0.375rem; overflow: hidden; border: 1px solid #d1d5db; transition: transform 0.15s ease;" onmouseover="this.style.transform=\'scale(1.03)\'" onmouseout="this.style.transform=\'scale(1)\'">';
                                        $photosHtml .= '<img src="' . $url . '" style="height: 80px; width: 110px; object-fit: cover; display: block;">';
                                        $photosHtml .= $annotatedBadge;
                                        $photosHtml .= '</div>';
                                    }

                                    $photosHtml .= '</div></div>';
                                }

                                return new \Illuminate\Support\HtmlString("
                                    <div style=\"display: flex; flex-direction: column; gap: 0.5rem; font-size: 0.875rem;\">
                                        <div style=\"display: flex; align-items: center; gap: 0.5rem;\">
                                            <span style=\"font-weight: 600; color: #4b5563;\">Previous Baseline Condition:</span>
                                            <span style=\"font-weight: 600; color: #1f2937; background: #e5e7eb; padding: 0.125rem 0.5rem; border-radius: 0.25rem;\">{$condLabel}</span>
                                        </div>
                                        <div>
                                            <span style=\"font-weight: 600; color: #4b5563;\">Previous Baseline Remarks:</span>
                                            <div style=\"margin-top: 0.25rem; background: #f3f4f6; padding: 0.5rem 0.75rem; border-radius: 0.375rem; border-left: 3px solid #6b7280; color: #374151;\">{$remarksText}</div>
                                        </div>
                                        {$photosHtml}
                                    </div>
                                ");
                            })
                    ]),
                \Filament\Schemas\Components\Section::make('Inspection Details')
                    ->schema([
                        Select::make('condition')
                            ->options(ItemCondition::class)
                            ->required()
                            ->disabled(function (?AuditItem $record = null) {
                                $item = $this->currentItemId ? AuditItem::find($this->currentItemId) : $record;
                                return !$item || !$item->isEditable();
                            }),
                        Textarea::make('remarks')
                            ->label('Fresh Inspection Remarks')
                            ->maxLength(65535)
                            ->disabled(function (?AuditItem $record = null) {
                                $item = $this->currentItemId ? AuditItem::find($this->currentItemId) : $record;
                                return !$item || !$item->isEditable();
                            }),
                    ]),
                \Filament\Schemas\Components\Section::make('Evidence')
                    ->heading(function (?AuditItem $record = null) {
                        $item = $this->currentItemId ? AuditItem::find($this->currentItemId) : $record;
                        $count = $item ? $item->evidence()->count() : 0;
                        $isEditable = $item ? $item->isEditable() : false;
                        return new \Illuminate\Support\HtmlString(view('livewire.operations.evidence-section-heading', ['count' => $count, 'isEditable' => $isEditable])->render());
                    })
                    ->schema([
                        \Filament\Schemas\Components\View::make('livewire.operations.evidence-gallery-form-field')
                    ])
            ])
            ->modalSubmitAction(function ($action, ?AuditItem $record = null) {
                $item = $this->currentItemId ? AuditItem::find($this->currentItemId) : $record;
                return ($item && $item->isEditable()) ? $action : $action->hidden();
            })
            ->extraModalFooterActions(function (?AuditItem $record = null) {
                return [
                    Action::make('prevItem')
                        ->label('Previous')
                        ->icon('heroicon-o-chevron-left')
                        ->color('gray')
                        ->button()
                        ->disabled(function () {
                            $item = $this->currentItemId ? AuditItem::find($this->currentItemId) : null;
                            return !$item || !$this->getPreviousItemId($item);
                        })
                        ->action(function (?AuditItem $record = null) {
                            $item = $this->currentItemId ? AuditItem::find($this->currentItemId) : $record;
                            if (!$item) return;

                            $prevId = $this->getPreviousItemId($item);
                            if (!$prevId) return;

                            $this->saveCurrentItemFormData();
                            $this->replaceMountedAction('editItem', ['item_id' => $prevId], ['seq' => microtime(true)]);
                        }),

                    Action::make('nextItem')
                        ->label('Next')
                        ->icon('heroicon-o-chevron-right')
                        ->iconPosition('after')
                        ->color('gray')
                        ->button()
                        ->disabled(function () {
                            $item = $this->currentItemId ? AuditItem::find($this->currentItemId) : null;
                            return !$item || !$this->getNextItemId($item);
                        })
                        ->action(function (?AuditItem $record = null) {
                            $item = $this->currentItemId ? AuditItem::find($this->currentItemId) : $record;
                            if (!$item) return;

                            $nextId = $this->getNextItemId($item);
                            if (!$nextId) return;

                            $this->saveCurrentItemFormData();
                            $this->replaceMountedAction('editItem', ['item_id' => $nextId], ['seq' => microtime(true)]);
                        }),
                ];
            })
            ->using(function (?AuditItem $record = null, array $data = [], ?Action $action = null): AuditItem {
                $record = $record ?? ($this->currentItemId ? AuditItem::find($this->currentItemId) : null);
                if (!$record || !$record->isEditable()) {
                    return $record ?? new AuditItem();
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

                if ($action) {
                    $action->halt();
                }

                return $record;
            })
            ->after(function () {
                $this->audit->load('categories.items');
            });
    }

    public ?string $editingEvidenceId = null;
    public ?string $currentItemId = null;
    public $uploads = [];
    public $videoUpload = null;

    public function saveCurrentItemFormData(): void
    {
        if (!$this->currentItemId) {
            return;
        }

        $item = AuditItem::find($this->currentItemId);
        if (!$item || !$item->isEditable()) {
            return;
        }

        $data = [];
        $schema = $this->getMountedActionSchema();
        if ($schema) {
            $rawState = $schema->getRawState();
            if (is_array($rawState)) {
                $data = $rawState;
            }
        }

        if (empty($data) && !empty($this->mountedActions)) {
            $lastIndex = array_key_last($this->mountedActions);
            $data = $this->mountedActions[$lastIndex]['data'] ?? [];
        }

        $updateData = [];
        $hasChanges = false;

        if (!empty($data['condition'])) {
            $newCondition = $data['condition'] instanceof ItemCondition ? $data['condition'] : ItemCondition::tryFrom($data['condition']);
            if ($item->condition !== $newCondition) {
                $updateData['condition'] = $data['condition'];
                $hasChanges = true;
            }
            if ($item->status !== ItemStatus::INSPECTED) {
                $updateData['status'] = ItemStatus::INSPECTED;
                $hasChanges = true;
            }
        }

        if (array_key_exists('remarks', $data)) {
            if ($item->remarks !== $data['remarks']) {
                $updateData['remarks'] = $data['remarks'];
                $hasChanges = true;
            }
        }

        if ($hasChanges && !empty($updateData)) {
            $item->update($updateData);

            $item->revisions()->create([
                'updated_by_id' => auth()->id(),
                'snapshot_data' => [
                    'condition' => $item->condition?->value ?? ($data['condition'] ?? null),
                    'remarks' => $item->remarks,
                    'evidence_count' => $item->evidence()->count(),
                ],
            ]);

            activity()
                ->performedOn($item)
                ->log('Inspection: ' . $item->name . ' auto-saved');

            $this->audit->load('categories.items');
        }
    }

    public function updatedVideoUpload()
    {
        if (!$this->videoUpload || !$this->isAuditEditable()) {
            $this->videoUpload = null;
            return;
        }

        $this->audit->addMedia($this->videoUpload->getRealPath())
            ->usingFileName($this->videoUpload->getClientOriginalName())
            ->toMediaCollection('layout_video');

        $this->videoUpload = null;
        $this->audit->refresh();

        \Filament\Notifications\Notification::make()
            ->title('Property layout video uploaded successfully.')
            ->success()
            ->send();
    }

    public function deleteLayoutVideo()
    {
        if (!$this->isAuditEditable()) {
            return;
        }

        $this->audit->clearMediaCollection('layout_video');
        $this->audit->refresh();

        \Filament\Notifications\Notification::make()
            ->title('Property layout video removed.')
            ->success()
            ->send();
    }

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

        $this->saveCurrentItemFormData();

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
        $evidence = \App\Domain\Audit\Models\AuditEvidence::with('auditItem')->find($evidenceId);
        if (!$evidence || !$evidence->auditItem?->isEditable()) {
            \Filament\Notifications\Notification::make()
                ->title('Cannot edit photo annotations')
                ->body('Annotations can only be modified for items that require changes.')
                ->warning()
                ->send();
            return;
        }

        $this->saveCurrentItemFormData();

        $this->editingEvidenceId = $evidenceId;
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
        $this->audit->load('categories.items');

        if ($this->currentItemId && AuditItem::where('id', $this->currentItemId)->exists()) {
            $this->dispatch('mount-edit-item', itemId: $this->currentItemId);
        }
    }

    public function mountEditItem(string $itemId)
    {
        $this->mountAction('editItem', ['item_id' => $itemId]);
    }

    public function render()
    {
        return view('livewire.operations.audit-inspection-component');
    }
}
