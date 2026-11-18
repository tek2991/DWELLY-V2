<?php

namespace App\Filament\Resources\Properties\RelationManagers;

use App\Filament\Resources\Properties\RelationManagers\Traits\LocksDuringPropertyOnboarding;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;

class PhotosRelationManager extends RelationManager
{
    use LocksDuringPropertyOnboarding;

    protected static string $relationship = 'photos';

    protected static ?string $recordTitleAttribute = 'title';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('property_room_id')
                    ->label('Room (Optional)')
                    ->relationship('room', 'id', modifyQueryUsing: function ($query, RelationManager $livewire) {
                        return $query->where('property_id', $livewire->getOwnerRecord()->id);
                    })
                    ->getOptionLabelFromRecordUsing(fn($record) => $record->custom_name ?: ($record->roomDefinition ? $record->roomDefinition->name : 'Room ' . $record->id))
                    ->nullable()
                    ->searchable()
                    ->preload()
                    ->columnSpanFull(),
                Forms\Components\FileUpload::make('file_path')
                    ->label('Photo')
                    ->image()
                    ->disk('public')
                    ->visibility('public')
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
                    ->getStateUsing(fn($record) => $record->room ? ($record->room->custom_name ?: ($record->room->roomDefinition ? $record->room->roomDefinition->name : 'Room')) : 'General')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\ViewColumn::make('file_path')
                    ->label('Photo')
                    ->view('filament.tables.columns.property-photo-column'),
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
                Action::make('browseGallery')
                    ->label('Browse Full Gallery')
                    ->icon('heroicon-o-photo')
                    ->color('gray')
                    ->modalHeading(fn(RelationManager $livewire) => 'Photo Gallery: ' . ($livewire->getOwnerRecord()?->name ?? 'Property Photos'))
                    ->modalDescription('Browse all property photos. Click any photo thumbnail to open fullscreen swipe lightbox.')
                    ->modalWidth('4xl')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->schema([
                        View::make('filament.forms.components.property-photos-lightbox'),
                    ]),
                CreateAction::make(),
            ])
            ->recordActions([
                Action::make('inspectGallery')
                    ->label('Gallery')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->modalHeading(fn($record) => 'Photo Gallery: ' . ($record->title ?: 'Property Photo'))
                    ->modalDescription(fn($record) => ($record->room ? 'Room: ' . ($record->room->custom_name ?: ($record->room->roomDefinition?->name ?? 'Room')) : 'General') . ' &bull; Click thumbnail to open fullscreen swipe lightbox.')
                    ->modalWidth('4xl')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->schema([
                        View::make('filament.forms.components.property-photos-lightbox'),
                    ]),
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
