<?php

namespace App\Filament\Resources\TenancyAgreements;

use App\Domain\Agreement\Models\TenancyAgreement;
use App\Filament\Resources\TenancyAgreements\Pages\CreateTenancyAgreement;
use App\Filament\Resources\TenancyAgreements\Pages\EditTenancyAgreement;
use App\Filament\Resources\TenancyAgreements\Pages\ListTenancyAgreements;
use App\Filament\Resources\TenancyAgreements\Schemas\TenancyAgreementForm;
use App\Filament\Resources\TenancyAgreements\Tables\TenancyAgreementsTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class TenancyAgreementResource extends Resource
{
    protected static ?string $model = TenancyAgreement::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return TenancyAgreementForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TenancyAgreementsTable::configure($table);
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
            'index' => ListTenancyAgreements::route('/'),
            'create' => CreateTenancyAgreement::route('/create'),
            'edit' => EditTenancyAgreement::route('/{record}/edit'),
        ];
    }
}
