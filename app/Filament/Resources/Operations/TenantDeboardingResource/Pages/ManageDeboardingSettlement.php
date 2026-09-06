<?php

namespace App\Filament\Resources\Operations\TenantDeboardingResource\Pages;

use App\Domain\Agreement\Enums\DeboardingStatus;
use App\Domain\Agreement\Models\TenantDeboarding;
use App\Domain\Agreement\Services\TenancyDeboardingService;
use App\Filament\Resources\Operations\TenantDeboardingResource;
use App\Filament\Resources\Operations\TenantDeboardingResource\Pages\Concerns\HasDeboardingWorkflowHeader;
use App\Filament\Resources\Operations\TenantDeboardingResource\Schemas\TenantDeboardingForm;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ManageDeboardingSettlement extends EditRecord
{
    use HasDeboardingWorkflowHeader;

    protected static string $resource = TenantDeboardingResource::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $navigationLabel = '5. Deposit Settlement';

    protected static ?string $title = 'Deboarding – Security Deposit Settlement & Refund';

    public static function canAccess(array $parameters = []): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        if ($user->roles->isEmpty()) {
            return true;
        }

        // Operations Executive, Demand Manager, and Supply Manager are strictly barred from Settlement!
        if ($user->hasAnyRole(['Operations Executive', 'Demand Manager', 'Supply Manager']) && ! $user->hasAnyRole(['Business Owner', 'City Manager', 'Operations Manager', 'Accountant'])) {
            return false;
        }

        return $user->can('deboarding.settlement.draft')
            || $user->can('deboarding.settlement.approve')
            || $user->hasAnyRole(['Business Owner', 'City Manager', 'Operations Manager', 'Accountant']);
    }

    protected function getHeaderActions(): array
    {
        /** @var TenantDeboarding $record */
        $record = $this->getRecord();
        $isCompleted = $record && $record->status === DeboardingStatus::COMPLETED;
        $canRecalculate = auth()->user()?->hasAnyRole(['Business Owner', 'City Manager', 'Operations Manager', 'Accountant']) || auth()->user()?->roles->isEmpty();

        return [
            Action::make('recalculateSettlement')
                ->label('Recalculate Dues & Settlement')
                ->icon('heroicon-o-calculator')
                ->color('gray')
                ->disabled($isCompleted)
                ->visible($canRecalculate)
                ->action(function () use ($record) {
                    $service = app(TenancyDeboardingService::class);
                    $result = $service->calculateSettlement($record);

                    Notification::make()
                        ->title('Settlement Recalculated')
                        ->body("Unpaid Rent: ₹{$result['unpaid_rent']} | Maintenance: ₹{$result['maintenance_deduction']} | Net Refund: ₹{$result['net_refund']}")
                        ->success()
                        ->send();

                    $this->fillForm();
                }),
        ];
    }

    public function form(Schema $schema): Schema
    {
        return TenantDeboardingForm::configureSettlementForm($schema);
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
