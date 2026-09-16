<?php

namespace App\Filament\Resources\Billing;

use App\Domain\Maintenance\Models\MaintenanceClientQuote;
use App\Filament\Resources\Billing\MaintenanceQuotationResource\Pages\CreateMaintenanceQuotation;
use App\Filament\Resources\Billing\MaintenanceQuotationResource\Pages\EditMaintenanceQuotation;
use App\Filament\Resources\Billing\MaintenanceQuotationResource\Pages\ListMaintenanceQuotations;
use App\Filament\Resources\Billing\MaintenanceQuotationResource\Pages\ManageClientQuotation;
use App\Filament\Resources\Billing\MaintenanceQuotationResource\Pages\ManageQuotationApproval;
use App\Filament\Resources\Billing\MaintenanceQuotationResource\Pages\ManageQuotationSettlement;
use App\Filament\Resources\Billing\MaintenanceQuotationResource\Pages\ManageQuotationWorkOrders;
use App\Filament\Resources\Billing\MaintenanceQuotationResource\Schemas\MaintenanceQuotationForm;
use App\Filament\Resources\Billing\MaintenanceQuotationResource\Tables\MaintenanceQuotationsTable;
use BackedEnum;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Resources\Pages\Page;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class MaintenanceQuotationResource extends Resource
{
    protected static ?string $model = MaintenanceClientQuote::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalculator;

    protected static \UnitEnum|string|null $navigationGroup = 'Maintenance & Field Ops';

    protected static ?string $navigationLabel = 'Maintenance Quotes';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'Maintenance Quotation';

    protected static ?string $pluralModelLabel = 'Maintenance Quotations';

    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    public static function form(Schema $schema): Schema
    {
        return MaintenanceQuotationForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MaintenanceQuotationsTable::configure($table);
    }

    public static function getRecordSubNavigation(Page $page): array
    {
        $record = $page->getRecord();
        $isDwelly = (bool) $record?->maintenanceRequest?->payer_type?->isDwellyAbsorbed();

        if ($isDwelly) {
            return $page->generateNavigationItems([
                EditMaintenanceQuotation::class,
                ManageQuotationWorkOrders::class,
                ManageQuotationSettlement::class,
            ]);
        }

        return $page->generateNavigationItems([
            EditMaintenanceQuotation::class,
            ManageClientQuotation::class,
            ManageQuotationApproval::class,
            ManageQuotationWorkOrders::class,
            ManageQuotationSettlement::class,
        ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('viewAny', MaintenanceClientQuote::class) ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->can('update', $record) ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()?->can('delete', $record) ?? false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMaintenanceQuotations::route('/'),
            'create' => CreateMaintenanceQuotation::route('/create'),
            'edit' => EditMaintenanceQuotation::route('/{record}/edit'),
            'pricing' => ManageClientQuotation::route('/{record}/pricing'),
            'approval' => ManageQuotationApproval::route('/{record}/approval'),
            'work-orders' => ManageQuotationWorkOrders::route('/{record}/work-orders'),
            'settlement' => ManageQuotationSettlement::route('/{record}/settlement'),
        ];
    }
}
