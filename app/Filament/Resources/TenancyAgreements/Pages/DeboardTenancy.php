<?php

namespace App\Filament\Resources\TenancyAgreements\Pages;

use App\Domain\Agreement\Models\TenancyAgreement;
use App\Domain\Agreement\Services\TenancyDeboardingService;
use App\Domain\Audit\Enums\AuditStatus;
use App\Filament\Resources\Operations\AuditResource;
use App\Filament\Resources\TenancyAgreements\Pages\Concerns\HasTenancyWorkflowHeader;
use App\Filament\Resources\TenancyAgreements\TenancyAgreementResource;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;

class DeboardTenancy extends EditRecord
{
    use HasTenancyWorkflowHeader;

    protected static string $resource = TenancyAgreementResource::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowLeftOnRectangle;

    protected static ?string $navigationLabel = '6. Deboarding & Exit';

    protected static ?string $title = 'Tenant Deboarding & Property Vacating';

    public static function shouldRegisterNavigation(array $parameters = []): bool
    {
        $record = $parameters['record'] ?? null;
        if ($record instanceof TenancyAgreement) {
            return in_array($record->status, ['active', 'deboarding_initiated', 'vacated']);
        }

        return true;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('initiateDeboardingPageAction')
                ->label('Initiate Deboarding & Trigger Exit Audit')
                ->icon('heroicon-o-arrow-left-on-rectangle')
                ->color('warning')
                ->modalHeading('Initiate Tenant Deboarding & Trigger Exit Audit')
                ->modalDescription('Record notice dates, reason for exit, and automatically trigger the Move-Out Verification Audit.')
                ->visible(fn () => $this->record && $this->record->status === 'active')
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
                    $service = app(TenancyDeboardingService::class);
                    $service->initiateDeboarding($this->record, $data);
                    $audit = $service->triggerMoveOutAudit($this->record, auth()->user());

                    Notification::make()
                        ->title('Deboarding Initiated & Exit Audit Triggered')
                        ->body("Notice recorded. Move-Out Verification Audit #{$audit->audit_number} has been created for exit inspection.")
                        ->success()
                        ->send();

                    $this->fillForm();
                }),

            Action::make('completeDeboardingPageAction')
                ->label('Complete Deboarding & Vacate Property')
                ->icon('heroicon-o-check-badge')
                ->color('danger')
                ->modalHeading('Complete Deboarding & Vacate Property')
                ->modalDescription('Finalize tenant exit, settle security deposit, lock the Move-Out audit, and update property status.')
                ->visible(fn () => $this->record && $this->record->status === 'deboarding_initiated')
                ->form([
                    Select::make('new_property_status')
                        ->label('New Property Status')
                        ->options([
                            'vacant' => 'Vacant & Ready for Onboarding',
                            'under_maintenance' => 'Under Maintenance / Repairs Needed',
                        ])
                        ->default('vacant')
                        ->required(),
                    TextInput::make('net_deposit_refund')
                        ->label('Net Security Deposit Refundable (₹)')
                        ->numeric()
                        ->prefix('₹')
                        ->default(fn () => $this->record?->net_deposit_refund ?? $this->record?->security_deposit ?? 0.00)
                        ->required(),
                    Select::make('deposit_settlement_status')
                        ->label('Security Deposit Settlement Status')
                        ->options([
                            'pending' => 'Pending Settlement',
                            'refunded' => 'Refunded to Tenant',
                            'balance_due' => 'Balance Due from Tenant',
                            'settled' => 'Fully Settled',
                        ])
                        ->default('settled')
                        ->required(),
                ])
                ->action(function (array $data) {
                    if ($this->record->moveOutAudit) {
                        $auditStatus = $this->record->moveOutAudit->status;
                        $statusVal = $auditStatus instanceof AuditStatus ? $auditStatus->value : (string) $auditStatus;
                        if (! in_array($statusVal, ['approved', 'completed'])) {
                            Notification::make()
                                ->title('Cannot Complete Deboarding')
                                ->body('The Move-Out Verification Audit must be approved before completing deboarding.')
                                ->warning()
                                ->persistent()
                                ->send();

                            return;
                        }
                    }

                    $service = app(TenancyDeboardingService::class);
                    $service->completeDeboardingAndVacate(
                        $this->record,
                        $data['new_property_status'] ?? 'vacant',
                        [
                            'net_refund' => $data['net_deposit_refund'] ?? 0,
                            'settlement_status' => $data['deposit_settlement_status'] ?? 'settled',
                        ],
                        auth()->user()
                    );

                    Notification::make()
                        ->title('Deboarding Completed')
                        ->body("Tenancy agreement #{$this->record->code} has been marked as Vacated and property status updated.")
                        ->success()
                        ->send();

                    $this->fillForm();
                }),
        ];
    }

    public function form(Schema $schema): Schema
    {
        $isVacated = $this->getRecord()?->status === 'vacated';

        return $schema
            ->disabled($isVacated)
            ->components([
                Section::make('1. Deboarding Lifecycle & Notice Information')
                    ->schema([
                        DatePicker::make('notice_date')
                            ->label('Notice Given Date'),

                        DatePicker::make('vacating_date')
                            ->label('Target / Actual Vacating Date'),

                        Select::make('deboarding_reason')
                            ->label('Deboarding Reason')
                            ->options([
                                'Agreement Expiry' => 'Agreement Expiry',
                                'Tenant Early Termination' => 'Tenant Early Termination',
                                'Owner Request' => 'Owner Request / Non-renewal',
                                'Eviction' => 'Eviction',
                                'Mutual Agreement' => 'Mutual Agreement',
                            ]),

                        Textarea::make('deboarding_notes')
                            ->label('Deboarding Notes & Exit Remarks')
                            ->rows(2)
                            ->columnSpanFull(),
                    ])->columns(3),

                Section::make('2. Move-Out Verification Audit')
                    ->description('Exit inspection comparing current property condition against the Move-In baseline.')
                    ->schema([
                        Placeholder::make('move_out_audit_card')
                            ->label('Move-Out Verification Audit Status')
                            ->content(function ($record) {
                                if (! $record) {
                                    return '';
                                }
                                $audit = $record->moveOutAudit;
                                if (! $audit) {
                                    return new HtmlString(
                                        '<div style="padding: 14px; background-color: rgba(239, 246, 255, 1); border: 1px solid rgba(191, 219, 254, 1); border-radius: 8px; color: #1e40af; font-size: 13px;">'.
                                            'ℹ No Move-Out Audit triggered yet. Click <strong>Initiate Deboarding & Trigger Exit Audit</strong> to generate exit inspection.'.
                                            '</div>'
                                    );
                                }

                                $auditStatus = $audit->status;
                                $statusLabel = is_object($auditStatus) && method_exists($auditStatus, 'getLabel') ? $auditStatus->getLabel() : (string) $auditStatus;
                                $auditUrl = AuditResource::getUrl('edit', ['record' => $audit->id]);

                                return new HtmlString(
                                    '<div style="display: flex; align-items: center; justify-content: space-between; padding: 16px; background: #ffffff; border: 1px solid #e5e7eb; border-radius: 8px;">'.
                                        '<div>'.
                                            '<div style="font-weight: 600; font-size: 15px; color: #111827;">Move-Out Audit #'.e($audit->audit_number).'</div>'.
                                            '<div style="font-size: 13px; color: #6b7280; margin-top: 4px;">Status: <span style="font-weight: 600; text-transform: capitalize; color: '.($statusLabel === 'Approved' ? '#059669' : '#d97706').';">'.e($statusLabel).'</span> | Referenced Baseline: #'.e($record->audit?->audit_number ?? 'N/A').'</div>'.
                                        '</div>'.
                                        '<div>'.
                                            '<a href="'.e($auditUrl).'" target="_blank" style="display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; background-color: #4f46e5; color: #ffffff; border-radius: 6px; font-weight: 500; font-size: 13px; text-decoration: none;">'.
                                                'Perform Exit Inspection →'.
                                            '</a>'.
                                        '</div>'.
                                    '</div>'
                                );
                            })
                            ->columnSpanFull(),
                    ]),

                Section::make('3. Key Return & Security Deposit Settlement')
                    ->schema([
                        Toggle::make('keys_returned')
                            ->label('Keys & Society Badges Returned by Tenant')
                            ->live(),

                        DateTimePicker::make('keys_returned_at')
                            ->label('Key Return Date & Time'),

                        SpatieMediaLibraryFileUpload::make('key_return_attachments')
                            ->label('Key Return Photo / Receipt')
                            ->collection('key_return_attachments')
                            ->multiple()
                            ->downloadable()
                            ->openable()
                            ->columnSpanFull(),

                        TextInput::make('net_deposit_refund')
                            ->label('Net Security Deposit Refundable (₹)')
                            ->numeric()
                            ->prefix('₹')
                            ->default(fn ($record) => $record?->net_deposit_refund ?? $record?->security_deposit ?? 0.00)
                            ->helperText('Net deposit refund amount after deducting damages or dues.'),

                        Select::make('deposit_settlement_status')
                            ->label('Deposit Settlement Status')
                            ->options([
                                'pending' => 'Pending Settlement',
                                'refunded' => 'Refunded to Tenant',
                                'balance_due' => 'Balance Due from Tenant',
                                'settled' => 'Fully Settled',
                            ])
                            ->default('pending'),
                    ])->columns(2),
            ]);
    }

    protected function getFormActions(): array
    {
        if ($this->getRecord()?->status === 'vacated') {
            return [];
        }

        return parent::getFormActions();
    }

    protected function getRedirectUrl(): ?string
    {
        return null;
    }
}
