<?php

namespace Tek2991\Accounting\Filament\Resources;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Tek2991\Accounting\Models\State;

class StateResource extends Resource
{
    protected static ?string $model = State::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-map';
    protected static \UnitEnum|string|null $navigationGroup = 'Settings';
    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('code')
                    ->required()
                    ->maxLength(10),
                Forms\Components\TextInput::make('country_id')
                    ->maxLength(2),
                Forms\Components\TextInput::make('gst_state_code')
                    ->maxLength(2),
                Forms\Components\Toggle::make('is_union_territory')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('code')
                    ->searchable(),
                Tables\Columns\TextColumn::make('country_id')
                    ->searchable(),
                Tables\Columns\TextColumn::make('gst_state_code')
                    ->searchable(),
                Tables\Columns\IconColumn::make('is_union_territory')
                    ->boolean(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => \Tek2991\Accounting\Filament\Resources\StateResource\Pages\ListStates::route('/'),
            'create' => \Tek2991\Accounting\Filament\Resources\StateResource\Pages\CreateState::route('/create'),
            'edit' => \Tek2991\Accounting\Filament\Resources\StateResource\Pages\EditState::route('/{record}/edit'),
        ];
    }
}
