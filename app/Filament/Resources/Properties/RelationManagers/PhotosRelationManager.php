<?php

namespace App\Filament\Resources\Properties\RelationManagers;

use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;

class PhotosRelationManager extends RelationManager
{
    protected static string $relationship = 'photos';

    protected static ?string $recordTitleAttribute = 'title';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Forms\Components\Select::make('property_room_id')
                    ->label('Room (Optional)')
                    ->relationship('room', 'id', modifyQueryUsing: function ($query, \Filament\Resources\RelationManagers\RelationManager $livewire) {
                        return $query->where('property_id', $livewire->getOwnerRecord()->id);
                    })
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->custom_name ?: ($record->roomDefinition ? $record->roomDefinition->name : 'Room ' . $record->id))
                    ->nullable()
                    ->searchable()
                    ->preload()
                    ->columnSpanFull(),
                Forms\Components\FileUpload::make('file_path')
                    ->label('Photo')
                    ->image()
                    ->directory('property-photos')
                    ->required()
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('title')
                    ->maxLength(255),
                Forms\Components\Toggle::make('is_featured')
                    ->label('Featured Image')
                    ->default(false),
                Forms\Components\Toggle::make('is_visible')
                    ->label('Show on Website')
                    ->default(true),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                Tables\Columns\TextColumn::make('room.custom_name')
                    ->label('Room')
                    ->getStateUsing(fn ($record) => $record->room ? ($record->room->custom_name ?: ($record->room->roomDefinition ? $record->room->roomDefinition->name : 'Room')) : 'General')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\ImageColumn::make('file_path')
                    ->label('Photo')
                    ->height(100)
                    ->square(),
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->placeholder('No Title'),
                Tables\Columns\ToggleColumn::make('is_featured')
                    ->label('Featured'),
                Tables\Columns\ToggleColumn::make('is_visible')
                    ->label('Visible'),
            ])
            ->reorderable('order_column')
            ->defaultSort('order_column')
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
