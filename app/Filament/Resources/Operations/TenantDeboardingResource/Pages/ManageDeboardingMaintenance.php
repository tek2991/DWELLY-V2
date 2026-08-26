<?php

namespace App\Filament\Resources\Operations\TenantDeboardingResource\Pages;

use App\Domain\Agreement\Enums\DeboardingStatus;
use App\Domain\Agreement\Models\TenantDeboarding;
use App\Domain\Agreement\Services\TenancyDeboardingService;
use App\Domain\Maintenance\Enums\MaintenancePriority;
use App\Domain\Maintenance\Enums\PayerType;
use App\Filament\Resources\Operations\TenantDeboardingResource;
use App\Filament\Resources\Operations\TenantDeboardingResource\Pages\Concerns\HasDeboardingWorkflowHeader;
use App\Filament\Resources\Operations\TenantDeboardingResource\Schemas\TenantDeboardingForm;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ManageDeboardingMaintenance extends EditRecord
{
    use HasDeboardingWorkflowHeader;

    protected static string $resource = TenantDeboardingResource::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;

    protected static ?string $navigationLabel = '3. Maintenance & Damages';

    protected static ?string $title = 'Deboarding – Maintenance & Repair Resolution';

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function form(Schema $schema): Schema
    {
        return TenantDeboardingForm::configureMaintenanceForm($schema);
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
