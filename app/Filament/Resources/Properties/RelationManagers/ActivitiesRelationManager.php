<?php

namespace App\Filament\Resources\Properties\RelationManagers;

use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ActivitiesRelationManager extends RelationManager
{
    use \App\Filament\Resources\Properties\RelationManagers\Traits\LocksDuringPropertyOnboarding;

    protected static string $relationship = 'activities';

    protected static ?string $recordTitleAttribute = 'description';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\TextInput::make('description')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('description')
            ->columns([
                Tables\Columns\TextColumn::make('description')
                    ->label('Action')
                    ->searchable(),
                Tables\Columns\TextColumn::make('causer.name')
                    ->label('User')
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->label('Time'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                // No create action for activities
            ])
            ->actions([
                // No edit/delete action for activities
            ])
            ->bulkActions([
                //
            ])
            ->defaultSort('created_at', 'desc');
    }
}
