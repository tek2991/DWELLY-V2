<?php

namespace App\Livewire\Operations;

use App\Domain\Audit\Models\AuditEvidence;
use App\Domain\Audit\Services\EvidenceService;
use Livewire\Component;

class AnnotoriousEditor extends Component
{
    public AuditEvidence $evidence;
    public string $imageUrl;
    public ?array $annotationData;

    public function mount(AuditEvidence $evidence)
    {
        $this->evidence = $evidence;
        $media = $evidence->getFirstMedia('images');
        
        if ($media) {
            $url = $media->getUrl();
            $appUrl = rtrim(config('app.url'), '/');
            
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
        
        // We might store annotorious data differently, but let's reuse annotation_json for now
        $this->annotationData = is_array($evidence->annotation_json) && isset($evidence->annotation_json['annotorious']) 
            ? $evidence->annotation_json['annotorious'] 
            : [];
    }

    public function saveAnnotation(array $annotoriousJson, EvidenceService $service)
    {
        // For testing, let's just save it in the annotation_json under a special key,
        // or just replace it. Let's wrap it in an annotorious key to not break fabric
        $data = $this->evidence->annotation_json ?? [];
        $data['annotorious'] = $annotoriousJson;
        
        $this->evidence->update([
            'annotation_json' => $data,
            'status' => \App\Domain\Audit\Enums\EvidenceStatus::ANNOTATED,
        ]);
        
        $this->dispatch('annotation-saved')->to(\App\Livewire\Operations\AuditInspectionComponent::class);
    }

    public function closeEditor()
    {
        $this->dispatch('annotation-saved')->to(\App\Livewire\Operations\AuditInspectionComponent::class);
    }

    public function render()
    {
        return view('livewire.operations.annotorious-editor');
    }
}
