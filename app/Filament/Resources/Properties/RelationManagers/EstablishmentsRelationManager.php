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

class EstablishmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'establishments';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Forms\Components\Select::make('establishment_id')
                    ->relationship('establishment', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->createOptionForm([
                        \Filament\Forms\Components\Select::make('establishment_type_id')
                            ->options(fn() => \Illuminate\Support\Facades\DB::table('establishment_types')->pluck('name', 'id'))
                            ->required(),
                        \Filament\Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                    ]),
                \Filament\Forms\Components\TextInput::make('distance_km')
                    ->numeric()
                    ->label('Distance (KM)'),
                \Filament\Forms\Components\TextInput::make('travel_time_minutes')
                    ->numeric()
                    ->label('Travel Time (Mins)'),
                \Filament\Forms\Components\Textarea::make('remarks')
                    ->maxLength(65535)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('establishment_id')
            ->columns([
                \Filament\Tables\Columns\TextColumn::make('establishment.name')
                    ->label('Establishment Name')
                    ->searchable()
                    ->sortable(),
                \Filament\Tables\Columns\TextColumn::make('distance_km')
                    ->label('Distance (KM)')
                    ->sortable(),
                \Filament\Tables\Columns\TextColumn::make('travel_time_minutes')
                    ->label('Time (Mins)')
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
