<?php

namespace App\Filament\Resources\Properties\RelationManagers;

use Filament\Actions\AssociateAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\DissociateAction;
use Filament\Actions\DissociateBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PricingVersionsRelationManager extends RelationManager
{
    protected static string $relationship = 'pricingVersions';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Forms\Components\DatePicker::make('effective_from')
                    ->required(),
                \Filament\Forms\Components\DatePicker::make('effective_to'),
                \Filament\Forms\Components\TextInput::make('rent')
                    ->numeric()
                    ->prefix('₹'),
                \Filament\Forms\Components\TextInput::make('security_deposit')
                    ->numeric()
                    ->prefix('₹'),
                \Filament\Forms\Components\TextInput::make('society_fee')
                    ->numeric()
                    ->prefix('₹'),
                \Filament\Forms\Components\TextInput::make('booking_amount')
                    ->numeric()
                    ->prefix('₹'),
                \Filament\Forms\Components\Textarea::make('notes')
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('effective_from')
            ->columns([
                TextColumn::make('effective_from')
                    ->date()
                    ->sortable(),
                TextColumn::make('effective_to')
                    ->date()
                    ->sortable(),
                TextColumn::make('rent')
                    ->money('INR')
                    ->sortable(),
                TextColumn::make('security_deposit')
                    ->money('INR')
                    ->sortable(),
                TextColumn::make('society_fee')
                    ->money('INR')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make(),
                AssociateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DissociateAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DissociateBulkAction::make(),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
