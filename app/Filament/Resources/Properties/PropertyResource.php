<?php

namespace App\Filament\Resources\Properties;

use App\Domain\Property\Models\Property;
use App\Filament\Clusters\PropertiesCluster;
use App\Filament\Resources\Properties\Pages\CreateProperty;
use App\Filament\Resources\Properties\Pages\EditProperty;
use App\Filament\Resources\Properties\Pages\ListProperties;
use App\Filament\Resources\Properties\Pages\OnboardingDashboard;
use App\Filament\Resources\Properties\Pages\PropertyFinancials;
use App\Filament\Resources\Properties\RelationManagers\ActivitiesRelationManager;
use App\Filament\Resources\Properties\RelationManagers\AmenitiesRelationManager;
use App\Filament\Resources\Properties\RelationManagers\AuditsRelationManager;
use App\Filament\Resources\Properties\RelationManagers\EstablishmentsRelationManager;
use App\Filament\Resources\Properties\RelationManagers\InventoriesRelationManager;
use App\Filament\Resources\Properties\RelationManagers\MaintenanceRequestsRelationManager;
use App\Filament\Resources\Properties\RelationManagers\PhotosRelationManager;
use App\Filament\Resources\Properties\RelationManagers\PricingVersionsRelationManager;
use App\Filament\Resources\Properties\RelationManagers\RoomsRelationManager;
use App\Filament\Resources\Properties\RelationManagers\TasksRelationManager;
use App\Filament\Resources\Properties\RelationManagers\UtilitiesRelationManager;
use App\Filament\Resources\Properties\Schemas\PropertyForm;
use App\Filament\Resources\Properties\Tables\PropertiesTable;
use BackedEnum;
use Filament\Resources\RelationManagers\RelationGroup;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class PropertyResource extends Resource
{
    protected static ?string $model = Property::class;

    protected static ?string $cluster = PropertiesCluster::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?string $navigationLabel = 'All Properties';

    protected static ?int $navigationSort = 1;

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('viewAny', Property::class) ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('create', Property::class) ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->can('update', $record) ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()?->can('delete', $record) ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return PropertyForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PropertiesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            RoomsRelationManager::class,
            InventoriesRelationManager::class,
            AmenitiesRelationManager::class,
            EstablishmentsRelationManager::class,
            PhotosRelationManager::class,
            PricingVersionsRelationManager::class,
            UtilitiesRelationManager::class,
            RelationGroup::make('Operations, Tasks & Audits', [
                TasksRelationManager::class,
                MaintenanceRequestsRelationManager::class,
                AuditsRelationManager::class,
            ]),
            ActivitiesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProperties::route('/'),
            'create' => CreateProperty::route('/create'),
            'edit' => EditProperty::route('/{record}/edit'),
            'financials' => PropertyFinancials::route('/{record}/financials'),
            'onboarding' => OnboardingDashboard::route('/{record}/onboarding'),
        ];
    }
}
