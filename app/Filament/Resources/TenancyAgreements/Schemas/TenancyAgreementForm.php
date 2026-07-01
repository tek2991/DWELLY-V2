<?php

namespace App\Filament\Resources\TenancyAgreements\Schemas;

use Filament\Schemas\Schema;
use Filament\Forms\Components\Wizard;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Placeholder;
use Illuminate\Support\HtmlString;

class TenancyAgreementForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Wizard::make([
                    Wizard\Step::make('Property & Terms')
                        ->schema([
                            Select::make('property_id')
                                ->relationship('property', 'building_name')
                                ->searchable()
                                ->required(),
                            TextInput::make('rent_amount')
                                ->numeric()
                                ->prefix('₹')
                                ->required(),
                            TextInput::make('security_deposit')
                                ->numeric()
                                ->prefix('₹')
                                ->helperText('Leave blank to auto-calculate (2 months rent)'),
                            DatePicker::make('start_date')->required(),
                            DatePicker::make('end_date')->required(),
                            TextInput::make('lock_in_period_months')
                                ->numeric()
                                ->default(0),
                            TextInput::make('notice_period_days')
                                ->numeric()
                                ->default(30),
                        ])->columns(2),

                    Wizard\Step::make('Tenants & Roles')
                        ->schema([
                            Select::make('primary_tenant_id')
                                ->label('Primary Tenant')
                                ->options(fn() => \App\Domain\Party\Models\Party::whereHas('tenantProfile')
                                    ->pluck('display_name', 'id'))
                                ->searchable()
                                ->required(),
                        ]),

                    Wizard\Step::make('E-Signature & Review')
                        ->schema([
                            Textarea::make('special_terms')
                                ->columnSpanFull(),
                            Placeholder::make('e_signature')
                                ->label('E-Signature Placeholder')
                                ->content(new HtmlString('<div class="p-4 border-2 border-dashed border-gray-300 rounded-lg text-center text-gray-500">Document generation and e-signature provider integration will appear here during activation workflow.</div>'))
                                ->columnSpanFull()
                        ]),
                ])->columnSpanFull(),
            ]);
    }
}
