<?php

namespace Tek2991\Accounting\Filament\Resources\Settings\GstRegistrations;

use Tek2991\Accounting\Filament\Resources\Settings\GstRegistrations\Pages\CreateGstRegistration;
use Tek2991\Accounting\Filament\Resources\Settings\GstRegistrations\Pages\EditGstRegistration;
use Tek2991\Accounting\Filament\Resources\Settings\GstRegistrations\Pages\ListGstRegistrations;
use Tek2991\Accounting\Models\GstRegistration;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class GstRegistrationResource extends Resource
{
    protected static ?string $model = GstRegistration::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationGroup = 'Settings';
    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('gstin')
                    ->required()
                    ->maxLength(15)
                    ->label('GSTIN'),
                Forms\Components\TextInput::make('legal_name')->maxLength(255),
                Forms\Components\TextInput::make('trade_name')->maxLength(255),
                Forms\Components\Select::make('state_id')
                    ->relationship('state', 'name')
                    ->required(),
                Forms\Components\Textarea::make('address')->maxLength(65535)->columnSpanFull(),
                Forms\Components\DatePicker::make('registration_date'),
                Forms\Components\Toggle::make('is_default')->default(false),
                Forms\Components\Select::make('status')
                    ->options([
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                        'cancelled' => 'Cancelled',
                    ])
                    ->default('active'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('gstin')->searchable()->label('GSTIN'),
                Tables\Columns\TextColumn::make('state.name')->sortable(),
                Tables\Columns\TextColumn::make('status')->searchable(),
                Tables\Columns\IconColumn::make('is_default')->boolean(),
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
            'index' => ListGstRegistrations::route('/'),
            'create' => CreateGstRegistration::route('/create'),
            'edit' => EditGstRegistration::route('/{record}/edit'),
        ];
    }
}
