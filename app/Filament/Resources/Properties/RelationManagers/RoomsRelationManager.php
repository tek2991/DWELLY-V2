<?php

namespace App\Filament\Resources\Properties\RelationManagers;

use App\Domain\Property\Models\RoomDefinition;
use App\Domain\Property\Models\RoomType;
use App\Filament\Resources\Properties\RelationManagers\Traits\LocksDuringPropertyOnboarding;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Unique;

class RoomsRelationManager extends RelationManager
{
    use LocksDuringPropertyOnboarding;

    protected static string $relationship = 'rooms';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('room_definition_id')
                    ->relationship('roomDefinition', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->unique(modifyRuleUsing: function (Unique $rule, RelationManager $livewire) {
                        return $rule->where('property_id', $livewire->getOwnerRecord()->id);
                    }, ignoreRecord: true),
                TextInput::make('custom_name')
                    ->maxLength(255),
                TextInput::make('floor')
                    ->numeric(),
                TextInput::make('area')
                    ->numeric(),
                Textarea::make('description'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('roomDefinition.roomType.name')
                    ->label('Type')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('roomDefinition.name')
                    ->label('Definition')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('custom_name')
                    ->label('Custom Name')
                    ->searchable(),
                TextColumn::make('floor')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Action::make('addRooms')
                    ->hidden(fn (RelationManager $livewire) => $livewire->isReadOnly())
                    ->label('Add Rooms')
                    ->icon('heroicon-o-plus')
                    ->form([
                        Select::make('room_type_id')
                            ->label('Room Type')
                            ->options(RoomType::query()->pluck('name', 'id'))
                            ->live()
                            ->afterStateUpdated(fn ($set) => $set('room_definition_ids', [])),

                        CheckboxList::make('room_definition_ids')
                            ->label('Room Definitions')
                            ->options(fn ($get) => RoomDefinition::query()
                                ->where('room_type_id', $get('room_type_id'))
                                ->pluck('name', 'id')
                            )
                            ->disableOptionWhen(fn (string $value, RelationManager $livewire) => $livewire->getOwnerRecord()->rooms()->where('room_definition_id', $value)->exists()
                            )
                            ->visible(fn ($get) => filled($get('room_type_id')))
                            ->columns(2)
                            ->bulkToggleable()
                            ->hintAction(
                                Action::make('createDefinition')
                                    ->label('Add New Definition')
                                    ->icon('heroicon-m-plus')
                                    ->form([
                                        TextInput::make('name')
                                            ->required()
                                            ->maxLength(255),
                                    ])
                                    ->action(function (array $data, $get, $set) {
                                        $def = RoomDefinition::create([
                                            'room_type_id' => $get('room_type_id'),
                                            'name' => $data['name'],
                                            'slug' => Str::slug($data['name'].'-'.uniqid()),
                                        ]);

                                        $current = $get('room_definition_ids') ?? [];
                                        $current[] = (string) $def->id;
                                        $set('room_definition_ids', $current);
                                    })
                            ),
                    ])
                    ->action(function (array $data, RelationManager $livewire) {
                        $property = $livewire->getOwnerRecord();
                        $definitionIds = $data['room_definition_ids'] ?? [];
                        foreach ($definitionIds as $defId) {
                            $property->rooms()->firstOrCreate([
                                'room_definition_id' => $defId,
                            ]);
                        }
                        Notification::make()
                            ->title('Rooms added successfully')
                            ->success()
                            ->send();

                        $livewire->dispatch('refresh-onboarding-progress');
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
