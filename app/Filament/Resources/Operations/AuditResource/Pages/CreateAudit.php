<?php

namespace App\Filament\Resources\Operations\AuditResource\Pages;

use App\Filament\Resources\Operations\AuditResource;
use App\Domain\Audit\Models\Audit;
use App\Domain\Audit\Enums\AuditStatus;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateAudit extends CreateRecord
{
    protected static string $resource = AuditResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Pre-selection of reference_audit_id is done in the form schema, 
        // but if they didn't pick one, maybe we can auto-assign the latest?
        // Actually, the form schema will do it if we implement default() logic,
        // but user specifically requested: "automatically pre-select... The user can change or clear this selection"
        // Let's implement that in the CreateAudit page using a lifecycle hook.
        
        return $data;
    }
}
