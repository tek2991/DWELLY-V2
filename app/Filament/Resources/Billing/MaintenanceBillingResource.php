<?php

namespace App\Filament\Resources\Billing;

use App\Domain\Maintenance\Models\MaintenanceRequest;
use App\Filament\Resources\Billing\Pages\CreateMaintenanceBilling;
use App\Filament\Resources\Billing\Pages\EditMaintenanceBilling;
use App\Filament\Resources\Billing\Pages\ListMaintenanceBilling;
use App\Filament\Resources\Billing\Schemas\MaintenanceBillingForm;
use App\Filament\Resources\Billing\Tables\MaintenanceBillingTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Tek2991\Accounting\Models\Invoice;

class MaintenanceBillingResource extends Resource
{
    protected static ?string $model = Invoice::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;

    protected static \UnitEnum|string|null $navigationGroup = 'Billing & Finance';

    protected static ?string $navigationLabel = 'Maintenance Invoices & Bills';

    protected static ?string $modelLabel = 'Maintenance Invoice';

    protected static ?string $pluralModelLabel = 'Maintenance Invoices & Bills';

    public static function form(Schema $schema): Schema
    {
        return MaintenanceBillingForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MaintenanceBillingTable::configure($table);
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()
            ->where(function ($query) {
                $query->where('reference_type', MaintenanceRequest::class)
                    ->orWhere('notes', 'like', '%Maintenance%');
            });
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMaintenanceBilling::route('/'),
            'create' => CreateMaintenanceBilling::route('/create'),
            'edit' => EditMaintenanceBilling::route('/{record}/edit'),
        ];
    }
}
