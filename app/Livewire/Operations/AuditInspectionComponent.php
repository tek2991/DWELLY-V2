<?php

namespace App\Livewire\Operations;

use App\Domain\Audit\Models\Audit;
use App\Domain\Audit\Models\AuditItem;
use App\Domain\Audit\Enums\ItemCondition;
use App\Domain\Audit\Enums\ItemStatus;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Components\Select;
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

    public function createItemAction(): Action
    {
        return Action::make('createItem')
            ->label('Add New Item')
            ->icon('heroicon-o-plus')
            ->button()
            ->modalHeading('Add Found Item')
            ->form([
                \Filament\Forms\Components\TextInput::make('name')
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
            ->using(function (AuditItem $record, array $data): AuditItem {
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
