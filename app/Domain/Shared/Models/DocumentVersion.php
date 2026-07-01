<?php

namespace App\Domain\Shared\Models;

use Illuminate\Database\Eloquent\Relations\MorphTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class DocumentVersion extends DomainModel implements HasMedia
{
    use InteractsWithMedia;

    protected $table = 'document_versions';

    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }
}
