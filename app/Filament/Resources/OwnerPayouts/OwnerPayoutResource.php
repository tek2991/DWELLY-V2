<?php

namespace App\Filament\Resources\OwnerPayouts;

use App\Domain\Finance\Models\OwnerPayout;
use App\Filament\Resources\OwnerPayouts\Pages\CreateOwnerPayout;
use App\Filament\Resources\OwnerPayouts\Pages\EditOwnerPayout;
use App\Filament\Resources\OwnerPayouts\Pages\ListOwnerPayouts;
use App\Filament\Resources\OwnerPayouts\Schemas\OwnerPayoutForm;
use App\Filament\Resources\OwnerPayouts\Tables\OwnerPayoutsTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class OwnerPayoutResource extends Resource
{
    protected static ?string $model = OwnerPayout::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static \UnitEnum|string|null $navigationGroup = 'Leasing & Finance';

    public static function form(Schema $schema): Schema
    {
        return OwnerPayoutForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OwnerPayoutsTable::configure($table);
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
            'index' => ListOwnerPayouts::route('/'),
            'create' => CreateOwnerPayout::route('/create'),
            'edit' => EditOwnerPayout::route('/{record}/edit'),
        ];
    }
}
