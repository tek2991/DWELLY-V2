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
use Filament\Tables;
use Filament\Tables\Table;

class RoomsRelationManager extends RelationManager
{
    protected static string $relationship = 'rooms';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Forms\Components\Select::make('room_type_id')
                    ->relationship('roomType', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->unique(modifyRuleUsing: function (\Illuminate\Validation\Rules\Unique $rule, \Filament\Resources\RelationManagers\RelationManager $livewire) {
                        return $rule->where('property_id', $livewire->getOwnerRecord()->id);
                    }, ignoreRecord: true)
                    ->createOptionForm([
                        \Filament\Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                    ]),
                \Filament\Forms\Components\TextInput::make('count')
                    ->required()
                    ->numeric()
                    ->default(1)
                    ->minValue(1),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('room_type_id')
            ->columns([
                Tables\Columns\TextColumn::make('roomType.name')
                    ->label('Room Type')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('count')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make(),
                \Filament\Actions\Action::make('bulkCreate')
                    ->label('Bulk Create')
                    ->icon('heroicon-o-squares-plus')
                    ->form(function () {
                        $types = \App\Domain\Property\Models\RoomType::where('is_active', true)->get();
                        $schema = [];
                        foreach ($types as $type) {
                            $schema[] = \Filament\Forms\Components\TextInput::make("type_{$type->id}")
                                ->label($type->name)
                                ->numeric()
                                ->default(0)
                                ->minValue(0);
                        }
                        return [
                            \Filament\Schemas\Components\Grid::make(3)->schema($schema)
                        ];
                    })
                    ->action(function (array $data, \Filament\Resources\RelationManagers\RelationManager $livewire) {
                        $property = $livewire->getOwnerRecord();
                        foreach ($data as $key => $count) {
                            if (str_starts_with($key, 'type_') && $count > 0) {
                                $typeId = substr($key, 5);
                                $existing = $property->rooms()->where('room_type_id', $typeId)->first();
                                if ($existing) {
                                    $existing->increment('count', $count);
                                } else {
                                    $property->rooms()->create([
                                        'room_type_id' => $typeId,
                                        'count' => $count,
                                    ]);
                                }
                            }
                        }
                        \Filament\Notifications\Notification::make()->title('Rooms created successfully')->success()->send();
                    }),
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
