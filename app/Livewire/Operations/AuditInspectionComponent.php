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

    public function editItemAction(): Action
    {
        return \Filament\Actions\EditAction::make('editItem')
            ->label('Inspect')
            ->button()
            ->slideOver()
            ->record(fn (array $arguments) => AuditItem::find($arguments['item_id']))
            ->form([
                Select::make('condition')
                    ->options(ItemCondition::class)
                    ->required(),
                Textarea::make('remarks')
                    ->maxLength(65535),
            ])
            ->using(function (AuditItem $record, array $data): AuditItem {
                $data['status'] = ItemStatus::INSPECTED;
                $record->update($data);
                return $record;
            })
            ->after(function () {
                $this->audit->load('categories.items');
            });
    }

    public function evidenceAction(): Action
    {
        return Action::make('evidence')
            ->label('Evidence')
            ->modalHeading(fn (array $arguments) => 'Evidence: ' . (AuditItem::find($arguments['item_id'])?->name ?? ''))
            ->modalWidth('4xl')
            ->modalContent(function (array $arguments) {
                $item = AuditItem::find($arguments['item_id']);
                $this->currentItemId = $arguments['item_id'];
                $evidenceList = $item ? $item->evidence()->orderBy('display_order')->get() : collect();
                return view('livewire.operations.evidence-gallery-modal', [
                    'item' => $item,
                    'evidenceList' => $evidenceList,
                ]);
            })
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close');
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
        if (!$item) return;

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
        $evidence = \App\Domain\Audit\Models\AuditEvidence::find($evidenceId);
        if ($evidence) {
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
