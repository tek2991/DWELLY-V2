<?php

namespace App\Filament\Resources\Properties\RelationManagers;

use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PropertyDocumentsRelationManager extends RelationManager
{
    protected static string $relationship = 'documents';

    protected static ?string $title = 'Uploaded Documents';
    protected static ?string $modelLabel = 'Uploaded Document';

    public function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Forms\Components\Select::make('property_room_id')
                    ->label('Room (Optional)')
                    ->relationship('room', 'id', modifyQueryUsing: function ($query, \Filament\Resources\RelationManagers\RelationManager $livewire) {
                        return $query->where('property_id', $livewire->getOwnerRecord()->id);
                    })
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->custom_name ?: ($record->roomDefinition ? $record->roomDefinition->name : 'Room ' . $record->id))
                    ->nullable()
                    ->searchable()
                    ->preload()
                    ->columnSpanFull(),
                Forms\Components\Select::make('document_category')
                    ->label('Category')
                    ->options([
                        'Owner Documents' => 'Owner Documents',
                        'Property Documents' => 'Property Documents',
                        'Compliance Documents' => 'Compliance Documents',
                        'Financial Documents' => 'Financial Documents',
                    ])
                    ->required()
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('title')
                    ->label('Title')
                    ->maxLength(255)
                    ->columnSpanFull(),
                Forms\Components\FileUpload::make('file_path')
                    ->label('Document File')
                    ->directory('property-documents')
                    ->required()
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                Tables\Columns\TextColumn::make('document_category')
                    ->label('Category')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Owner Documents' => 'primary',
                        'Property Documents' => 'info',
                        'Compliance Documents' => 'warning',
                        'Financial Documents' => 'success',
                        default => 'gray',
                    })
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('title')
                    ->label('Title')
                    ->sortable()
                    ->searchable()
                    ->placeholder('No Title'),
                Tables\Columns\TextColumn::make('room.custom_name')
                    ->label('Room')
                    ->getStateUsing(fn ($record) => $record->room ? ($record->room->custom_name ?: ($record->room->roomDefinition ? $record->room->roomDefinition->name : 'Room')) : 'General')
                    ->searchable()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('document_category')
                    ->label('Category')
                    ->options([
                        'Owner Documents' => 'Owner Documents',
                        'Property Documents' => 'Property Documents',
                        'Compliance Documents' => 'Compliance Documents',
                        'Financial Documents' => 'Financial Documents',
                    ]),
            ])
            ->headerActions([
                \Filament\Actions\CreateAction::make(),
            ])
            ->recordActions([
                \Filament\Actions\Action::make('download')
                    ->label('Download')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(function ($record) {
                        return response()->download(storage_path('app/public/' . $record->file_path), $record->title ?? 'document');
                    }),
                \Filament\Actions\EditAction::make(),
                \Filament\Actions\DeleteAction::make(),
            ])
            ->toolbarActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
