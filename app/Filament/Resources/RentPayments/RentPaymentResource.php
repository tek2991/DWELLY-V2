<?php

namespace App\Filament\Resources\RentPayments;

use App\Domain\Finance\Models\RentPayment;
use App\Filament\Resources\RentPayments\Pages\CreateRentPayment;
use App\Filament\Resources\RentPayments\Pages\EditRentPayment;
use App\Filament\Resources\RentPayments\Pages\ListRentPayments;
use App\Filament\Resources\RentPayments\Schemas\RentPaymentForm;
use App\Filament\Resources\RentPayments\Tables\RentPaymentsTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class RentPaymentResource extends Resource
{
    protected static ?string $model = RentPayment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static \UnitEnum|string|null $navigationGroup = 'Leasing & Finance';

    public static function form(Schema $schema): Schema
    {
        return RentPaymentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RentPaymentsTable::configure($table);
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
            'index' => ListRentPayments::route('/'),
            'create' => CreateRentPayment::route('/create'),
            'edit' => EditRentPayment::route('/{record}/edit'),
        ];
    }
}
