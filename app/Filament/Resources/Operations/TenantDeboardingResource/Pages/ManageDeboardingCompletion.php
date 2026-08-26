<?php

namespace App\Filament\Resources\Operations\TenantDeboardingResource\Pages;

use App\Domain\Agreement\Enums\DeboardingStatus;
use App\Domain\Agreement\Models\TenantDeboarding;
use App\Domain\Agreement\Services\TenancyDeboardingService;
use App\Domain\Audit\Enums\AuditStatus;
use App\Filament\Resources\Operations\TenantDeboardingResource;
use App\Filament\Resources\Operations\TenantDeboardingResource\Pages\Concerns\HasDeboardingWorkflowHeader;
use App\Filament\Resources\Operations\TenantDeboardingResource\Schemas\TenantDeboardingForm;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ManageDeboardingCompletion extends EditRecord
{
    use HasDeboardingWorkflowHeader;

    protected static string $resource = TenantDeboardingResource::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCheckCircle;

    protected static ?string $navigationLabel = '6. Final Handover & Vacate';

    protected static ?string $title = 'Deboarding – Final Handover & Property Release';

    protected function getHeaderActions(): array
    {
        /** @var TenantDeboarding $record */
        $record = $this->getRecord();
        $isCompleted = $record && $record->status === DeboardingStatus::COMPLETED;

        return [
            Action::make('completeDeboarding')
                ->label('Complete Deboarding & Vacate Property')
                ->icon('heroicon-o-check-badge')
                ->color('danger')
                ->disabled($isCompleted)
                ->modalHeading('Finalize Deboarding & Vacate Property')
                ->modalDescription('This will permanently lock the exit audit, finalize the security deposit settlement, mark the tenancy agreement as Vacated, and update property status.')
                ->form([
                    Select::make('new_property_status')
                        ->label('Destination Property Status')
                        ->options([
                            'vacant' => 'Vacant & Ready for Onboarding',
                            'under_maintenance' => 'Under Maintenance / Further Repairs Needed',
                        ])
                        ->default(fn () => $record->new_property_status ?? 'vacant')
                        ->required(),

                    TextInput::make('net_refund')
                        ->label('Net Deposit Refundable Amount (₹)')
                        ->numeric()
                        ->prefix('₹')
                        ->default(fn () => $record->net_deposit_refund ?? 0.00)
                        ->required(),

                    Select::make('settlement_status')
                        ->label('Security Deposit Settlement Status')
                        ->options([
                            'refunded' => 'Refunded to Tenant',
                            'settled' => 'Fully Settled & Closed',
                            'balance_due' => 'Balance Due from Tenant',
                            'pending' => 'Pending Settlement',
                        ])
                        ->default('settled')
                        ->required(),

                    Toggle::make('record_accounting_transaction')
                        ->label('Post Double-Entry Journal Transaction to Accounting Ledger')
                        ->default(true)
                        ->helperText('Creates journal entries deducting repair costs and refunding deposit balance.'),
                ])
                ->action(function (array $data) use ($record) {
                    if ($record->moveOutAudit) {
                        $auditStatus = $record->moveOutAudit->status;
                        $statusVal = $auditStatus instanceof AuditStatus ? $auditStatus->value : (string) $auditStatus;
                        if (! in_array($statusVal, ['approved', 'completed'])) {
                            Notification::make()
                                ->title('Exit Audit Not Approved')
                                ->body('The Move-Out Verification Audit must be reviewed and approved before completing deboarding.')
                                ->warning()
                                ->persistent()
                                ->send();

                            return;
                        }
                    }

                    if (! $record->keys_returned) {
                        $record->update([
                            'keys_returned' => true,
                            'keys_returned_at' => now(),
                        ]);
                    }

                    $service = app(TenancyDeboardingService::class);
                    $service->completeDeboardingAndVacate(
                        $record,
                        $data['new_property_status'] ?? 'vacant',
                        [
                            'net_refund' => $data['net_refund'] ?? $record->net_deposit_refund,
                            'settlement_status' => $data['settlement_status'] ?? 'settled',
                            'record_accounting_transaction' => $data['record_accounting_transaction'] ?? false,
                        ],
                        auth()->user()
                    );

                    Notification::make()
                        ->title('Tenant Deboarding Completed')
                        ->body("Deboarding #{$record->code} completed. Property set to {$data['new_property_status']}.")
                        ->success()
                        ->send();

                    $this->fillForm();
                }),
        ];
    }

    public function form(Schema $schema): Schema
    {
        return TenantDeboardingForm::configureCompletionForm($schema);
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
