<?php

namespace App\Filament\Resources\Administration\UserResource\Pages;

use App\Domain\Auth\Enums\RoleName;
use App\Filament\Resources\Administration\UserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function afterCreate(): void
    {
        $creator = auth()->user();
        if ($creator && $creator->hasRole(RoleName::CITY_MANAGER) && ! $creator->hasRole(RoleName::BUSINESS_OWNER)) {
            // City Manager creates field staff: auto-assign Operations Executive role if no roles attached
            if ($this->record->roles()->count() === 0) {
                $this->record->assignRole(RoleName::OPERATIONS_EXECUTIVE);
            }
            // Auto-attach City Manager's branches if no branches selected
            if ($this->record->branches()->count() === 0) {
                $this->record->branches()->sync($creator->branches()->pluck('id'));
            }
        }
    }
}
