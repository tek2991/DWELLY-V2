<?php

namespace App\Filament\Resources\Operations;

use App\Domain\Opportunity\Models\Opportunity;
use App\Filament\Resources\Operations\MOUResource;
use App\Filament\Resources\Operations\OpportunityResource\Pages;
use App\Filament\Resources\Operations\OpportunityResource\Schemas\OpportunityForm;
use App\Filament\Resources\Operations\OpportunityResource\Tables\OpportunitiesTable;
use App\Filament\Resources\Properties\PropertyResource;
use BackedEnum;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class OpportunityResource extends Resource
{
    protected static ?string $model = Opportunity::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;
    
    protected static \UnitEnum|string|null $navigationGroup = 'Sales & CRM';
    
    protected static ?int $navigationSort = 1;

    public static function canEdit(?\Illuminate\Database\Eloquent\Model $record = null): bool
    {
        if (! $record) {
            return true;
        }

        return ! $record->mou()->exists();
    }

    public static function form(Schema $schema): Schema
    {
        return OpportunityForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OpportunitiesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Group::make([
                    Section::make('📍 Lead & Opportunity Overview')
                        ->schema([
                            TextEntry::make('number')
                                ->label('Opportunity #')
                                ->weight('bold')
                                ->copyable(),
                            TextEntry::make('status')
                                ->badge(),
                            TextEntry::make('opportunitySource.name')
                                ->label('Lead Source')
                                ->placeholder('—'),
                            TextEntry::make('source_phone')
                                ->label('Source Phone')
                                ->placeholder('—'),
                            TextEntry::make('assignedUser.name')
                                ->label('Assigned Relationship Manager')
                                ->placeholder('Unassigned'),
                            TextEntry::make('expected_onboarding_date')
                                ->label('Target Onboarding Date')
                                ->date()
                                ->placeholder('—'),
                            TextEntry::make('mou.number')
                                ->label('Associated MOU')
                                ->url(fn (Opportunity $record) => $record->mou ? MOUResource::getUrl('view', ['record' => $record->mou]) : null)
                                ->badge()
                                ->color('info')
                                ->visible(fn (Opportunity $record) => $record->mou !== null),
                            TextEntry::make('mou.property.code')
                                ->label('Converted Property')
                                ->url(fn (Opportunity $record) => $record->mou?->property ? PropertyResource::getUrl('edit', ['record' => $record->mou->property]) : null)
                                ->badge()
                                ->color('success')
                                ->visible(fn (Opportunity $record) => $record->mou?->property !== null),
                        ])->columns(3),

                    Section::make('👤 Owner Contact Profile')
                        ->schema([
                            TextEntry::make('owner_name')->label('Owner Name')->weight('bold'),
                            TextEntry::make('owner_phone')->label('Phone')->copyable(),
                            TextEntry::make('owner_email')->label('Email')->placeholder('—')->copyable(),
                            TextEntry::make('address')->label('Address')->columnSpanFull()->placeholder('—'),
                        ])->columns(3),

                    Section::make('🏢 Property & Commercial Estimates')
                        ->schema([
                            TextEntry::make('estimatedPropertyType.name')->label('Property Type')->placeholder('—'),
                            TextEntry::make('estimated_bhk')->label('BHK Configuration')->badge()->color('gray')->placeholder('—'),
                            TextEntry::make('estimated_size')->label('Size (Sq.Ft.)')->placeholder('—')->suffix(' Sq.Ft.'),
                            IconEntry::make('estimated_is_furnished')->boolean()->label('Furnished'),
                            TextEntry::make('expected_rent')->money('INR')->label('Expected Rent')->weight('bold'),
                            TextEntry::make('expectedFinancialModel.name')->label('Financial Model')->placeholder('—'),
                        ])->columns(3),

                    Section::make('📝 Internal Notes & Summary')
                        ->schema([
                            TextEntry::make('internal_summary')
                                ->hiddenLabel()
                                ->columnSpanFull()
                                ->placeholder('No internal notes recorded.'),
                        ]),
                ])->columnSpan(['lg' => 2]),

                Group::make([
                    Section::make('🕒 Activity & History')
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
