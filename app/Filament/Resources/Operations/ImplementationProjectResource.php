<?php

namespace App\Filament\Resources\Operations;

use App\Domain\Implementation\Models\ImplementationProject;
use App\Filament\Resources\Operations\ImplementationProjectResource\Pages;
use Filament\Schemas\Schema;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ImplementationProjectResource extends Resource
{
    protected static ?string $model = ImplementationProject::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-clipboard-document-check';
    
    protected static \UnitEnum|string|null $navigationGroup = 'Portfolio & Operations';
    
    protected static ?string $modelLabel = 'Implementation Project';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Select::make('workflow_template_id')
                    ->relationship('workflowTemplate', 'name')
                    ->required(),
                Forms\Components\Select::make('status')
                    ->options([
                        'created' => 'Created',
                        'in_progress' => 'In Progress',
                        'on_hold' => 'On Hold',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                    ])
                    ->required(),
                Forms\Components\DatePicker::make('target_completion_date'),
                Forms\Components\Select::make('manager_id')
                    ->relationship('manager', 'name'),
                Forms\Components\TextInput::make('progress')
                    ->numeric()
                    ->default(0),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('workflowTemplate.name')
                    ->label('Template')
                    ->sortable(),
                Tables\Columns\TextColumn::make('entity.id') // In a real app we'd want a polymorphic display, but this is a start
                    ->label('Entity ID')
                    ->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'created' => 'gray',
                        'in_progress' => 'warning',
                        'on_hold' => 'danger',
                        'completed' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('progress')
                    ->formatStateUsing(fn ($state) => $state . '%')
                    ->sortable(),
                Tables\Columns\TextColumn::make('target_completion_date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('manager.name')
                    ->label('Manager')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                \Filament\Actions\Action::make('workspace')
                    ->label('Workspace')
                    ->icon('heroicon-m-squares-2x2')
                    ->url(fn (ImplementationProject $record): string => static::getUrl('workspace', ['record' => $record])),
                \Filament\Actions\EditAction::make(),
            ])
            ->bulkActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListImplementationProjects::route('/'),
            'workspace' => Pages\ManageWorkspace::route('/{record}/workspace'),
        ];
    }
}
