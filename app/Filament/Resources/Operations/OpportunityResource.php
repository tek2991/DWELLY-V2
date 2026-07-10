<?php

namespace App\Filament\Resources\Operations;

use App\Domain\Opportunity\Models\Opportunity;
use App\Domain\Opportunity\Enums\OpportunityStatus;
use App\Filament\Resources\Operations\OpportunityResource\Pages;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use App\Domain\Opportunity\Services\OpportunityWorkflowService;
use App\Domain\Opportunity\Services\OpportunityReadinessService;
use App\Domain\Mou\Models\Mou;
use App\Filament\Resources\Operations\MOUResource;

class OpportunityResource extends Resource
{
    protected static ?string $model = Opportunity::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-briefcase';
    
    protected static \UnitEnum|string|null $navigationGroup = 'Sales & CRM';
    
    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                \Filament\Schemas\Components\Group::make()->schema([
                    \Filament\Schemas\Components\Section::make('Basic Information')
                        ->schema([
                            Forms\Components\TextInput::make('title')
                                ->required()
                                ->maxLength(255),
                            Forms\Components\Select::make('opportunity_source_id')
                                ->relationship('opportunitySource', 'name')
                                ->searchable()
                                ->preload(),
                            Forms\Components\Select::make('assigned_user_id')
                                ->relationship('assignedUser', 'name')
                                ->searchable()
                                ->preload()
                                ->default(fn () => auth()->id()),
                        ])->columns(2),

                    \Filament\Schemas\Components\Section::make('Property Estimates')
                        ->schema([
                            Forms\Components\Select::make('estimated_property_type_id')
                                ->relationship('estimatedPropertyType', 'name')
                                ->searchable()
                                ->preload()
                                ->disabled(fn (?Opportunity $record) => $record && $record->status === OpportunityStatus::READY_FOR_MOU),
                            Forms\Components\Select::make('estimated_bhk')
                                ->options([
                                    '1 RK' => '1 RK',
                                    '1 BHK' => '1 BHK',
                                    '2 BHK' => '2 BHK',
                                    '3 BHK' => '3 BHK',
                                    '4 BHK' => '4 BHK',
                                    '5+ BHK' => '5+ BHK',
                                    'Villa' => 'Villa',
                                    'Independent House' => 'Independent House',
                                ])
                                ->searchable(),
                            Forms\Components\TextInput::make('estimated_size')
                                ->numeric(),
                            Forms\Components\Toggle::make('estimated_is_furnished'),
                        ])->columns(2),

                    \Filament\Schemas\Components\Section::make('Commercials & Dates')
                        ->schema([
                            Forms\Components\TextInput::make('expected_rent')
                                ->numeric()
                                ->prefix('₹')
                                ->disabled(fn (?Opportunity $record) => $record && $record->status === OpportunityStatus::READY_FOR_MOU),
                            Forms\Components\Select::make('expected_financial_model_id')
                                ->relationship('expectedFinancialModel', 'name')
                                ->searchable()
                                ->preload(),
                            Forms\Components\DatePicker::make('expected_onboarding_date'),
                        ])->columns(2),
                        
                    \Filament\Schemas\Components\Section::make('Internal Summary')
                        ->schema([
                            Forms\Components\Textarea::make('internal_summary')
                                ->maxLength(65535)
                                ->columnSpanFull(),
                        ]),
                ])->columnSpan(['lg' => 2]),

