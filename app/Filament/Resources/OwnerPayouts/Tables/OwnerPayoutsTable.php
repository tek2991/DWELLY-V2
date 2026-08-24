<?php

namespace App\Filament\Resources\OwnerPayouts\Tables;

use App\Domain\Finance\Actions\ProcessOwnerPayoutAction;
use App\Domain\Property\Models\Property;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Tek2991\Accounting\Models\Account;

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

                TextColumn::make('period_start')
                    ->label('Period')
                    ->formatStateUsing(fn ($record) => $record->period_start?->format('M Y') ?? '-')
                    ->sortable(),

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
                    ->form([
                        Select::make('property_id')
                            ->label('Property')
                            ->options(fn() => Property::pluck('building_name', 'id'))
                            ->searchable()
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(function ($state, callable $set) {
                                if ($state) {
                                    $prop = Property::with(['agreements' => fn($q) => $q->where('status', 'active'), 'owner'])->find($state);
                                    $agr = $prop?->agreements->first();
                                    if ($agr) {
                                        $set('rent_collected', (float) $agr->rent_amount);
                                    }
                                    if ($prop?->owner) {
                                        $advBal = app(\App\Domain\Finance\Services\AccountingProvisioningService::class)->getOwnerAdvanceBalance($prop->owner);
                                        if ($advBal > 0) {
                                            $set('advance_offset', (float) $advBal);
                                        }
                                    }
                                }
                            }),

                        DatePicker::make('period_start')
                            ->label('Period Start')
                            ->default(now()->startOfMonth())
                            ->required(),

                        DatePicker::make('period_end')
                            ->label('Period End')
                            ->default(now()->endOfMonth())
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
                            ->label('Advance / Geyser Offset (₹)')
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
                            ->label('Payout Remarks'),
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
                    })
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([]);
    }
}

