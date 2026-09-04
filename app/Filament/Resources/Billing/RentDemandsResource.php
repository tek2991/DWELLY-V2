<?php

namespace App\Filament\Resources\Billing;

use App\Filament\Resources\Billing\Pages\ListRentDemands;
use App\Filament\Resources\Billing\Schemas\RentDemandForm;
use App\Filament\Resources\Billing\Tables\RentDemandsTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Tek2991\Accounting\Models\Invoice;
use App\Domain\Agreement\Models\TenancyAgreement;

class RentDemandsResource extends Resource
{
    protected static ?string $model = Invoice::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static \UnitEnum|string|null $navigationGroup = 'Billing & Finance';

    protected static ?string $navigationLabel = 'Rent Demands & Collections';

    protected static ?string $modelLabel = 'Rent Demand';

    protected static ?string $pluralModelLabel = 'Rent Demands & Receipts';

    public static function form(Schema $schema): Schema
    {
        return RentDemandForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RentDemandsTable::configure($table);
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()
            ->where(function ($query) {
                $query->where('reference_type', TenancyAgreement::class)
                    ->orWhere('notes', 'like', '%Rent%');
            });
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRentDemands::route('/'),
        ];
    }
}
