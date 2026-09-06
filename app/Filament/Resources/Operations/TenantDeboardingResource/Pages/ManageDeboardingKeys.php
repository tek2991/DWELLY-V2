<?php

namespace App\Filament\Resources\Operations\TenantDeboardingResource\Pages;

use App\Domain\Agreement\Enums\DeboardingStatus;
use App\Filament\Resources\Operations\TenantDeboardingResource;
use App\Filament\Resources\Operations\TenantDeboardingResource\Pages\Concerns\HasDeboardingWorkflowHeader;
use App\Filament\Resources\Operations\TenantDeboardingResource\Schemas\TenantDeboardingForm;
use BackedEnum;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ManageDeboardingKeys extends EditRecord
{
    use HasDeboardingWorkflowHeader;

    protected static string $resource = TenantDeboardingResource::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static ?string $navigationLabel = '4. Key Return';

    protected static ?string $title = 'Deboarding – Key Handover';

    public static function canAccess(array $parameters = []): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        if ($user->roles->isEmpty()) {
            return true;
        }

        if ($user->hasAnyRole(['Supply Manager', 'Demand Manager']) && ! $user->hasAnyRole(['Business Owner', 'City Manager', 'Operations Manager'])) {
            return false;
        }

        return $user->can('deboarding.keys.return')
            || $user->hasAnyRole(['Business Owner', 'City Manager', 'Operations Manager', 'Operations Executive', 'Accountant']);
    }

    public function form(Schema $schema): Schema
    {
        return TenantDeboardingForm::configureKeysForm($schema);
    }

    protected function getFormActions(): array
    {
        if ($this->getRecord()?->status === DeboardingStatus::COMPLETED) {
            return [];
        }

        return parent::getFormActions();
    }

    protected function getRedirectUrl(): ?string
    {
        return null;
    }
}
