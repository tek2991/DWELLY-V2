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

class InventoriesRelationManager extends RelationManager
{
    protected static string $relationship = 'inventories';

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
                    ->preload(),
                \Filament\Forms\Components\Select::make('inventory_type_id')
                    ->relationship('inventoryType', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->unique(modifyRuleUsing: function (\Illuminate\Validation\Rules\Unique $rule, \Filament\Resources\RelationManagers\RelationManager $livewire, callable $get) {
                        $rule->where('property_id', $livewire->getOwnerRecord()->id);
                        if (blank($get('property_room_id'))) {
                            $rule->whereNull('property_room_id');
                        } else {
                            $rule->where('property_room_id', $get('property_room_id'));
                        }
                        return $rule;
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
            ->recordTitleAttribute('inventory_type_id')
            ->columns([
                \Filament\Tables\Columns\TextColumn::make('room.custom_name')
                    ->label('Room')
                    ->getStateUsing(fn ($record) => $record->room ? ($record->room->custom_name ?: ($record->room->roomDefinition ? $record->room->roomDefinition->name : 'Room')) : 'Unassigned')
                    ->searchable()
                    ->sortable(),
                \Filament\Tables\Columns\TextColumn::make('inventoryType.name')
                    ->label('Item')
                    ->searchable()
                    ->sortable(),
                \Filament\Tables\Columns\TextColumn::make('count')
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
                    ->form(function (\Filament\Resources\RelationManagers\RelationManager $livewire) {
                        $types = \App\Domain\Property\Models\InventoryType::where('is_active', true)->get();
                        
                        $schema = [
                            \Filament\Forms\Components\Select::make('property_room_id')
                                ->label('Room (Optional)')
                                ->options(function () use ($livewire) {
                                    return \App\Domain\Property\Models\PropertyRoom::with('roomDefinition')
                                        ->where('property_id', $livewire->getOwnerRecord()->id)
                                        ->get()
                                        ->mapWithKeys(function ($room) {
                                            return [$room->id => $room->custom_name ?: ($room->roomDefinition ? $room->roomDefinition->name : 'Room ' . $room->id)];
                                        });
                                })
                                ->nullable()
                                ->searchable()
                                ->preload()
                                ->columnSpanFull(),
                        ];
                        
                        $gridSchema = [];
                        foreach ($types as $type) {
                            $gridSchema[] = \Filament\Forms\Components\TextInput::make("type_{$type->id}")
                                ->label($type->name)
                                ->numeric()
                                ->default(0)
                                ->minValue(0)
                                ->rules([
                                    function (\Filament\Resources\RelationManagers\RelationManager $livewire, callable $get) use ($type) {
                                        return function (string $attribute, $value, \Closure $fail) use ($livewire, $get, $type) {
                                            if ((int) $value > 0) {
                                                $roomId = $get('property_room_id');
                                                $query = $livewire->getOwnerRecord()->inventories()->where('inventory_type_id', $type->id);
                                                
                                                if ($roomId) {
                                                    $query->where('property_room_id', $roomId);
                                                } else {
                                                    $query->whereNull('property_room_id');
                                                }
                                                
                                                if ($query->exists()) {
                                                    $fail('Already exists. Use the edit action to update.');
                                                }
                                            }
                                        };
                                    },
                                ]);
                        }
                        $schema[] = \Filament\Schemas\Components\Grid::make(3)->schema($gridSchema);
                        
                        return $schema;
                    })
                    ->action(function (array $data, \Filament\Resources\RelationManagers\RelationManager $livewire) {
                        $property = $livewire->getOwnerRecord();
                        $roomId = $data['property_room_id'] ?? null;
                        
                        foreach ($data as $key => $count) {
                            if (str_starts_with($key, 'type_') && $count > 0) {
                                $typeId = substr($key, 5);
                                $property->inventories()->create([
                                    'inventory_type_id' => $typeId,
                                    'property_room_id' => $roomId,
                                    'count' => $count,
                                ]);
                            }
                        }
                        \Filament\Notifications\Notification::make()->title('Inventories created successfully')->success()->send();
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
