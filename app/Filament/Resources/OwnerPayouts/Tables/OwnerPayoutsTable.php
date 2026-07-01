<?php

namespace App\Filament\Resources\OwnerPayouts\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;

class OwnerPayoutsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('property.building_name')->label('Property')->searchable(),
                TextColumn::make('owner.display_name')->label('Owner')->searchable(),
                TextColumn::make('period_start')->date(),
                TextColumn::make('period_end')->date(),
                TextColumn::make('rent_collected')->money('INR'),
                TextColumn::make('management_fee')->money('INR')->color('danger'),
                TextColumn::make('amount')->label('Payout Amount')->money('INR')->weight('bold'),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'completed' => 'success',
                        'failed' => 'danger',
                        default => 'gray',
                    }),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                \Filament\Tables\Actions\Action::make('generate_payout')
                    ->label('Generate Payout')
                    ->form([
                        \Filament\Forms\Components\Select::make('property_id')
                            ->label('Property')
                            ->options(fn() => \App\Domain\Property\Models\Property::pluck('building_name', 'id'))
                            ->searchable()
                            ->required(),
                        \Filament\Forms\Components\DatePicker::make('period_start')->required(),
                        \Filament\Forms\Components\DatePicker::make('period_end')->required(),
                    ])
                    ->action(function (array $data, \App\Domain\Finance\Actions\ProcessOwnerPayoutAction $action) {
                        $property = \App\Domain\Property\Models\Property::findOrFail($data['property_id']);
                        $action->execute(
                            $property,
                            $data['period_start'],
                            $data['period_end'],
                            auth()->user()
                        );
                        
                        \Filament\Notifications\Notification::make()
                            ->title('Payout Generated Successfully')
                            ->success()
                            ->send();
                    })
            ])
            ->recordActions([
                // View only — payout records are financial and immutable
            ])
            ->toolbarActions([
                // No bulk delete — financial records are never permanently deleted
            ]);
    }
}
