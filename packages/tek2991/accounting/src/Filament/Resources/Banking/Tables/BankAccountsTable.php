<?php

namespace Tek2991\Accounting\Filament\Resources\Banking\Tables;

use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions;
use Illuminate\Database\Eloquent\Builder;
use Tek2991\Accounting\Enums\BankAccountType;
use Tek2991\Accounting\Models\BankAccount;

class BankAccountsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['account']))
            ->columns([
                Tables\Columns\TextColumn::make('account.name')
                    ->label('Chart Account')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                    
                Tables\Columns\TextColumn::make('nickname')
                    ->label('Nickname')
                    ->searchable()
                    ->sortable()
                    ->description(fn (BankAccount $record) => $record->mask),

                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->label('Type')
                    ->sortable(),

                Tables\Columns\TextColumn::make('account.reporting_class')
                    ->label('Reporting Class')
                    ->badge()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('account.currency_code')
                    ->label('Currency')
                    ->badge()
                    ->color('gray')
                    ->toggleable(),

                Tables\Columns\IconColumn::make('enabled')
                    ->label('Default')
                    ->boolean()
                    ->trueIcon('heroicon-o-lock-closed')
                    ->falseIcon('heroicon-o-minus-circle')
                    ->trueColor('primary')
                    ->falseColor('gray'),

                Tables\Columns\TextColumn::make('account.code')
                    ->label('Account Code')
                    ->badge()
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Last Updated')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options(BankAccountType::class),

                Tables\Filters\TernaryFilter::make('enabled')
                    ->label('Default Account')
                    ->trueLabel('Default only')
                    ->falseLabel('Non-default only'),
            ])
            ->recordActions([
                Actions\EditAction::make(),
                Actions\DeleteAction::make()
                    ->requiresConfirmation()
                    ->before(function (BankAccount $record, Actions\DeleteAction $action) {
                        if ($record->transactions()->exists()) {
                            \Filament\Notifications\Notification::make()
                                ->danger()
                                ->title('Cannot Delete')
                                ->body('This bank account has transactions and cannot be deleted.')
                                ->send();
                            $action->halt();
                        }
                    }),
            ])
            ->groupedBulkActions([
                Actions\DeleteBulkAction::make()
                    ->requiresConfirmation(),
            ])
            ->defaultSort('account.name');
    }
}
