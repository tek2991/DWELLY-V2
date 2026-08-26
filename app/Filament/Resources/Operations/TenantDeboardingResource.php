<?php

namespace App\Filament\Resources\Operations;

use App\Domain\Agreement\Models\TenantDeboarding;
use App\Filament\Resources\Operations\TenantDeboardingResource\Pages\CreateTenantDeboarding;
use App\Filament\Resources\Operations\TenantDeboardingResource\Pages\EditTenantDeboarding;
use App\Filament\Resources\Operations\TenantDeboardingResource\Pages\ListTenantDeboardings;
use App\Filament\Resources\Operations\TenantDeboardingResource\Pages\ManageDeboardingAudit;
use App\Filament\Resources\Operations\TenantDeboardingResource\Pages\ManageDeboardingCompletion;
use App\Filament\Resources\Operations\TenantDeboardingResource\Pages\ManageDeboardingKeys;
use App\Filament\Resources\Operations\TenantDeboardingResource\Pages\ManageDeboardingMaintenance;
use App\Filament\Resources\Operations\TenantDeboardingResource\Pages\ManageDeboardingSettlement;
use App\Filament\Resources\Operations\TenantDeboardingResource\Schemas\TenantDeboardingForm;
use App\Filament\Resources\Operations\TenantDeboardingResource\Tables\TenantDeboardingsTable;
use BackedEnum;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Resources\Pages\Page;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class TenantDeboardingResource extends Resource
{
    protected static ?string $model = TenantDeboarding::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowLeftOnRectangle;

    protected static \UnitEnum|string|null $navigationGroup = 'Portfolio & Operations';

    protected static ?string $navigationLabel = 'Tenant Deboardings';

    protected static ?int $navigationSort = 5;

    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    public static function form(Schema $schema): Schema
    {
        return TenantDeboardingForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TenantDeboardingsTable::configure($table);
    }

    public static function getRecordSubNavigation(Page $page): array
    {
        return $page->generateNavigationItems([
            EditTenantDeboarding::class,
            ManageDeboardingAudit::class,
            ManageDeboardingMaintenance::class,
            ManageDeboardingKeys::class,
            ManageDeboardingSettlement::class,
            ManageDeboardingCompletion::class,
        ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTenantDeboardings::route('/'),
            'create' => CreateTenantDeboarding::route('/create'),
            'edit' => EditTenantDeboarding::route('/{record}/edit'),
            'audit' => ManageDeboardingAudit::route('/{record}/audit'),
            'maintenance' => ManageDeboardingMaintenance::route('/{record}/maintenance'),
            'keys' => ManageDeboardingKeys::route('/{record}/keys'),
            'settlement' => ManageDeboardingSettlement::route('/{record}/settlement'),
            'completion' => ManageDeboardingCompletion::route('/{record}/completion'),
        ];
    }
}
