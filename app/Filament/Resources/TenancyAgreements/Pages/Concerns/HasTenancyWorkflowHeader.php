<?php

namespace App\Filament\Resources\TenancyAgreements\Pages\Concerns;

use App\Domain\Agreement\Actions\ActivateTenancyAction;
use App\Domain\Agreement\Actions\RenewTenancyAgreementAction;
use App\Domain\Agreement\Services\TenancyDeboardingService;
use App\Domain\Auth\Enums\RoleName;
use App\Domain\Finance\Services\AccountingProvisioningService;
use App\Filament\Resources\Operations\TenantDeboardingResource;
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

        if (auth()->check() && ! auth()->user()->can('activate', $record)) {
            Notification::make()
                ->title('Unauthorized')
                ->body('You do not have permission to activate this tenancy agreement.')
                ->danger()
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
                ->authorize(fn () => auth()->user()?->can('deboard', $this->getRecord()) ?? false)
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
                    $deboarding = $service->initiateDeboarding($record, $data, auth()->user());

                    Notification::make()
                        ->title('Deboarding Initiated & Exit Audit Triggered')
                        ->body('Notice recorded. Move-Out Verification Audit has been created.')
                        ->success()
                        ->send();

                    $this->redirect(TenantDeboardingResource::getUrl('edit', ['record' => $deboarding->id]));
                })
                ->extraAttributes(['style' => 'display: none;']),

            Action::make('activateTenancyHeader')
                ->label('Activate Tenancy')
                ->icon('heroicon-o-bolt')
                ->color('success')
                ->authorize(fn () => auth()->user()?->can('activate', $this->getRecord()) ?? false)
                ->requiresConfirmation()
                ->modalHeading('Activate Tenancy Agreement')
                ->modalDescription('Are you sure you want to activate this tenancy agreement? This will mark the agreement as active, transition property status to occupied, and permanently lock the linked Move-In Audit.')
                ->modalSubmitActionLabel('Yes, Activate Tenancy')
                ->action(fn () => $this->activateTenancy())
                ->extraAttributes(['style' => 'display: none;']),

            Action::make('renewTenancyHeader')
                ->label('Renew Tenancy Agreement')
                ->icon('heroicon-o-arrow-path')
                ->color('purple')
                ->authorize(fn () => auth()->user()?->can('renew', $this->getRecord()) ?? false)
                ->modalHeading('Renew Tenancy Agreement & Draft 11-Month Lease')
                ->modalDescription('Carries forward tenant KYC, inventory audit references, and security deposit, establishing a renewed 11-month lease term with updated commercial terms.')
                ->modalSubmitActionLabel('Draft Renewal Agreement')
                ->form(fn () => TenancyAgreementForm::getRenewalFormSchema($this->getRecord()))
                ->action(function (array $data) {
                    $record = $this->getRecord();
                    $action = app(RenewTenancyAgreementAction::class);
                    $renewal = $action->execute($record, $data, auth()->user());

                    Notification::make()
                        ->title('Renewal Agreement Drafted')
                        ->body("Agreement {$renewal->code} has been drafted with carried-over KYC and audit records.")
                        ->success()
                        ->send();

                    $this->redirect(TenancyAgreementResource::getUrl('edit', ['record' => $renewal]));
                })
                ->extraAttributes(['style' => 'display: none;']),

            Action::make('generateDocInvoiceHeader')
                ->label('Generate Documentation Invoice')
                ->icon('heroicon-o-document-currency-rupee')
                ->color('primary')
                ->authorize(fn () => auth()->user()?->hasAnyRole([RoleName::BUSINESS_OWNER, RoleName::CITY_MANAGER, RoleName::ACCOUNTANT]) ?? false)
                ->requiresConfirmation()
                ->modalHeading('Generate Documentation Fee Invoice')
                ->modalDescription(function () {
                    $record = $this->getRecord();
                    $fee = (float) ($record?->documentation_charge ?? ($record?->is_renewal ? 1000.00 : 1500.00));

                    return 'This will generate and post a firm Sales Invoice of ₹'.number_format($fee, 2)." to the tenant's account for agreement documentation and legal execution.";
                })
                ->modalSubmitActionLabel('Yes, Generate Invoice')
                ->action(function () {
                    $record = $this->getRecord();
                    $provisioning = app(AccountingProvisioningService::class);
                    $invoice = $provisioning->generateDocumentationChargeInvoice($record);

                    if ($invoice) {
                        Notification::make()
                            ->title('Documentation Invoice Created')
                            ->body("Invoice {$invoice->invoice_number} has been generated and posted to the General Ledger.")
                            ->success()
                            ->send();
                    } else {
                        Notification::make()
                            ->title('Invoicing Ineligible')
                            ->body('Could not generate documentation invoice. Please check documentation charge amount and tenant profile.')
                            ->warning()
                            ->send();
                    }

                    $this->redirect(TenancyAgreementResource::getUrl('edit', ['record' => $record]));
                })
                ->extraAttributes(['style' => 'display: none;']),
        ];
    }
}
