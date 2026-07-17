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
        
        if ($media) {
            $url = $media->getUrl();
            $appUrl = rtrim(config('app.url'), '/');
            
            // Convert to relative URL if it's served from the app's domain
            // This prevents port mismatch issues in local development (e.g. localhost vs localhost:8000)
            if (str_starts_with($url, $appUrl)) {
                $url = substr($url, strlen($appUrl));
                if (!str_starts_with($url, '/')) {
                    $url = '/' . $url;
                }
            }
            
            $this->imageUrl = $url;
        } else {
            $this->imageUrl = '';
        }
        
        $this->annotationJson = $evidence->annotation_json;
    }

    public function saveAnnotation(array $fabricJson, EvidenceService $service)
    {
        $service->saveAnnotation($this->evidence, $fabricJson);
        $this->dispatch('annotation-saved');
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
