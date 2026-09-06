<?php

namespace App\Filament\Resources\Operations;

use App\Domain\Maintenance\Models\MaintenanceRequest;
use App\Filament\Resources\Operations\MaintenanceRequestResource\Pages\CreateMaintenanceRequest;
use App\Filament\Resources\Operations\MaintenanceRequestResource\Pages\EditMaintenanceRequest;
use App\Filament\Resources\Operations\MaintenanceRequestResource\Pages\ListMaintenanceRequests;
use App\Filament\Resources\Operations\MaintenanceRequestResource\RelationManagers\ItemsRelationManager;
use App\Filament\Resources\Operations\MaintenanceRequestResource\RelationManagers\RepairExecutionRelationManager;
use App\Filament\Resources\Operations\MaintenanceRequestResource\RelationManagers\VerificationAuditRelationManager;
use App\Filament\Resources\Operations\MaintenanceRequestResource\Schemas\MaintenanceRequestForm;
use App\Filament\Resources\Operations\MaintenanceRequestResource\Tables\MaintenanceRequestsTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class MaintenanceRequestResource extends Resource
{
    protected static ?string $model = MaintenanceRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;

    protected static \UnitEnum|string|null $navigationGroup = 'Maintenance & Field Ops';

    protected static ?int $navigationSort = 1;

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
            ItemsRelationManager::class,
            RepairExecutionRelationManager::class,
            VerificationAuditRelationManager::class,
        ];
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('viewAny', MaintenanceRequest::class) ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('create', MaintenanceRequest::class) ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->can('update', $record) ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()?->can('delete', $record) ?? false;
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
