<?php

namespace App\Domain\Audit\Services;

use App\Domain\Audit\DTOs\EvidenceDTO;
use App\Domain\Audit\Enums\EvidenceStatus;
use App\Domain\Audit\Models\AuditEvidence;
use App\Domain\Audit\Models\AuditItem;
use Illuminate\Support\Collection;

class EvidenceService
{
    /**
     * @param AuditItem $item
     * @param array $files Temporary file paths or UploadedFile instances
     * @return Collection<EvidenceDTO>
     */
    public function createFromUpload(AuditItem $item, array $files): Collection
    {
        $dtos = collect();

        foreach ($files as $file) {
            $evidence = new AuditEvidence();
            $evidence->audit_item_id = $item->id;
            $evidence->status = EvidenceStatus::PENDING;
            
            // Get max display order
            $maxOrder = $item->evidence()->max('display_order') ?? 0;
            $evidence->display_order = $maxOrder + 1;
            
            $evidence->save();

            $evidence->addMedia($file)->toMediaCollection('images');

            $dtos->push(EvidenceDTO::fromModel($evidence));
        }
        
        if (!empty($files)) {
            activity()->performedOn($item)->log('Inspection: Evidence added');
        }

        return $dtos;
    }

    public function saveAnnotation(AuditEvidence $evidence, array $fabricJson): EvidenceDTO
    {
        $evidence->annotation_json = [
            'version' => 1,
            'canvas' => $fabricJson,
        ];
        $evidence->status = EvidenceStatus::ANNOTATED;
        $evidence->save();
        
        activity()->performedOn($evidence->auditItem)->log('Inspection: Evidence annotated');

        return EvidenceDTO::fromModel($evidence);
    }

    public function deleteEvidence(AuditEvidence $evidence): void
    {
        $item = $evidence->auditItem;
        $evidence->clearMediaCollection('images');
        $evidence->delete();
        
        if ($item) {
            activity()->performedOn($item)->log('Inspection: Evidence deleted');
        }
    }

    public function reorder(array $orderedIds): void
    {
        foreach ($orderedIds as $index => $id) {
            AuditEvidence::where('id', $id)->update(['display_order' => $index + 1]);
        }
    }
}
