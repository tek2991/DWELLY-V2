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

class ManageDeboardingAudit extends EditRecord
{
    use HasDeboardingWorkflowHeader;

    protected static string $resource = TenantDeboardingResource::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?string $navigationLabel = '2. Exit Audit';

    protected static ?string $title = 'Deboarding – Move-Out Verification Audit';

    protected function getHeaderActions(): array
    {
        /** @var TenantDeboarding $record */
        $record = $this->getRecord();
        $isCompleted = $record && $record->status === DeboardingStatus::COMPLETED;

        return [
            Action::make('triggerAudit')
                ->label('Re-trigger Move-Out Audit')
                ->icon('heroicon-o-clipboard-document-list')
                ->color('info')
                ->visible(fn () => ! $record->move_out_audit_id && ! $isCompleted)
                ->action(function () use ($record) {
                    $service = app(TenancyDeboardingService::class);
                    $audit = $service->triggerMoveOutAudit($record->tenancyAgreement, auth()->user());

                    Notification::make()
                        ->title('Move-Out Audit Triggered')
                        ->body("Audit #{$audit->audit_number} created for exit inspection.")
                        ->success()
                        ->send();

                    $this->fillForm();
                }),
        ];
    }

    public function form(Schema $schema): Schema
    {
        return TenantDeboardingForm::configureAuditForm($schema);
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
