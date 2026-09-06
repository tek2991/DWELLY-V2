<?php

namespace App\Filament\Resources\Properties\RelationManagers;

use App\Domain\Property\Models\AmenityType;
use App\Filament\Resources\Properties\RelationManagers\Traits\LocksDuringPropertyOnboarding;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
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
use Illuminate\Validation\Rules\Unique;

class AmenitiesRelationManager extends RelationManager
{
    use LocksDuringPropertyOnboarding;

    protected static string $relationship = 'amenities';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('amenity_type_id')
                    ->relationship('amenityType', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->unique(modifyRuleUsing: function (Unique $rule, RelationManager $livewire) {
                        return $rule->where('property_id', $livewire->getOwnerRecord()->id);
                    }, ignoreRecord: true)
                    ->createOptionForm([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                    ]),
                Textarea::make('notes')
                    ->maxLength(65535)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('amenity_type_id')
            ->columns([
                TextColumn::make('amenityType.name')
                    ->label('Amenity')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('notes')
                    ->limit(50)
                    ->searchable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make(),
                Action::make('bulkCreate')
                    ->label('Bulk Create')
                    ->icon('heroicon-o-squares-plus')
                    ->form(function () {
                        $types = AmenityType::where('is_active', true)->get();

                        return [
                            CheckboxList::make('amenities')
                                ->options($types->pluck('name', 'id'))
                                ->columns(3),
                        ];
                    })
                    ->action(function (array $data, RelationManager $livewire) {
                        $property = $livewire->getOwnerRecord();
                        foreach ($data['amenities'] as $typeId) {
                            $existing = $property->amenities()->where('amenity_type_id', $typeId)->first();
                            if (! $existing) {
                                $property->amenities()->create([
                                    'amenity_type_id' => $typeId,
                                ]);
                            }
                        }
                        Notification::make()->title('Amenities added successfully')->success()->send();
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
