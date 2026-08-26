<?php

namespace App\Filament\Resources\TenancyAgreements\Pages;

use App\Domain\Agreement\Enums\DeboardingStatus;
use App\Domain\Agreement\Models\TenancyAgreement;
use App\Domain\Agreement\Services\TenancyDeboardingService;
use App\Domain\Audit\Enums\AuditStatus;
use App\Filament\Resources\Operations\AuditResource;
use App\Filament\Resources\Operations\TenantDeboardingResource;
use App\Filament\Resources\TenancyAgreements\Pages\Concerns\HasTenancyWorkflowHeader;
use App\Filament\Resources\TenancyAgreements\TenancyAgreementResource;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Grid;
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

    protected static ?string $title = 'Deboarding Status & Reference';

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
        /** @var TenancyAgreement $record */
        $record = $this->getRecord();
        $deboarding = $record?->deboarding;

        return [
            Action::make('openDedicatedWorkflow')
                ->label('Open Dedicated Deboarding Workflow →')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('primary')
                ->visible(fn () => $deboarding !== null)
                ->url(fn () => TenantDeboardingResource::getUrl('edit', ['record' => $deboarding->id])),

            Action::make('initiateDeboardingPageAction')
                ->label('Initiate Deboarding & Exit Workflow')
                ->icon('heroicon-o-arrow-left-on-rectangle')
                ->color('warning')
                ->modalHeading('Initiate Tenant Deboarding & Exit Workflow')
                ->modalDescription('Record notice dates, reason for exit, and launch the dedicated Deboarding & Exit Workflow.')
                ->visible(fn () => $record && $record->status === 'active' && ! $deboarding)
                ->form([
                    DatePicker::make('notice_date')
                        ->label('Notice Date')
                        ->default(now()->toDateString())
                        ->required(),
                    DatePicker::make('vacating_date')
                        ->label('Target Vacating Date')
                        ->default(now()->addDays(30)->toDateString())
                        ->required(),
                    Select::make('deboarding_reason')
                        ->label('Reason for Deboarding')
                        ->options([
                            'Agreement Expiry' => 'Agreement Expiry',
                            'Tenant Early Termination' => 'Tenant Early Termination',
                            'Owner Request' => 'Owner Request / Non-renewal',
                            'Eviction' => 'Eviction',
                            'Mutual Agreement' => 'Mutual Agreement',
                            'Other' => 'Other',
                        ])
                        ->default('Agreement Expiry')
                        ->required(),
                    Textarea::make('deboarding_notes')
                        ->label('Notes & Special Exit Remarks')
                        ->rows(2),
                ])
                ->action(function (array $data) {
                    $service = app(TenancyDeboardingService::class);
                    $deboarding = $service->initiateDeboarding($this->record, $data, auth()->user());

                    Notification::make()
                        ->title('Deboarding Workflow Initiated')
                        ->body("Deboarding #{$deboarding->code} created. Redirecting to dedicated workflow...")
                        ->success()
                        ->send();

                    $this->redirect(TenantDeboardingResource::getUrl('edit', ['record' => $deboarding->id]));
                }),
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->disabled(true)
            ->components([
                Section::make('Deboarding Overview & Quick Launcher')
                    ->schema([
                        Placeholder::make('deboarding_status_overview')
                            ->hiddenLabel()
                            ->content(function (?TenancyAgreement $record) {
                                if (! $record) {
                                    return '';
                                }

                                $deboarding = $record->deboarding;

                                if (! $deboarding) {
                                    return new HtmlString(
                                        '<div style="padding: 20px; background: #fffbeb; border: 1px solid #fde68a; border-radius: 10px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">' .
                                            '<div>' .
                                                '<div style="font-weight: 700; font-size: 15px; color: #92400e;">⚠️ Deboarding Not Yet Initiated</div>' .
                                                '<div style="font-size: 13px; color: #b45309; margin-top: 2px;">This tenancy agreement is currently Active. When the tenant gives notice or agreement expires, initiate the deboarding workflow.</div>' .
                                            '</div>' .
                                        '</div>'
                                    );
                                }

                                $status = $deboarding->status instanceof DeboardingStatus ? $deboarding->status : DeboardingStatus::tryFrom((string) $deboarding->status);
                                $statusLabel = $status?->getLabel() ?? ucfirst((string) $deboarding->status);
                                $workflowUrl = TenantDeboardingResource::getUrl('edit', ['record' => $deboarding->id]);

                                return new HtmlString(
                                    '<div style="padding: 20px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px;">' .
                                        '<div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">' .
                                            '<div>' .
                                                '<div style="display: flex; align-items: center; gap: 10px;">' .
                                                    '<span style="font-size: 1.15rem; font-weight: 800; color: #0f172a;">Deboarding #' . e($deboarding->code) . '</span>' .
                                                    '<span style="display: inline-flex; padding: 3px 10px; font-size: 12px; font-weight: 700; border-radius: 9999px; background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd;">' . e($statusLabel) . '</span>' .
                                                '</div>' .
                                                '<div style="font-size: 13px; color: #64748b; margin-top: 4px;">' .
                                                    'Notice Date: <strong>' . e($deboarding->notice_date?->format('d M Y') ?? 'N/A') . '</strong> | Target Vacate: <strong>' . e($deboarding->target_vacating_date?->format('d M Y') ?? 'N/A') . '</strong>' .
                                                '</div>' .
                                            '</div>' .
                                            '<div>' .
                                                '<a href="' . e($workflowUrl) . '" style="display: inline-flex; align-items: center; gap: 6px; padding: 10px 18px; background: #2563eb; color: #ffffff; font-weight: 600; font-size: 13px; border-radius: 8px; text-decoration: none; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">' .
                                                    'Open Dedicated Deboarding Workflow →' .
                                                '</a>' .
                                            '</div>' .
                                        '</div>' .
                                    '</div>'
                                );
                            })
                            ->columnSpanFull(),
                    ]),

                Section::make('Exit Inspection & Audit Status')
                    ->schema([
                        Placeholder::make('move_out_audit_status')
                            ->hiddenLabel()
                            ->content(function (?TenancyAgreement $record) {
                                if (! $record) {
                                    return '';
                                }

                                $audit = $record->moveOutAudit ?? $record->deboarding?->moveOutAudit;
                                if (! $audit) {
                                    return new HtmlString('<div style="font-size: 13px; color: #64748b;">No Move-Out audit linked.</div>');
                                }

                                $auditStatus = $audit->status;
                                $statusLabel = is_object($auditStatus) && method_exists($auditStatus, 'getLabel') ? $auditStatus->getLabel() : (string) $auditStatus;
                                $auditUrl = AuditResource::getUrl('edit', ['record' => $audit->id]);

                                return new HtmlString(
                                    '<div style="display: flex; justify-content: space-between; align-items: center; padding: 12px; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px;">' .
                                        '<div>' .
                                            '<div style="font-weight: 600; font-size: 14px;">Move-Out Audit #' . e($audit->audit_number) . '</div>' .
                                            '<div style="font-size: 12px; color: #64748b; margin-top: 2px;">Status: <strong>' . e($statusLabel) . '</strong> | Inspector: ' . e($audit->inspector?->name ?? 'Unassigned') . '</div>' .
                                        '</div>' .
                                        '<div>' .
                                            '<a href="' . e($auditUrl) . '" target="_blank" style="font-size: 12px; color: #4f46e5; font-weight: 600;">View Audit Details →</a>' .
                                        '</div>' .
                                    '</div>'
                                );
                            })
                            ->columnSpanFull(),
                    ]),

                Section::make('Keys & Deposit Settlement Summary')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                Placeholder::make('keys_summary')
                                    ->label('Keys Returned')
                                    ->content(fn (?TenancyAgreement $record) => ($record?->keys_returned || $record?->deboarding?->keys_returned) ? '✅ Yes' : '❌ Pending'),

                                Placeholder::make('net_refund_summary')
                                    ->label('Net Deposit Refund')
                                    ->content(fn (?TenancyAgreement $record) => '₹' . number_format((float) ($record?->deboarding?->net_deposit_refund ?? $record?->net_deposit_refund ?? 0.00), 2)),

                                Placeholder::make('settlement_status_summary')
                                    ->label('Settlement Status')
                                    ->content(fn (?TenancyAgreement $record) => ucfirst(str_replace('_', ' ', (string) ($record?->deboarding?->settlement_status ?? $record?->deposit_settlement_status ?? 'pending')))),
                            ]),
                    ]),
            ]);
    }

    protected function getFormActions(): array
    {
        return [];
    }
}
