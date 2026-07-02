<?php

namespace Tek2991\Accounting\Filament\Resources\Contacts\Tables;

use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions;
use Tek2991\Accounting\Enums\ContactType;

class ContactsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable(),
                Tables\Columns\TextColumn::make('phone')
                    ->searchable(),
                Tables\Columns\TextColumn::make('receivable_balance')
                    ->label('To Receive')
                    ->money(config('accounting.default_currency', 'USD'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('payable_balance')
                    ->label('To Pay')
                    ->money(config('accounting.default_currency', 'USD'))
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options(ContactType::class),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->recordActions([
                Actions\EditAction::make(),
                Actions\DeleteAction::make()
                    ->hidden(fn () => !config('accounting.contacts.allow_delete', true)),
                Actions\RestoreAction::make(),
            ])
            ->groupedBulkActions([
                Actions\DeleteBulkAction::make()
                    ->hidden(fn () => !config('accounting.contacts.allow_delete', true)),
                Actions\RestoreBulkAction::make(),
            ]);
    }
}
