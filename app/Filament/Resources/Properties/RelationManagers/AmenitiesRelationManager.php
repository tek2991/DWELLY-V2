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

class AmenitiesRelationManager extends RelationManager
{
    protected static string $relationship = 'amenities';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Forms\Components\Select::make('amenity_type_id')
                    ->relationship('amenityType', 'name')
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
                \Filament\Forms\Components\Textarea::make('notes')
                    ->maxLength(65535)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('amenity_type_id')
            ->columns([
                \Filament\Tables\Columns\TextColumn::make('amenityType.name')
                    ->label('Amenity')
                    ->searchable()
                    ->sortable(),
                \Filament\Tables\Columns\TextColumn::make('notes')
                    ->limit(50)
                    ->searchable(),
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
                        $types = \App\Domain\Property\Models\AmenityType::where('is_active', true)->get();
                        return [
                            \Filament\Forms\Components\CheckboxList::make('amenities')
                                ->options($types->pluck('name', 'id'))
                                ->columns(3)
                        ];
                    })
                    ->action(function (array $data, \Filament\Resources\RelationManagers\RelationManager $livewire) {
                        $property = $livewire->getOwnerRecord();
                        foreach ($data['amenities'] as $typeId) {
                            $existing = $property->amenities()->where('amenity_type_id', $typeId)->first();
                            if (!$existing) {
                                $property->amenities()->create([
                                    'amenity_type_id' => $typeId,
                                ]);
                            }
                        }
                        \Filament\Notifications\Notification::make()->title('Amenities added successfully')->success()->send();
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
