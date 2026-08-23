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
                                    $prop = Property::find($state);
                                    $agr = $prop?->agreements()->where('status', 'active')->first();
                                    if ($agr) {
                                        $set('rent_collected', (float) $agr->rent_amount);
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
                            ->options(fn () => Account::where('type', 'asset')->pluck('name', 'id'))
                            ->searchable(),

                        Textarea::make('notes')
                            ->label('Payout Remarks'),
                    ])
                    ->action(function (array $data, ProcessOwnerPayoutAction $action) {
                        $property = Property::findOrFail($data['property_id']);
                        $payout = $action->execute(
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

