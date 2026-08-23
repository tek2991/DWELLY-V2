<?php

namespace App\Filament\Resources\OwnerPayouts\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OwnerPayoutForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Payout Information')
                    ->columns(3)
                    ->components([
                        Select::make('property_id')
                            ->relationship('property', 'building_name')
                            ->disabled(),

                        Select::make('owner_id')
                            ->relationship('owner', 'display_name')
                            ->disabled(),

                        TextInput::make('status')
                            ->disabled(),

                        DatePicker::make('period_start')
                            ->disabled(),

                        DatePicker::make('period_end')
                            ->disabled(),

                        DatePicker::make('processed_at')
                            ->disabled(),
                    ]),

                Section::make('Financial Breakdown')
                    ->columns(4)
                    ->components([
                        TextInput::make('rent_collected')
                            ->label('Gross Rent Collected')
                            ->prefix('₹')
                            ->disabled(),

                        TextInput::make('management_fee')
                            ->label('Management Fee / Commission')
                            ->prefix('₹')
                            ->disabled(),

                        TextInput::make('advance_offset')
                            ->label('Advance / Appliance Offset')
                            ->prefix('₹')
                            ->disabled(),

                        TextInput::make('amount')
                            ->label('Net Disbursed Amount')
                            ->prefix('₹')
                            ->disabled(),
                    ]),

                Section::make('Remarks')
                    ->components([
                        Textarea::make('notes')
                            ->disabled(),
                    ]),
            ]);
    }
}

