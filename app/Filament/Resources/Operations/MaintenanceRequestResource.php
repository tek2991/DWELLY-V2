<?php

namespace App\Filament\Resources\Operations;

use App\Domain\Maintenance\Models\MaintenanceRequest;
use App\Filament\Resources\Operations\MaintenanceRequestResource\Pages\CreateMaintenanceRequest;
use App\Filament\Resources\Operations\MaintenanceRequestResource\Pages\EditMaintenanceRequest;
use App\Filament\Resources\Operations\MaintenanceRequestResource\Pages\ListMaintenanceRequests;
use App\Filament\Resources\Operations\MaintenanceRequestResource\Schemas\MaintenanceRequestForm;
use App\Filament\Resources\Operations\MaintenanceRequestResource\Tables\MaintenanceRequestsTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class MaintenanceRequestResource extends Resource
{
    protected static ?string $model = MaintenanceRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;

    protected static \UnitEnum|string|null $navigationGroup = 'Portfolio & Operations';

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return MaintenanceRequestForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MaintenanceRequestsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            \App\Filament\Resources\Operations\MaintenanceRequestResource\RelationManagers\ItemsRelationManager::class,
            \App\Filament\Resources\Operations\MaintenanceRequestResource\RelationManagers\RepairExecutionRelationManager::class,
            \App\Filament\Resources\Operations\MaintenanceRequestResource\RelationManagers\VerificationAuditRelationManager::class,
        ];
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMaintenanceRequests::route('/'),
            'create' => CreateMaintenanceRequest::route('/create'),
            'edit' => EditMaintenanceRequest::route('/{record}/edit'),
        ];
    }
}
