<?php

namespace App\Filament\Resources\Settings;

use App\Domain\Property\Models\EstablishmentType;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class EstablishmentTypeResource extends Resource
{
    protected static ?string $model = EstablishmentType::class;

    protected static ?string $modelLabel = 'Establishment Type';
    protected static ?string $pluralModelLabel = 'Establishment Types';
    protected static ?string $navigationLabel = 'Establishment Types';

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-building-office';
    
    protected static \UnitEnum|string|null $navigationGroup = 'Reference Data';
    
    protected static ?int $navigationSort = 17;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\TextInput::make('slug')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Textarea::make('description')
                    ->maxLength(65535)
                    ->columnSpanFull(),
                Forms\Components\Toggle::make('is_active')
                    ->default(true)
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('slug')
                    ->searchable(),
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                \Filament\Actions\EditAction::make(),
            ])
            ->bulkActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Resources\Settings\EstablishmentTypeResource\Pages\ManageEstablishmentTypes::route('/'),
        ];
    }
}
