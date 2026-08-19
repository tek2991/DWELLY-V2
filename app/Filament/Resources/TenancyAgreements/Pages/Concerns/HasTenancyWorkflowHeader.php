<?php

namespace App\Filament\Resources\TenancyAgreements\Pages\Concerns;

use App\Domain\Agreement\Actions\ActivateTenancyAction;
use App\Domain\Agreement\Services\TenancyDeboardingService;
use App\Filament\Resources\TenancyAgreements\Schemas\TenancyAgreementForm;
use App\Filament\Resources\TenancyAgreements\TenancyAgreementResource;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;

trait HasTenancyWorkflowHeader
{
    public function getHeader(): ?View
    {
        $record = $this->getRecord();
        if (! $record) {
            return null;
        }

        return view('filament.resources.tenancy-agreements.header', [
            'breadcrumbs' => filament()->hasBreadcrumbs() ? $this->getBreadcrumbs() : [],
            'heading' => $this->getHeading(),
            'actions' => $this->getCachedHeaderActions(),
            'actionsAlignment' => $this->getHeaderActionsAlignment(),
            'record' => $record,
            'headerHtml' => TenancyAgreementForm::getWorkflowHeaderHtml($record),
        ]);
    }

    public function activateTenancy(): void
    {
        $record = $this->getRecord();

        if ($record->status === 'active') {
            Notification::make()
                ->title('Tenancy Already Active')
                ->body('This tenancy agreement is already active.')
                ->info()
                ->send();

            return;
        }

        $pending = TenancyAgreementForm::getPendingActivationRequirements($record);

        if (! empty($pending)) {
            $formatted = implode('<br>• ', $pending);
            Notification::make()
                ->title('Cannot Activate Tenancy')
                ->body('Please complete all onboarding requirements first:<br>• '.$formatted)
                ->danger()
                ->persistent()
                ->send();

            return;
        }

        try {
            app(ActivateTenancyAction::class)->execute($record, auth()->user());

            Notification::make()
                ->title('Tenancy Activated Successfully')
                ->body('Tenancy agreement is now active and property status set to occupied. The linked Move-In Audit is permanently locked.')
                ->success()
                ->send();

            $this->redirect(TenancyAgreementResource::getUrl('edit', ['record' => $record]));
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Activation Failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('initiateDeboardingHeader')
                ->label('Initiate Deboarding & Exit Audit')
                ->icon('heroicon-o-arrow-left-on-rectangle')
                ->color('warning')
                ->modalHeading('Initiate Tenant Deboarding & Trigger Exit Audit')
                ->modalDescription('Record notice dates, reason for exit, and automatically trigger the Move-Out Verification Audit.')
                ->form([
                    DatePicker::make('notice_date')
                        ->label('Notice Date')
                        ->default(now()->toDateString())
                        ->required(),
                    DatePicker::make('vacating_date')
                        ->label('Target Vacating Date')
                        ->required(),
                    Select::make('deboarding_reason')
                        ->label('Reason for Deboarding')
                        ->options([
                            'Agreement Expiry' => 'Agreement Expiry',
                            'Tenant Early Termination' => 'Tenant Early Termination',
                            'Owner Request' => 'Owner Request / Non-renewal',
                            'Eviction' => 'Eviction',
                            'Mutual Agreement' => 'Mutual Agreement',
                        ])
                        ->default('Agreement Expiry')
                        ->required(),
                    Textarea::make('deboarding_notes')
                        ->label('Notes & Special Exit Remarks')
                        ->rows(2),
                ])
                ->action(function (array $data) {
                    $record = $this->getRecord();
                    $service = app(TenancyDeboardingService::class);
                    $service->initiateDeboarding($record, $data);
                    $audit = $service->triggerMoveOutAudit($record, auth()->user());

                    Notification::make()
                        ->title('Deboarding Initiated & Exit Audit Triggered')
                        ->body("Notice recorded. Move-Out Verification Audit #{$audit->audit_number} created.")
                        ->success()
                        ->send();

                    $this->redirect(TenancyAgreementResource::getUrl('deboard', ['record' => $record]));
                })
                ->extraAttributes(['style' => 'display: none;']),

            Action::make('activateTenancyHeader')
                ->label('Activate Tenancy')
                ->icon('heroicon-o-bolt')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Activate Tenancy Agreement')
                ->modalDescription('Are you sure you want to activate this tenancy agreement? This will mark the agreement as active, transition property status to occupied, and permanently lock the linked Move-In Audit.')
                ->modalSubmitActionLabel('Yes, Activate Tenancy')
                ->action(fn () => $this->activateTenancy())
                ->extraAttributes(['style' => 'display: none;']),
        ];
    }
}