                \Filament\Schemas\Components\Group::make()->schema([
                    \Filament\Schemas\Components\Section::make('Owner Information')
                        ->schema([
                            Forms\Components\TextInput::make('owner_name')
                                ->label('Owner Name')
                                ->maxLength(255)
                                ->required(),
                            Forms\Components\TextInput::make('owner_phone')
                                ->label('Owner Phone')
                                ->tel()
                                ->maxLength(255),
                            Forms\Components\TextInput::make('owner_email')
                                ->label('Owner Email')
                                ->email()
                                ->maxLength(255),
                            
                            Forms\Components\Textarea::make('address')
                                ->label('Address')
                                ->columnSpanFull(),
                        ]),
                        
                    \Filament\Schemas\Components\Section::make('Status')
                        ->schema([
                            Forms\Components\Placeholder::make('number')
                                ->content(fn (?Opportunity $record): string => $record?->number ?? 'Auto-generated'),
                            Forms\Components\Placeholder::make('status')
                                ->content(fn (?Opportunity $record): string => $record?->status?->getLabel() ?? 'New'),
                        ])->hiddenOn('create'),
                ])->columnSpan(['lg' => 1]),
            ])
            ->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('number')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('title')
                    ->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge(),
                Tables\Columns\TextColumn::make('owner_name')
                    ->label('Owner')
                    ->searchable(),
                Tables\Columns\TextColumn::make('assignedUser.name')
                    ->label('Assigned To'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordUrl(
                fn (Opportunity $record): string => static::getUrl('view', ['record' => $record]),
            )
            ->filters([
                Tables\Filters\TrashedFilter::make(),
                Tables\Filters\SelectFilter::make('status')
                    ->options(OpportunityStatus::class),
            ])
            ->actions([
                \Filament\Actions\ViewAction::make(),
                \Filament\Actions\EditAction::make(),
                // Workflow Actions
                \Filament\Actions\ActionGroup::make([
                    \Filament\Actions\Action::make('markReadyForMou')
                        ->label('Ready For MOU')
                        ->icon('heroicon-o-check-badge')
                        ->color('success')
                        ->visible(fn (Opportunity $record) => $record->status === OpportunityStatus::NEW)
                        ->action(function (Opportunity $record) {
                            $readiness = app(OpportunityReadinessService::class)->canCreateMOU($record);
                            if (!$readiness['is_ready']) {
                                \Filament\Notifications\Notification::make()
                                    ->title('Cannot Mark as Ready')
                                    ->body(implode(' ', $readiness['errors']))
                                    ->danger()
                                    ->send();
                                return;
                            }
                            app(OpportunityWorkflowService::class)->markReadyForMou($record);
                            \Filament\Notifications\Notification::make()->title('Opportunity marked as Ready for MOU')->success()->send();
                        }),

                    \Filament\Actions\Action::make('manageMou')
                        ->label(fn (Opportunity $record) => Mou::where('opportunity_id', $record->id)->exists() ? 'Open MOU' : 'Create MOU')
                        ->icon('heroicon-o-document-text')
                        ->color('primary')
                        ->visible(fn (Opportunity $record) => in_array($record->status, [OpportunityStatus::READY_FOR_MOU, OpportunityStatus::CONVERTED]))
                        ->url(function (Opportunity $record) {
                            $mou = Mou::where('opportunity_id', $record->id)->first();
                            if ($mou) {
                                return MOUResource::getUrl('view', ['record' => $mou]);
                            }
                            return MOUResource::getUrl('create', ['opportunity_id' => $record->id]);
                        }),
                        
                    \Filament\Actions\Action::make('closeLost')
                        ->label('Close Lost')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->visible(fn (Opportunity $record) => !in_array($record->status, [OpportunityStatus::CONVERTED, OpportunityStatus::CLOSED_LOST, OpportunityStatus::CANCELLED, OpportunityStatus::MOU_SIGNED]))
                        ->form([
                            Forms\Components\Textarea::make('notes')->label('Reason for losing'),
                        ])
                        ->action(fn (Opportunity $record, array $data) => app(OpportunityWorkflowService::class)->closeLost($record, $data['notes'] ?? null)),
                ])->label('Actions'),
            ])
            ->bulkActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\DeleteBulkAction::make(),
                    \Filament\Actions\ForceDeleteBulkAction::make(),
                    \Filament\Actions\RestoreBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function infolist(\Filament\Schemas\Schema $schema): \Filament\Schemas\Schema
    {
        return $schema
            ->components([
                \Filament\Schemas\Components\Group::make([
                    \Filament\Schemas\Components\Section::make('Opportunity Details')
                        ->schema([
                            \Filament\Infolists\Components\TextEntry::make('number')->label('Number'),
                            \Filament\Infolists\Components\TextEntry::make('status')
                                ->badge(),
                            \Filament\Infolists\Components\TextEntry::make('assignedUser.name')->label('Assigned To'),
                            \Filament\Infolists\Components\TextEntry::make('mou.property.code')
                                ->label('Associated Property')
                                ->url(fn (Opportunity $record) => $record->mou?->property ? \App\Filament\Resources\Properties\PropertyResource::getUrl('edit', ['record' => $record->mou->property]) : null)
                                ->visible(fn (Opportunity $record) => $record->mou?->property !== null),
                        ])->columns(3),
                        
                    \Filament\Schemas\Components\Section::make('Owner Information')
                        ->schema([
                            \Filament\Infolists\Components\TextEntry::make('owner_name')->label('Name'),
                            \Filament\Infolists\Components\TextEntry::make('owner_phone')->label('Phone'),
                            \Filament\Infolists\Components\TextEntry::make('owner_email')->label('Email'),
                            \Filament\Infolists\Components\TextEntry::make('address')->label('Address')->columnSpanFull(),
                        ])->columns(3),
                        
                    \Filament\Schemas\Components\Section::make('Property Estimate')
                        ->schema([
                            \Filament\Infolists\Components\TextEntry::make('estimatedPropertyType.name')->label('Type'),
                            \Filament\Infolists\Components\TextEntry::make('estimated_bhk')->label('BHK'),
                            \Filament\Infolists\Components\TextEntry::make('estimated_size')->label('Size (Sq.Ft.)'),
                            \Filament\Infolists\Components\IconEntry::make('estimated_is_furnished')->boolean()->label('Furnished'),
                            \Filament\Infolists\Components\TextEntry::make('expected_rent')->money('INR')->label('Expected Rent'),
                            \Filament\Infolists\Components\TextEntry::make('expectedFinancialModel.name')->label('Financial Model'),
                        ])->columns(3),
                ])->columnSpan(['lg' => 2]),

                \Filament\Schemas\Components\Group::make([
                    \Filament\Schemas\Components\Section::make('Activity Timeline')
                        ->schema([
                            \App\Filament\Infolists\Components\ActivityTimeline::make('activities'),
                        ]),
                ])->columnSpan(['lg' => 1]),
            ])->columns(3);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOpportunities::route('/'),
            'create' => Pages\CreateOpportunity::route('/create'),
            'view' => Pages\ViewOpportunity::route('/{record}'),
            'edit' => Pages\EditOpportunity::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
