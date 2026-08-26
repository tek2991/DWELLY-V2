<?php

namespace App\Filament\Resources\OwnerPayouts\Tables;

use App\Domain\Finance\Actions\ProcessOwnerPayoutAction;
use App\Domain\Finance\Services\OwnerPayoutService;
use App\Domain\Property\Models\Property;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Tek2991\Accounting\Models\Account;
use Tek2991\Accounting\Models\OwnerPayout;

class OwnerPayoutsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('property.building_name')
                    ->label('Property')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('owner.display_name')
                    ->label('Owner')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('period')
                    ->label('Billing Period')
                    ->state(fn ($record) => $record->period_formatted ?? ($record->period_start ? $record->period_start->format('M Y') : '—'))
                    ->color('primary')
                    ->weight('medium')
                    ->sortable(['period_start']),

                TextColumn::make('rent_collected')
                    ->label('Gross Rent')
                    ->money('INR')
                    ->sortable(),

                TextColumn::make('management_fee')
                    ->label('Fee / Comm.')
                    ->money('INR')
                    ->color('danger')
                    ->sortable(),

                TextColumn::make('advance_offset')
                    ->label('Advance Offset')
                    ->money('INR')
                    ->color('warning')
                    ->sortable(),

                TextColumn::make('amount')
                    ->label('Net Payout')
                    ->money('INR')
                    ->weight('bold')
                    ->color('success')
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'completed' => 'success',
                        'pending' => 'warning',
                        'failed' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('processed_at')
                    ->label('Processed Date')
                    ->date()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->headerActions([
                Action::make('generate_payout')
                    ->label('Generate Owner Payout')
                    ->icon('heroicon-o-banknotes')
                    ->color('primary')
                    ->modalHeading('Generate Single Owner Payout')
                    ->modalWidth(Width::FourExtraLarge)
                    ->modalDescription('Review the billing period, gross rent, management fee, and advance deductions before disbursing.')
                    ->modalSubmitActionLabel('Confirm & Disburse Payout')
                    ->form([
                        Select::make('property_id')
                            ->label('Property')
                            ->options(fn() => Property::pluck('building_name', 'id'))
                            ->searchable()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (Set $set, Get $get, $state, OwnerPayoutService $service) {
                                if ($state) {
                                    $prop = Property::find($state);
                                    if ($prop) {
                                        $month = (int) ($get('month') ?: date('n'));
                                        $year = (int) ($get('year') ?: date('Y'));
                                        $calc = $service->calculatePayoutDetails($prop, $month, $year);
                                        $set('period_start', $calc['billing_period_start']);
                                        $set('period_end', $calc['billing_period_end']);
                                        $set('rent_collected', $calc['gross_rent']);
                                        $set('management_fee_percent', $calc['management_fee_percent']);
                                        $set('advance_offset', $calc['advance_offset']);
                                    }
                                }
                            }),

                        Select::make('month')
                            ->label('Payout Month')
                            ->options([
                                1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
                                5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
                                9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December',
                            ])
                            ->default((int) date('n'))
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (Set $set, Get $get, $state, OwnerPayoutService $service) {
                                $propId = $get('property_id');
                                if ($propId) {
                                    $prop = Property::find($propId);
                                    if ($prop) {
                                        $month = (int) $state;
                                        $year = (int) ($get('year') ?: date('Y'));
                                        $calc = $service->calculatePayoutDetails($prop, $month, $year);
                                        $set('period_start', $calc['billing_period_start']);
                                        $set('period_end', $calc['billing_period_end']);
                                        $set('rent_collected', $calc['gross_rent']);
                                        $set('management_fee_percent', $calc['management_fee_percent']);
                                        $set('advance_offset', $calc['advance_offset']);
                                    }
                                }
                            }),

                        TextInput::make('year')
                            ->label('Payout Year')
                            ->numeric()
                            ->default((int) date('Y'))
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (Set $set, Get $get, $state, OwnerPayoutService $service) {
                                $propId = $get('property_id');
                                if ($propId) {
                                    $prop = Property::find($propId);
                                    if ($prop) {
                                        $month = (int) ($get('month') ?: date('n'));
                                        $year = (int) $state;
                                        $calc = $service->calculatePayoutDetails($prop, $month, $year);
                                        $set('period_start', $calc['billing_period_start']);
                                        $set('period_end', $calc['billing_period_end']);
                                        $set('rent_collected', $calc['gross_rent']);
                                        $set('management_fee_percent', $calc['management_fee_percent']);
                                        $set('advance_offset', $calc['advance_offset']);
                                    }
                                }
                            }),

                        View::make('filament.billing.single-owner-payout-preview-modal')
                            ->columnSpanFull(),

                        DatePicker::make('period_start')
                            ->label('Billing Period Start')
                            ->required(),

                        DatePicker::make('period_end')
                            ->label('Billing Period End')
                            ->required(),

                        TextInput::make('rent_collected')
                            ->label('Gross Rent Collected (₹)')
                            ->numeric()
                            ->prefix('₹')
                            ->required(),

                        TextInput::make('management_fee_percent')
                            ->label('Management Fee (%)')
                            ->numeric()
                            ->default(10)
                            ->suffix('%')
                            ->required(),

                        TextInput::make('advance_offset')
                            ->label('Advance Offset (₹)')
                            ->numeric()
                            ->prefix('₹')
                            ->default(0)
                            ->helperText('Amount to deduct towards prior capital advances/purchases made on owner behalf.'),

                        TextInput::make('reserve_deduction')
                            ->label('Reserve Deduction (₹)')
                            ->numeric()
                            ->prefix('₹')
                            ->default(0),

                        Select::make('bank_account_id')
                            ->label('Disbursement Bank Account')
                            ->options(function () {
                                $defaultId = \Tek2991\Accounting\Facades\Accounting::getDefaultBankAccountId();

                                return Account::where('type', \Tek2991\Accounting\Enums\AccountType::Asset)
                                    ->where(function ($q) {
                                        $q->whereIn('system_role', [
                                            \Tek2991\Accounting\Enums\SystemRole::Bank,
                                            \Tek2991\Accounting\Enums\SystemRole::Cash,
                                        ])
                                        ->orWhere('code', 'like', '11%')
                                        ->orWhere('name', 'like', '%Current Account%')
                                        ->orWhere('name', 'like', '%Savings Account%')
                                        ->orWhere('name', 'like', '%Bank%')
                                        ->orWhere('name', 'like', '%Cash%');
                                    })
                                    ->where('is_control_account', false)
                                    ->get()
                                    ->mapWithKeys(function (Account $acc) use ($defaultId) {
                                        if ($acc->id === $defaultId) {
                                            return [$acc->id => "<div style='display: flex; align-items: center; justify-content: space-between; width: 100%;'><span>{$acc->name}</span><span style='font-size: 10px; font-weight: 700; background: #dbeafe; color: #1e40af; padding: 2px 8px; border-radius: 9999px; text-transform: uppercase;'>Default</span></div>"];
                                        }
                                        return [$acc->id => "<div>{$acc->name}</div>"];
                                    });
                            })
                            ->default(fn () => \Tek2991\Accounting\Facades\Accounting::getDefaultBankAccountId())
                            ->allowHtml()
                            ->searchable()
                            ->preload()
                            ->required(),

                        Textarea::make('notes')
                            ->label('Payout Remarks')
                            ->columnSpanFull(),
                    ])
                    ->action(function (array $data) {
                        $property = Property::findOrFail($data['property_id']);
                        $payout = app(ProcessOwnerPayoutAction::class)->execute(
                            $property,
                            $data['period_start'],
                            $data['period_end'],
                            auth()->user(),
                            [
                                'rent_collected' => $data['rent_collected'] ?? null,
                                'management_fee_percent' => $data['management_fee_percent'] ?? 10.0,
                                'advance_offset' => $data['advance_offset'] ?? 0.0,
                                'reserve_deduction' => $data['reserve_deduction'] ?? 0.0,
                                'bank_account_id' => $data['bank_account_id'] ?? null,
                                'notes' => $data['notes'] ?? null,
                            ]
                        );
                        
                        Notification::make()
                            ->title('Owner Payout Processed')
                            ->body("Disbursed net amount of ₹" . number_format($payout->amount, 2) . " for {$property->building_name}")
                            ->success()
                            ->send();
                    }),

                Action::make('bulk_generate_payouts')
                    ->label('Bulk Generate Monthly Payouts')
                    ->icon('heroicon-o-sparkles')
                    ->color('warning')
                    ->modalHeading('Bulk Disburse Monthly Owner Payouts')
                    ->modalWidth(Width::SevenExtraLarge)
                    ->modalSubmitActionLabel('Yes, Disburse Payouts Now')
                    ->modifyWizardUsing(fn (Wizard $wizard) => $wizard->nextAction(fn (Action $action) => $action->label('Confirm & Disburse Payouts')->color('warning')->icon('heroicon-o-arrow-right')))
                    ->steps([
                        Step::make('Review Payouts')
                            ->description('Select cycle & review owner payout calculations')
                            ->icon('heroicon-o-clipboard-document-list')
                            ->schema([
                                Select::make('month')
                                    ->label('Billing Month')
                                    ->options([
                                        1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
                                        5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
                                        9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December',
                                    ])
                                    ->default((int) date('n'))
                                    ->required()
                                    ->live(),

                                TextInput::make('year')
                                    ->label('Billing Year')
                                    ->numeric()
                                    ->default((int) date('Y'))
                                    ->required()
                                    ->live(),

                                Select::make('bank_account_id')
                                    ->label('Disbursement Bank Account')
                                    ->options(function () {
                                        $defaultId = \Tek2991\Accounting\Facades\Accounting::getDefaultBankAccountId();

                                        return Account::where('type', \Tek2991\Accounting\Enums\AccountType::Asset)
                                            ->where(function ($q) {
                                                $q->whereIn('system_role', [
                                                    \Tek2991\Accounting\Enums\SystemRole::Bank,
                                                    \Tek2991\Accounting\Enums\SystemRole::Cash,
                                                ])
                                                ->orWhere('code', 'like', '11%')
                                                ->orWhere('name', 'like', '%Current Account%')
                                                ->orWhere('name', 'like', '%Savings Account%')
                                                ->orWhere('name', 'like', '%Bank%')
                                                ->orWhere('name', 'like', '%Cash%');
                                            })
                                            ->where('is_control_account', false)
                                            ->get()
                                            ->mapWithKeys(function (Account $acc) use ($defaultId) {
                                                if ($acc->id === $defaultId) {
                                                    return [$acc->id => "<div style='display: flex; align-items: center; justify-content: space-between; width: 100%;'><span>{$acc->name}</span><span style='font-size: 10px; font-weight: 700; background: #dbeafe; color: #1e40af; padding: 2px 8px; border-radius: 9999px; text-transform: uppercase;'>Default</span></div>"];
                                                }
                                                return [$acc->id => "<div>{$acc->name}</div>"];
                                            });
                                    })
                                    ->default(fn () => \Tek2991\Accounting\Facades\Accounting::getDefaultBankAccountId())
                                    ->allowHtml()
                                    ->searchable()
                                    ->preload()
                                    ->required(),

                                View::make('filament.billing.bulk-owner-payout-preview-modal')
                                    ->columnSpanFull(),
                            ]),

                        Step::make('Final Confirmation')
                            ->description('Confirm ledger disbursements & fee revenue')
                            ->icon('heroicon-o-shield-check')
                            ->schema([
                                View::make('filament.billing.bulk-owner-payout-final-confirmation-modal')
                                    ->columnSpanFull(),
                            ]),
                    ])
                    ->action(function (array $data, OwnerPayoutService $service) {
                        $count = $service->bulkProcessOwnerPayouts(
                            (int) $data['month'],
                            (int) $data['year'],
                            auth()->user(),
                            !empty($data['bank_account_id']) ? (int) $data['bank_account_id'] : null
                        );

                        Notification::make()
                            ->title('Bulk Owner Payouts Completed')
                            ->body("Disbursed {$count} owner payouts for " . date('F Y', mktime(0, 0, 0, $data['month'], 1, $data['year'])))
                            ->success()
                            ->send();
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([]);
    }
}


