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

class EstablishmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'establishments';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Forms\Components\Select::make('establishment_id')
                    ->relationship('establishment', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->unique(modifyRuleUsing: function (\Illuminate\Validation\Rules\Unique $rule, \Filament\Resources\RelationManagers\RelationManager $livewire) {
                        return $rule->where('property_id', $livewire->getOwnerRecord()->id);
                    }, ignoreRecord: true)
                    ->createOptionForm([
                        \Filament\Forms\Components\Select::make('establishment_type_id')
                            ->options(fn() => \Illuminate\Support\Facades\DB::table('establishment_types')->pluck('name', 'id'))
                            ->required(),
                        \Filament\Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                    ]),
                \Filament\Forms\Components\TextInput::make('distance_km')
                    ->numeric()
                    ->label('Distance (KM)'),
                \Filament\Forms\Components\TextInput::make('travel_time_minutes')
                    ->numeric()
                    ->label('Travel Time (Mins)'),
                \Filament\Forms\Components\Textarea::make('remarks')
                    ->maxLength(65535)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('establishment_id')
            ->columns([
                \Filament\Tables\Columns\TextColumn::make('establishment.name')
                    ->label('Establishment Name')
                    ->searchable()
                    ->sortable(),
                \Filament\Tables\Columns\TextColumn::make('distance_km')
                    ->label('Distance (KM)')
                    ->sortable(),
                \Filament\Tables\Columns\TextColumn::make('travel_time_minutes')
                    ->label('Time (Mins)')
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
                        $establishments = \App\Domain\Property\Models\Establishment::with('establishmentType')->get();
                        
                        $schema = [
                            \Filament\Forms\Components\Placeholder::make('h_name')->hiddenLabel()->content(new \Illuminate\Support\HtmlString('<strong>Establishment Name</strong>')),
                            \Filament\Forms\Components\Placeholder::make('h_type')->hiddenLabel()->content(new \Illuminate\Support\HtmlString('<strong>Type</strong>')),
                            \Filament\Forms\Components\Placeholder::make('h_dist')->hiddenLabel()->content(new \Illuminate\Support\HtmlString('<strong>Distance (KM)</strong>')),
                            \Filament\Forms\Components\Placeholder::make('h_time')->hiddenLabel()->content(new \Illuminate\Support\HtmlString('<strong>Time (Mins)</strong>')),
                        ];

                        foreach ($establishments as $est) {
                            $schema[] = \Filament\Forms\Components\Placeholder::make("label_{$est->id}")
                                ->hiddenLabel()
                                ->content($est->name);
                                
                            $schema[] = \Filament\Forms\Components\Placeholder::make("type_{$est->id}")
                                ->hiddenLabel()
                                ->content($est->establishmentType?->name ?? '-');
                                
                            $schema[] = \Filament\Forms\Components\TextInput::make("est_{$est->id}_dist")
                                ->hiddenLabel()
                                ->numeric()
                                ->placeholder("0.0");
                                
                            $schema[] = \Filament\Forms\Components\TextInput::make("est_{$est->id}_time")
                                ->hiddenLabel()
                                ->numeric()
                                ->placeholder("0");
                        }

                        return [
                            \Filament\Schemas\Components\Grid::make(4)->schema($schema)
                        ];
                    })
                    ->action(function (array $data, \Filament\Resources\RelationManagers\RelationManager $livewire) {
                        $property = $livewire->getOwnerRecord();
                        $establishments = \App\Domain\Property\Models\Establishment::all();
                        
                        $addedCount = 0;
                        foreach ($establishments as $est) {
                            $dist = $data["est_{$est->id}_dist"] ?? null;
                            $time = $data["est_{$est->id}_time"] ?? null;
                            
                            // If user entered any distance or time data for this establishment
                            if ($dist !== null || $time !== null) {
                                $existing = $property->establishments()->where('establishment_id', $est->id)->first();
                                if (!$existing) {
                                    $property->establishments()->create([
                                        'establishment_id' => $est->id,
                                        'distance_km' => $dist,
                                        'travel_time_minutes' => $time,
                                    ]);
                                    $addedCount++;
                                } else {
                                    $existing->update([
                                        'distance_km' => $dist ?? $existing->distance_km,
                                        'travel_time_minutes' => $time ?? $existing->travel_time_minutes,
                                    ]);
                                }
                            }
                        }
                        if ($addedCount > 0) {
                            \Filament\Notifications\Notification::make()->title("{$addedCount} Establishments mapped successfully")->success()->send();
                        } else {
                            \Filament\Notifications\Notification::make()->title("Establishment mappings updated")->success()->send();
                        }
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
