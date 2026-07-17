<?php

namespace App\Livewire\Operations;

use App\Domain\Audit\Models\AuditEvidence;
use App\Domain\Audit\Services\EvidenceService;
use Livewire\Component;

class EvidenceAnnotationEditor extends Component
{
    public AuditEvidence $evidence;
    public string $imageUrl;
    public ?array $annotationJson;

    public function mount(AuditEvidence $evidence)
    {
        $this->evidence = $evidence;
        $media = $evidence->getFirstMedia('images');
        $this->imageUrl = $media ? $media->getUrl() : '';
        $this->annotationJson = $evidence->annotation_json;
    }

    public function saveAnnotation(array $fabricJson, EvidenceService $service)
    {
        $service->saveAnnotation($this->evidence, $fabricJson);
        $this->dispatch('annotation-saved')->to(\App\Livewire\Operations\AuditInspectionComponent::class);
    }

    public function closeEditor()
    {
        $this->dispatch('annotation-saved')->to(\App\Livewire\Operations\AuditInspectionComponent::class);
    }

    public function render()
    {
        return view('livewire.operations.evidence-annotation-editor');
    }
}
