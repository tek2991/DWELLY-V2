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
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;

class ManageDeboardingCompletion extends EditRecord
{
    use HasDeboardingWorkflowHeader;

    protected static string $resource = TenantDeboardingResource::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCheckCircle;

    protected static ?string $navigationLabel = '6. Final Handover & Vacate';

    protected static ?string $title = 'Deboarding – Final Handover & Property Release';

    public static function canAccess(array $parameters = []): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        if ($user->roles->isEmpty()) {
            return true;
        }

        if ($user->hasAnyRole(['Operations Executive', 'Demand Manager', 'Supply Manager']) && ! $user->hasAnyRole(['Business Owner', 'City Manager', 'Operations Manager', 'Accountant'])) {
            return false;
        }

        return $user->can('deboarding.complete')
            || $user->hasAnyRole(['Business Owner', 'City Manager', 'Operations Manager', 'Accountant']);
    }

    protected function getHeaderActions(): array
    {
        /** @var TenantDeboarding $record */
        $record = $this->getRecord();
        $isCompleted = $record && $record->status === DeboardingStatus::COMPLETED;
        $canComplete = auth()->user()?->hasAnyRole(['Business Owner', 'City Manager', 'Operations Manager', 'Accountant']) || auth()->user()?->roles->isEmpty();

        return [
            Action::make('completeDeboarding')
                ->label('Complete Deboarding & Vacate Property')
                ->icon('heroicon-o-check-badge')
                ->color('danger')
                ->disabled($isCompleted)
                ->visible($canComplete)
                ->modalHeading('Finalize Deboarding & Vacate Property')
                ->modalDescription('This will permanently lock the exit audit, finalize the security deposit settlement, mark the tenancy agreement as Vacated, and update property status.')
                ->form([
                    Placeholder::make('property_status_info')
                        ->label('Property Handover Status')
                        ->content(function () use ($record) {
                            $tickets = $record->maintenanceRequests;
                            if ($tickets->isEmpty()) {
                                $deduction = (float) ($record->maintenance_deduction ?? 0);
                                if ($deduction > 0) {
                                    return new HtmlString('<div style="color: #b45309; font-size: 13px; background: #fffbeb; padding: 10px 12px; border-radius: 6px; border: 1px dashed #fcd34d;">' .
                                        '⚠️ <strong>Turnover Damage Deductions:</strong> A damage deduction of <strong>₹' . number_format($deduction, 2) . '</strong> was recorded on deposit. Property will be marked <strong>Vacant</strong> upon completion. If repair work orders are needed, you can raise a ticket from Step 3.' .
                                        '</div>');
                                }

                                return new HtmlString('<div style="font-size: 13px; color: #166534; background: #f0fdf4; padding: 8px 12px; border-radius: 6px; border: 1px solid #bbf7d0;">' .
                                    '✅ Property will be cleanly marked <strong>Vacant</strong> upon completion.' .
                                    '</div>');
                            }

                            $list = $tickets->map(function ($t) {
                                $statusLabel = $t->status?->getLabel() ?? ucfirst((string) $t->status);
                                $age = $t->created_at?->diffForHumans();

                                return "<li style=\"margin-top: 4px;\"><strong>#{$t->ticket_number}</strong>: " . e($t->title) . " &mdash; <span style=\"color: #b45309; font-weight: 600;\">{$statusLabel}</span> (created {$age})</li>";
                            })->implode('');

                            return new HtmlString('<div style="background: #f8fafc; padding: 10px 12px; border-radius: 6px; border: 1px solid #e2e8f0; font-size: 13px;">' .
                                '<p style="font-weight: 600; color: #1e293b; margin: 0 0 4px 0;">Active Maintenance Tickets (' . $tickets->count() . '):</p>' .
                                '<ul style="padding-left: 20px; margin: 0; color: #475569;">' . $list . '</ul>' .
                                '<p style="margin: 6px 0 0 0; color: #64748b; font-size: 12px;">Property will be marked <strong>Vacant</strong>. The open tickets will automatically track repairs under the Maintenance tab.</p>' .
                                '</div>');
                        }),

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
                        'vacant',
                        [
                            'net_refund' => $data['net_refund'] ?? $record->net_deposit_refund,
                            'settlement_status' => $data['settlement_status'] ?? 'settled',
                            'record_accounting_transaction' => $data['record_accounting_transaction'] ?? false,
                        ],
                        auth()->user()
                    );

                    Notification::make()
                        ->title('Tenant Deboarding Completed')
                        ->body("Deboarding #{$record->code} completed. Property marked Vacant.")
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
