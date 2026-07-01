<?php

namespace Tek2991\Accounting\Filament\Resources\Settings\Organizations;

use Tek2991\Accounting\Filament\Resources\Settings\Organizations\Pages\CreateOrganization;
use Tek2991\Accounting\Filament\Resources\Settings\Organizations\Pages\EditOrganization;
use Tek2991\Accounting\Filament\Resources\Settings\Organizations\Pages\ListOrganizations;
use Tek2991\Accounting\Models\Organization;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class OrganizationResource extends Resource
{
    protected static ?string $model = Organization::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';
    protected static ?string $navigationGroup = 'Settings';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')->required()->maxLength(255),
                Forms\Components\TextInput::make('legal_name')->maxLength(255),
                Forms\Components\TextInput::make('trade_name')->maxLength(255),
                Forms\Components\TextInput::make('pan')->maxLength(10),
                Forms\Components\TextInput::make('default_currency')->default('INR')->maxLength(3),
                Forms\Components\Select::make('tax_regime')
                    ->options([
                        'india_gst' => 'India GST',
                        'none' => 'None',
                    ]),
                Forms\Components\TextInput::make('email')->email()->maxLength(255),
                Forms\Components\TextInput::make('phone')->tel()->maxLength(255),
                Forms\Components\TextInput::make('website')->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('legal_name')->searchable(),
                Tables\Columns\TextColumn::make('tax_regime'),
                Tables\Columns\TextColumn::make('email')->searchable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrganizations::route('/'),
            'create' => CreateOrganization::route('/create'),
            'edit' => EditOrganization::route('/{record}/edit'),
        ];
    }
}
