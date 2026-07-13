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
                \Filament\Forms\Components\Select::make('room_definition_id')
                    ->relationship('roomDefinition', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->unique(modifyRuleUsing: function (\Illuminate\Validation\Rules\Unique $rule, \Filament\Resources\RelationManagers\RelationManager $livewire) {
                        return $rule->where('property_id', $livewire->getOwnerRecord()->id);
                    }, ignoreRecord: true),
                \Filament\Forms\Components\TextInput::make('custom_name')
                    ->maxLength(255),
                \Filament\Forms\Components\TextInput::make('floor')
                    ->numeric(),
                \Filament\Forms\Components\TextInput::make('area')
                    ->numeric(),
                \Filament\Forms\Components\Textarea::make('description'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('roomDefinition.roomType.name')
                    ->label('Type')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('roomDefinition.name')
                    ->label('Definition')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('custom_name')
                    ->label('Custom Name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('floor')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                \Filament\Actions\Action::make('addRooms')
                    ->label('Add Rooms')
                    ->icon('heroicon-o-plus')
                    ->form([
                        \Filament\Forms\Components\Select::make('room_type_id')
                            ->label('Room Type')
                            ->options(\App\Domain\Property\Models\RoomType::query()->pluck('name', 'id'))
                            ->live()
                            ->afterStateUpdated(fn ($set) => $set('room_definition_ids', [])),
                        
                        \Filament\Forms\Components\CheckboxList::make('room_definition_ids')
                            ->label('Room Definitions')
                            ->options(fn ($get) => \App\Domain\Property\Models\RoomDefinition::query()
                                ->where('room_type_id', $get('room_type_id'))
                                ->pluck('name', 'id')
                            )
                            ->disableOptionWhen(fn (string $value, \Filament\Resources\RelationManagers\RelationManager $livewire) => 
                                $livewire->getOwnerRecord()->rooms()->where('room_definition_id', $value)->exists()
                            )
                            ->visible(fn ($get) => filled($get('room_type_id')))
                            ->columns(2)
                            ->bulkToggleable()
                            ->hintAction(
                                \Filament\Actions\Action::make('createDefinition')
                                    ->label('Add New Definition')
                                    ->icon('heroicon-m-plus')
                                    ->form([
                                        \Filament\Forms\Components\TextInput::make('name')
                                            ->required()
                                            ->maxLength(255),
                                    ])
                                    ->action(function (array $data, $get, $set) {
                                        $def = \App\Domain\Property\Models\RoomDefinition::create([
                                            'room_type_id' => $get('room_type_id'),
                                            'name' => $data['name'],
                                            'slug' => \Illuminate\Support\Str::slug($data['name'] . '-' . uniqid()),
                                        ]);
                                        
                                        $current = $get('room_definition_ids') ?? [];
                                        $current[] = (string) $def->id;
                                        $set('room_definition_ids', $current);
                                    })
                            ),
                    ])
                    ->action(function (array $data, \Filament\Resources\RelationManagers\RelationManager $livewire) {
                        $property = $livewire->getOwnerRecord();
                        $definitionIds = $data['room_definition_ids'] ?? [];
                        foreach ($definitionIds as $defId) {
                            $property->rooms()->firstOrCreate([
                                'room_definition_id' => $defId,
                            ]);
                        }
                        \Filament\Notifications\Notification::make()
                            ->title('Rooms added successfully')
                            ->success()
                            ->send();
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
