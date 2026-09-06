<?php

namespace App\Filament\Resources\Properties\RelationManagers;

use App\Filament\Resources\Properties\RelationManagers\Traits\LocksDuringPropertyOnboarding;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class UtilitiesRelationManager extends RelationManager
{
    use LocksDuringPropertyOnboarding;

    protected static string $relationship = 'utilities';

    protected static ?string $title = 'Utilities';

    protected static ?string $modelLabel = 'Utility Configuration';

    public function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Forms\Components\Select::make('utility_type_id')
                    ->label('Utility Type')
                    ->relationship('utilityType', 'name')
                    ->required()
                    ->searchable()
                    ->preload(),
                Forms\Components\Select::make('paid_by')
                    ->label('Paid By')
                    ->options([
                        'owner' => 'Owner',
                        'tenant' => 'Tenant',
                        'dwelly' => 'Dwelly',
                    ])
                    ->required(),
                Forms\Components\TextInput::make('amount')
                    ->label('Amount / Rate')
                    ->numeric()
                    ->prefix('₹')
                    ->nullable(),
                Forms\Components\DatePicker::make('effective_from')
                    ->label('Effective From')
                    ->required()
                    ->default(now()),
                Forms\Components\DatePicker::make('effective_to')
                    ->label('Effective To')
                    ->nullable()
                    ->helperText('Leave empty if this is the current active configuration'),
                Forms\Components\TextInput::make('details')
                    ->label('Details / Meter Number')
                    ->maxLength(255)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('details')
            ->columns([
                Tables\Columns\TextColumn::make('utilityType.name')
                    ->label('Utility')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('paid_by')
                    ->label('Paid By')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => ucfirst($state))
                    ->color(fn (string $state): string => match ($state) {
                        'owner' => 'info',
                        'tenant' => 'success',
                        'dwelly' => 'primary',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('amount')
                    ->label('Rate')
                    ->money('INR')
                    ->sortable(),
                Tables\Columns\TextColumn::make('effective_from')
                    ->label('From')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('effective_to')
                    ->label('To')
                    ->date()
                    ->placeholder('Active')
                    ->sortable(),
                Tables\Columns\TextColumn::make('details')
                    ->label('Details')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('effective_from', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('utility_type_id')
                    ->relationship('utilityType', 'name')
                    ->label('Utility Type'),
                Tables\Filters\SelectFilter::make('paid_by')
                    ->options([
                        'owner' => 'Owner',
                        'tenant' => 'Tenant',
                        'dwelly' => 'Dwelly',
                    ]),
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
