<?php

namespace App\Domain\Audit\DTOs;

use App\Domain\Audit\Enums\EvidenceStatus;
use App\Domain\Audit\Models\AuditEvidence;

class EvidenceDTO
{
    public function __construct(
        public readonly string $id,
        public readonly string $imageUrl,
        public readonly ?array $annotationJson,
        public readonly ?string $caption,
        public readonly EvidenceStatus $status,
    ) {
    }

    public static function fromModel(AuditEvidence $evidence): self
    {
        $media = $evidence->getFirstMedia('images');

        return new self(
            id: $evidence->id,
            imageUrl: $media ? $media->getUrl() : '',
            annotationJson: $evidence->annotation_json,
            caption: $evidence->caption,
            status: $evidence->status,
        );
    }
}
