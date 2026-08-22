<?php

namespace App\Filament\Resources\Properties\Schemas;

use App\Domain\Geographic\Models\City;
use App\Domain\Geographic\Models\District;
use App\Domain\Geographic\Models\Locality;
use App\Domain\Property\Enums\PropertyStatus;
use App\Domain\Property\Models\Property;
use App\Domain\Property\Services\PropertyOnboardingValidator;
use App\Filament\Resources\Operations\MOUResource;
use App\Filament\Resources\Parties\PartyResource;
use App\Filament\Resources\Properties\PropertyResource;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Tek2991\Accounting\Models\State;

class PropertyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(3)
                    ->columnSpanFull()
                    ->schema([
                        // Left Main Content (2 Columns)
                        Grid::make(1)
                            ->columnSpan(2)
                            ->schema([
                                // 📍 Section 1: Primary & Structural Details
                                Section::make('📍 Primary & Structural Details')
                                    ->schema([
                                        TextInput::make('building_name')
                                            ->label('Building / Project Name')
                                            ->placeholder('e.g. Prestige Ozone, Villa 42')
                                            ->required()
                                            ->maxLength(255),

                                        TextInput::make('code')
                                            ->label('Property Code')
                                            ->placeholder('e.g. PRP-001')
                                            ->required()
                                            ->regex('/^[a-zA-Z0-9]+(?:-[a-zA-Z0-9]+)*$/')
                                            ->validationMessages([
                                                'regex' => 'The code must only contain letters, numbers, and single hyphens, and cannot start or end with a hyphen.',
                                            ])
                                            ->unique(ignoreRecord: true)
                                            ->maxLength(255),

                                        Select::make('property_type_id')
                                            ->label('Property Type')
                                            ->required()
                                            ->options(fn () => DB::table('property_types')->pluck('name', 'id'))
                                            ->searchable()
                                            ->preload(),

                                        Select::make('bhk_type_id')
                                            ->label('BHK Configuration')
                                            ->required()
                                            ->options(fn () => DB::table('bhk_types')->pluck('name', 'id'))
                                            ->searchable()
                                            ->preload(),

                                        TextInput::make('floor_space_sqft')
                                            ->label('Floor Space')
                                            ->placeholder('e.g. 1450')
                                            ->required()
                                            ->numeric()
                                            ->suffix('sq. ft'),

                                        Select::make('flooring_type_id')
                                            ->label('Flooring Type')
                                            ->required()
                                            ->options(fn () => DB::table('flooring_types')->pluck('name', 'id'))
                                            ->searchable()
                                            ->preload(),

                                        TextInput::make('floor')
                                            ->label('Floor Number')
                                            ->placeholder('e.g. 3')
                                            ->required()
                                            ->numeric(),

                                        TextInput::make('total_floors')
                                            ->label('Total Floors in Building')
                                            ->placeholder('e.g. 12')
                                            ->required()
                                            ->numeric(),

                                        Select::make('furnishing_type_id')
                                            ->label('Furnishing Status')
                                            ->required()
                                            ->options(fn () => DB::table('furnishing_types')->pluck('name', 'id'))
                                            ->searchable()
                                            ->preload()
                                            ->columnSpanFull(),
                                    ])->columns(2),

                                // 🗺️ Section 2: Location & Address
                                Section::make('🗺️ Location & Geographic Address')
                                    ->schema([
                                        TextInput::make('address_line_1')
                                            ->label('Address Line 1')
                                            ->placeholder('Door/Flat number, wing, street address...')
                                            ->required()
                                            ->maxLength(255)
                                            ->columnSpanFull(),

                                        TextInput::make('address_line_2')
                                            ->label('Address Line 2')
                                            ->placeholder('Secondary address details (optional)...')
                                            ->maxLength(255)
                                            ->columnSpanFull(),

                                        TextInput::make('landmark')
                                            ->label('Prominent Landmark')
                                            ->placeholder('e.g. Near Metro Station / Behind Forum Mall')
                                            ->required()
                                            ->maxLength(255),

                                        Grid::make(2)
                                            ->schema([
                                                TextInput::make('latitude')
                                                    ->label('Latitude')
                                                    ->placeholder('e.g. 12.9716')
                                                    ->required()
                                                    ->numeric(),

                                                TextInput::make('longitude')
                                                    ->label('Longitude')
                                                    ->placeholder('e.g. 77.5946')
                                                    ->required()
                                                    ->numeric(),
                                            ]),

                                        Select::make('state_id')
                                            ->required()
                                            ->label('State')
                                            ->options(fn () => State::pluck('name', 'id'))
                                            ->live()
                                            ->afterStateHydrated(function ($component, $state, ?Model $record) {
                                                if ($record && $record->locality_id) {
                                                    $locality = Locality::with('city.district.state')->find($record->locality_id);
                                                    if ($locality && $locality->city && $locality->city->district) {
                                                        $component->state($locality->city->district->state_id);
                                                    }
                                                }
                                            })
                                            ->afterStateUpdated(function ($set) {
                                                $set('district_id', null);
                                                $set('city_id', null);
                                                $set('locality_id', null);
                                            })
                                            ->dehydrated(false)
                                            ->searchable()
                                            ->preload(),

                                        Select::make('district_id')
                                            ->required()
                                            ->label('District')
                                            ->options(function ($get) {
                                                $stateId = $get('state_id');
                                                if (! $stateId) {
                                                    return [];
                                                }

                                                return District::where('state_id', $stateId)->pluck('name', 'id');
                                            })
                                            ->live()
                                            ->afterStateHydrated(function ($component, $state, ?Model $record) {
                                                if ($record && $record->locality_id) {
                                                    $locality = Locality::with('city.district')->find($record->locality_id);
                                                    if ($locality && $locality->city) {
                                                        $component->state($locality->city->district_id);
                                                    }
                                                }
                                            })
                                            ->afterStateUpdated(function ($set) {
                                                $set('city_id', null);
                                                $set('locality_id', null);
                                            })
                                            ->dehydrated(false)
                                            ->searchable()
                                            ->preload(),

                                        Select::make('city_id')
                                            ->required()
                                            ->label('City')
                                            ->options(function ($get) {
                                                $districtId = $get('district_id');
                                                if (! $districtId) {
                                                    return [];
                                                }

                                                return City::where('district_id', $districtId)->pluck('name', 'id');
                                            })
                                            ->live()
                                            ->afterStateHydrated(function ($component, $state, ?Model $record) {
                                                if ($record && $record->locality_id) {
                                                    $locality = Locality::find($record->locality_id);
                                                    if ($locality) {
                                                        $component->state($locality->city_id);
                                                    }
                                                }
                                            })
                                            ->afterStateUpdated(function ($set) {
                                                $set('locality_id', null);
                                            })
                                            ->dehydrated(false)
                                            ->searchable()
                                            ->preload(),

                                        Select::make('locality_id')
                                            ->label('Locality')
                                            ->options(function ($get) {
                                                $cityId = $get('city_id');
                                                if (! $cityId) {
                                                    return [];
                                                }

                                                return Locality::where('city_id', $cityId)->pluck('name', 'id');
                                            })
                                            ->required()
                                            ->searchable()
                                            ->preload(),
                                    ])->columns(2),

                                // 👤 Section 3: Associated Owner Entity Profile
                                Section::make('👤 Associated Owner Entity Profile')
                                    ->description('Details of the primary owner entity linked via the signed Management Agreement (MOU).')
                                    ->schema([
                                        Placeholder::make('owner_profile_card')
                                            ->hiddenLabel()
                                            ->columnSpanFull()
                                            ->content(function (?Model $record) {
                                                $mou = $record?->mous()->latest()->first();
                                                $party = $mou?->party;

                                                if (! $party) {
                                                    return new HtmlString(
                                                        '<div style="background-color: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 8px; padding: 14px 16px; font-size: 13px; color: #64748b;">' .
                                                        'No owner entity profile linked yet.' .
                                                        '</div>'
                                                    );
                                                }

                                                $partyType = ucfirst($party->party_type ?? 'Individual');
                                                $phone = e($party->phone ?? '—');
                                                $email = e($party->email ?? '—');
                                                $partyName = e($party->display_name ?? 'Unnamed Owner');
                                                $partyUrl = PartyResource::getUrl('edit', ['record' => $party]);
                                                $mouUrl = $mou ? MOUResource::getUrl('view', ['record' => $mou]) : null;

                                                return new HtmlString(
                                                    '<div style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px 16px; box-shadow: 0 1px 2px rgba(0,0,0,0.03);">' .
                                                        '<div style="display: flex; align-items: center; justify-content: space-between; padding-bottom: 10px; border-bottom: 1px solid #e2e8f0; margin-bottom: 12px;">' .
                                                            '<div style="display: flex; align-items: center; gap: 8px;">' .
                                                                '<span style="font-size: 14px; font-weight: 700; color: #0f172a;">👤 ' . $partyName . '</span>' .
                                                                '<span style="font-size: 11px; font-weight: 600; padding: 2px 8px; border-radius: 4px; background-color: #dbeafe; color: #1d4ed8;">' . $partyType . '</span>' .
                                                            '</div>' .
                                                            '<div style="display: flex; align-items: center; gap: 10px;">' .
                                                                '<a href="' . $partyUrl . '" target="_blank" style="font-size: 12px; font-weight: 600; color: #2563eb; text-decoration: underline;">View Owner Profile &rarr;</a>' .
                                                                ($mouUrl ? '<span style="color: #cbd5e1;">|</span><a href="' . $mouUrl . '" style="font-size: 12px; font-weight: 600; color: #2563eb; text-decoration: underline;">Open MOU #' . e($mou->number) . ' &rarr;</a>' : '') .
                                                            '</div>' .
                                                        '</div>' .
                                                        '<div style="display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; font-size: 12px;">' .
                                                            '<div>' .
                                                                '<span style="display: block; font-size: 11px; font-weight: 500; color: #64748b; margin-bottom: 2px;">Phone Number</span>' .
                                                                '<span style="font-family: monospace; font-weight: 600; color: #0f172a;">' . $phone . '</span>' .
                                                            '</div>' .
                                                            '<div>' .
                                                                '<span style="display: block; font-size: 11px; font-weight: 500; color: #64748b; margin-bottom: 2px;">Email Address</span>' .
                                                                '<span style="font-weight: 600; color: #0f172a;">' . $email . '</span>' .
                                                            '</div>' .
                                                        '</div>' .
                                                    '</div>'
                                                );
                                            }),
                                    ])->columns(1)
                                    ->hidden(fn (?Model $record) => ! ($record && $record->mous()->exists())),

                                // 💰 Section 4: Current Pricing & Commercials
                                Section::make('💰 Active Commercial & Pricing Terms')
                                    ->description('Overview of the currently active pricing version. Manage pricing history in the Pricing Versions tab.')
                                    ->schema([
                                        Placeholder::make('current_rent')
                                            ->label('Active Monthly Rent')
                                            ->content(function (?Model $record) {
                                                $pricing = $record?->pricingVersions()->latest('effective_from')->first();

                                                return $pricing && $pricing->rent
                                                    ? new HtmlString('<strong style="color: #059669; font-size: 14px;">₹ ' . number_format((float) $pricing->rent) . '</strong> /mo')
                                                    : 'N/A';
                                            }),

                                        Placeholder::make('current_deposit')
                                            ->label('Security Deposit')
                                            ->content(function (?Model $record) {
                                                $pricing = $record?->pricingVersions()->latest('effective_from')->first();

                                                return $pricing && $pricing->security_deposit
                                                    ? new HtmlString('<strong style="color: #0284c7; font-size: 14px;">₹ ' . number_format((float) $pricing->security_deposit) . '</strong>')
                                                    : 'N/A';
                                            }),

                                        Placeholder::make('current_model')
                                            ->label('Financial Pricing Model')
                                            ->content(function (?Model $record) {
                                                $financialTerm = $record?->financialTerms()->latest('effective_from')->first();

                                                return $financialTerm && $financialTerm->pricing_model
                                                    ? new HtmlString('<span style="display: inline-flex; align-items: center; padding: 2px 8px; border-radius: 4px; background: #e0e7ff; color: #3730a3; font-weight: 600; font-size: 12px;">' . e($financialTerm->pricing_model) . '</span>')
                                                    : 'Standard Model';
                                            }),
                                    ])->columns(3)
                                    ->hidden(fn (?Model $record) => ! $record || $record->pricingVersions()->count() === 0),

                                // 📢 Section 5: Marketing & Availability
                                Section::make('📢 Marketing & Listing Configuration')
                                    ->schema([
                                        Toggle::make('is_listed')
                                            ->label('Publicly Listed')
                                            ->helperText('Property appears in public search results and portals')
                                            ->default(true),

                                        Toggle::make('is_promoted')
                                            ->label('Promote Property')
                                            ->helperText('Boost ranking and highlight on homepage')
                                            ->default(false),

                                        DatePicker::make('available_from')
                                            ->label('Available For Move-in From')
                                            ->native(false),
                                    ])->columns(3),
                            ]),

                        // Right Sidebar (1 Column)
                        Grid::make(1)
                            ->columnSpan(1)
                            ->schema([
                                // ⚡ Property Operational Status & Hub
                                Section::make('⚡ Property Operational Hub')
                                    ->schema([
                                        Placeholder::make('property_status_badge')
                                            ->label('Operational Status')
                                            ->content(function (?Property $record) {
                                                $statusStr = $record?->status ?? 'Draft';
                                                $statusEnum = PropertyStatus::fromValue($statusStr);
                                                $label = $statusEnum?->getLabel() ?? ucfirst($statusStr);
                                                $color = match ($statusStr) {
                                                    'Vacant', 'vacant' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/60 dark:text-emerald-200 font-bold',
                                                    'Occupied', 'occupied' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/60 dark:text-blue-200 font-semibold',
                                                    'Onboarding', 'onboarding' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/60 dark:text-amber-200 font-semibold animate-pulse',
                                                    'Maintenance', 'under_maintenance', 'under maintenance' => 'bg-rose-100 text-rose-800 dark:bg-rose-900/60 dark:text-rose-200 font-semibold',
                                                    default => 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200',
                                                };

                                                return new HtmlString("<div class=\"flex items-center gap-1.5 flex-wrap\"><span class=\"inline-flex items-center px-3 py-1 rounded-full text-xs font-bold {$color}\">{$label}</span></div>");
                                            }),

                                        Placeholder::make('property_code_badge')
                                            ->label('Property Code')
                                            ->content(function (?Property $record) {
                                                $code = $record?->code;
                                                if (! $code) {
                                                    return new HtmlString('<span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-mono text-gray-400 bg-gray-50 dark:bg-gray-800 border border-dashed border-gray-300 dark:border-gray-700">Unassigned</span>');
                                                }

                                                return new HtmlString("<span class=\"inline-flex items-center px-3 py-1 rounded-md text-xs font-mono font-bold bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200 border border-gray-300 dark:border-gray-700\">#{$code}</span>");
                                            }),

                                        // Onboarding / Workflow Guidance Banner
                                        Placeholder::make('onboarding_guidance_banner')
                                            ->hiddenLabel()
                                            ->columnSpanFull()
                                            ->content(function (?Property $record) {
                                                if (! $record) {
                                                    return null;
                                                }

                                                if ($record->isLockedDuringOnboarding()) {
                                                    $validator = app(PropertyOnboardingValidator::class);
                                                    $data = $validator->validate($record);
                                                    $progress = $data['progress'] ?? 0;
                                                    $onboardingUrl = PropertyResource::getUrl('onboarding', ['record' => $record]);

                                                    return new HtmlString(
                                                        '<div style="background-color: #fffbeb; border-left: 4px solid #d97706; padding: 12px 14px; border-radius: 6px; font-size: 13px; color: #92400e; box-shadow: 0 1px 2px rgba(0,0,0,0.03);">' .
                                                            '<div style="font-weight: 700; display: flex; align-items: center; justify-content: space-between;">' .
                                                                '<span>⚠️ In Onboarding Pipeline</span>' .
                                                                '<span style="font-size: 12px; font-weight: 800; color: #b45309;">' . $progress . '%</span>' .
                                                            '</div>' .
                                                            '<div style="font-size: 12px; margin-top: 4px; color: #b45309;">' .
                                                                'Checklist must be completed and approved before changes can be made.' .
                                                            '</div>' .
                                                            '<div style="margin-top: 10px;">' .
                                                                '<a href="' . $onboardingUrl . '" style="display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; border-radius: 6px; background-color: #d97706; color: #ffffff; font-size: 12px; font-weight: 600; text-decoration: none;">' .
                                                                    '📋 Open Onboarding Dashboard &rarr;' .
                                                                '</a>' .
                                                            '</div>' .
                                                        '</div>'
                                                    );
                                                }

                                                if ($record->status === 'Vacant' || $record->status === 'vacant') {
                                                    return new HtmlString(
                                                        '<div style="background-color: #f0fdf4; border-left: 4px solid #16a34a; padding: 10px 14px; border-radius: 6px; font-size: 13px; color: #166534;">' .
                                                            '🟢 <strong>Vacant & Available for Lease:</strong> Property is active and ready to be linked with tenant agreements.' .
                                                        '</div>'
                                                    );
                                                }

                                                if ($record->status === 'Occupied' || $record->status === 'occupied') {
                                                    return new HtmlString(
                                                        '<div style="background-color: #eff6ff; border-left: 4px solid #2563eb; padding: 10px 14px; border-radius: 6px; font-size: 13px; color: #1e40af;">' .
                                                            '👤 <strong>Occupied:</strong> Currently tenanted under an active tenancy agreement.' .
                                                        '</div>'
                                                    );
                                                }

                                                return null;
                                            }),

                                        // Quick Navigation Bridges
                                        Placeholder::make('navigation_bridges_card')
                                            ->label('Related Workspaces')
                                            ->columnSpanFull()
                                            ->visible(fn (?Property $record) => (bool) $record && $record->exists)
                                            ->content(function (?Property $record) {
                                                if (! $record || ! $record->exists) {
                                                    return '';
                                                }

                                                $financialsUrl = PropertyResource::getUrl('financials', ['record' => $record]);
                                                $onboardingUrl = PropertyResource::getUrl('onboarding', ['record' => $record]);
                                                $mou = $record->mous()->latest()->first();
                                                $mouUrl = $mou ? MOUResource::getUrl('view', ['record' => $mou]) : null;

                                                $links = [
                                                    '<a href="' . $financialsUrl . '" style="display: flex; align-items: center; justify-content: space-between; padding: 8px 12px; border-radius: 6px; background-color: #f8fafc; border: 1px solid #e2e8f0; color: #0f172a; text-decoration: none; font-size: 12px; font-weight: 600;">' .
                                                        '<span>💳 Financial Terms & MOU</span><span style="color: #2563eb;">&rarr;</span>' .
                                                    '</a>',
                                                    '<a href="' . $onboardingUrl . '" style="display: flex; align-items: center; justify-content: space-between; padding: 8px 12px; border-radius: 6px; background-color: #f8fafc; border: 1px solid #e2e8f0; color: #0f172a; text-decoration: none; font-size: 12px; font-weight: 600;">' .
                                                        '<span>📋 Onboarding Dashboard</span><span style="color: #2563eb;">&rarr;</span>' .
                                                    '</a>',
                                                ];

                                                if ($mouUrl) {
                                                    $links[] = '<a href="' . $mouUrl . '" style="display: flex; align-items: center; justify-content: space-between; padding: 8px 12px; border-radius: 6px; background-color: #f8fafc; border: 1px solid #e2e8f0; color: #0f172a; text-decoration: none; font-size: 12px; font-weight: 600;">' .
                                                        '<span>📄 Onboarding MOU #' . e($mou->number) . '</span><span style="color: #2563eb;">&rarr;</span>' .
                                                    '</a>';
                                                }

                                                return new HtmlString(
                                                    '<div style="display: flex; flex-direction: column; gap: 8px; margin-top: 4px;">' .
                                                        implode('', $links) .
                                                    '</div>'
                                                );
                                            }),
                                    ]),
                            ]),
                    ]),
            ]);
    }
}
