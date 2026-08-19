<?php

namespace App\Filament\Resources\TenancyAgreements;

use App\Domain\Agreement\Models\TenancyAgreement;
use App\Filament\Resources\TenancyAgreements\Pages\CreateTenancyAgreement;
use App\Filament\Resources\TenancyAgreements\Pages\DeboardTenancy;
use App\Filament\Resources\TenancyAgreements\Pages\EditAgreementTerms;
use App\Filament\Resources\TenancyAgreements\Pages\EditTenancyAgreement;
use App\Filament\Resources\TenancyAgreements\Pages\ListTenancyAgreements;
use App\Filament\Resources\TenancyAgreements\Pages\ManageAgreementActivation;
use App\Filament\Resources\TenancyAgreements\Pages\ManageAgreementDocuments;
use App\Filament\Resources\TenancyAgreements\Pages\ManageSecondaryTenants;
use App\Filament\Resources\TenancyAgreements\Schemas\TenancyAgreementForm;
use App\Filament\Resources\TenancyAgreements\Tables\TenancyAgreementsTable;
use BackedEnum;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Resources\Pages\Page;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class TenancyAgreementResource extends Resource
{
    protected static ?string $model = TenancyAgreement::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static \UnitEnum|string|null $navigationGroup = 'Sales & CRM';

    protected static ?int $navigationSort = 99;

    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    public static function form(Schema $schema): Schema
    {
        return TenancyAgreementForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TenancyAgreementsTable::configure($table);
    }

    public static function getRecordSubNavigation(Page $page): array
    {
        return $page->generateNavigationItems([
            EditTenancyAgreement::class,
            EditAgreementTerms::class,
            ManageSecondaryTenants::class,
            ManageAgreementDocuments::class,
            ManageAgreementActivation::class,
            DeboardTenancy::class,
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
            'index' => ListTenancyAgreements::route('/'),
            'create' => CreateTenancyAgreement::route('/create'),
            'edit' => EditTenancyAgreement::route('/{record}/edit'),
            'terms' => EditAgreementTerms::route('/{record}/terms'),
            'secondary-tenants' => ManageSecondaryTenants::route('/{record}/secondary-tenants'),
            'documents' => ManageAgreementDocuments::route('/{record}/documents'),
            'activation' => ManageAgreementActivation::route('/{record}/activation'),
            'deboard' => DeboardTenancy::route('/{record}/deboard'),
        ];
    }
}
