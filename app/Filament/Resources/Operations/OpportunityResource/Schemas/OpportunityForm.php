<?php

namespace App\Filament\Resources\Operations\OpportunityResource\Schemas;

use App\Domain\Opportunity\Enums\OpportunityStatus;
use App\Domain\Opportunity\Models\Opportunity;
use App\Filament\Resources\Operations\MOUResource;
use App\Filament\Resources\Properties\PropertyResource;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class OpportunityForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(3)
                ->columnSpanFull()
                ->schema([
                    // Main Left Column (2 Cols)
                    Grid::make(1)
                        ->columnSpan(2)
                        ->schema([
                            // 📍 Section 1: Lead Information & Source
                            Section::make('📍 Lead Information & Source')
                                ->schema([
                                    TextInput::make('title')
                                        ->label('Opportunity Title')
                                        ->placeholder('e.g. 3BHK Apartment in Whitefield / Luxury Villa in Indiranagar')
                                        ->required()
                                        ->maxLength(255)
                                        ->columnSpanFull(),

                                    Select::make('opportunity_source_id')
                                        ->label('Lead Source')
                                        ->relationship('opportunitySource', 'name')
                                        ->searchable()
                                        ->preload()
                                        ->placeholder('Select Source (e.g. Referral, Website, Direct)'),

                                    TextInput::make('source_phone')
                                        ->label('Source Phone Number')
                                        ->tel()
                                        ->placeholder('Contact number for lead referrer')
                                        ->maxLength(255),

                                    Select::make('assigned_user_id')
                                        ->label('Assigned Relationship Manager')
                                        ->relationship('assignedUser', 'name')
                                        ->searchable()
                                        ->preload()
                                        ->required()
                                        ->default(fn () => auth()->id())
                                        ->helperText('Staff member responsible for qualifying and converting this opportunity.'),

                                    DatePicker::make('expected_onboarding_date')
                                        ->label('Expected Onboarding Date')
                                        ->placeholder('Select target date')
                                        ->native(false),
                                ])->columns(2),

                            // 🏢 Section 2: Property Estimates & Specifications
                            Section::make('🏢 Property Estimates & Specifications')
                                ->schema([
                                    Select::make('estimated_property_type_id')
                                        ->label('Estimated Property Type')
                                        ->relationship('estimatedPropertyType', 'name')
                                        ->searchable()
                                        ->preload()
                                        ->disabled(fn (?Opportunity $record) => $record && $record->status === OpportunityStatus::READY_FOR_MOU)
                                        ->helperText(fn (?Opportunity $record) => $record && $record->status === OpportunityStatus::READY_FOR_MOU ? '🔒 Locked once marked Ready for MOU' : null),

                                    Select::make('estimated_bhk')
                                        ->label('BHK Configuration')
                                        ->options([
                                            '1 RK' => '🚪 1 RK',
                                            '1 BHK' => '🏠 1 BHK',
                                            '2 BHK' => '🏠 2 BHK',
                                            '3 BHK' => '🏠 3 BHK',
                                            '4 BHK' => '🏡 4 BHK',
                                            '5+ BHK' => '🏰 5+ BHK',
                                            'Villa' => '🏡 Villa',
                                            'Independent House' => '🏘 Independent House',
                                        ])
                                        ->searchable()
                                        ->placeholder('Select Configuration'),

                                    TextInput::make('estimated_size')
                                        ->label('Estimated Size (Sq.Ft.)')
                                        ->placeholder('e.g. 1450')
                                        ->numeric()
                                        ->suffix('Sq.Ft.'),

                                    Toggle::make('estimated_is_furnished')
                                        ->label('Furnished Property')
                                        ->inline(false)
                                        ->helperText('Check if the unit comes fully or semi-furnished.'),
                                ])->columns(2),

                            // 💰 Section 3: Commercial Estimates & Terms
                            Section::make('💰 Commercial Estimates & Terms')
                                ->schema([
                                    TextInput::make('expected_rent')
                                        ->label('Expected Monthly Rent')
                                        ->placeholder('e.g. 45000')
                                        ->numeric()
                                        ->prefix('₹')
                                        ->disabled(fn (?Opportunity $record) => $record && $record->status === OpportunityStatus::READY_FOR_MOU)
                                        ->helperText(fn (?Opportunity $record) => $record && $record->status === OpportunityStatus::READY_FOR_MOU ? '🔒 Locked once marked Ready for MOU' : 'Target monthly rental income expected by owner.'),

                                    Select::make('expected_financial_model_id')
                                        ->label('Expected Financial Model')
                                        ->relationship('expectedFinancialModel', 'name')
                                        ->searchable()
                                        ->preload()
                                        ->placeholder('Select Financial Model'),
                                ])->columns(2),

                            // 📝 Section 4: Internal Summary
                            Section::make('📝 Internal Notes & Summary')
                                ->schema([
                                    Textarea::make('internal_summary')
                                        ->label('Lead Assessment & Special Remarks')
                                        ->placeholder('Record meeting notes, owner expectations, property condition highlights, or commercial negotiation points...')
                                        ->rows(4)
                                        ->maxLength(65535)
                                        ->columnSpanFull(),
                                ]),
                        ]),

                    // Right Sidebar (1 Col)
                    Grid::make(1)
                        ->columnSpan(1)
                        ->schema([
                            // 👤 Owner / Lead Contact Section
                            Section::make('👤 Owner Contact Profile')
                                ->schema([
                                    TextInput::make('owner_name')
                                        ->label('Owner Full Name')
                                        ->placeholder('e.g. Rajesh Kumar')
                                        ->maxLength(255)
                                        ->required(),

                                    TextInput::make('owner_phone')
                                        ->label('Owner Phone')
                                        ->tel()
                                        ->placeholder('e.g. 9876543210')
                                        ->maxLength(255)
                                        ->required(),

                                    TextInput::make('owner_email')
                                        ->label('Owner Email')
                                        ->email()
                                        ->placeholder('e.g. rajesh@example.com')
                                        ->maxLength(255),

                                    Textarea::make('address')
                                        ->label('Owner / Property Address')
                                        ->placeholder('Street address, flat/villa number, locality...')
                                        ->rows(3)
                                        ->columnSpanFull(),
                                ]),

                            // ⚡ Pipeline Status & Conversion Hub
                            Section::make('⚡ Pipeline Status & Conversion')
                                ->hiddenOn('create')
                                ->schema([
                                    Placeholder::make('opportunity_status_badge')
                                        ->label('Pipeline Status')
                                        ->content(function (?Opportunity $record) {
                                            if (! $record) {
                                                return new HtmlString('<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-blue-100 text-blue-800 dark:bg-blue-900/60 dark:text-blue-200">✨ New Lead</span>');
                                            }

                                            $status = $record->status ?? OpportunityStatus::NEW;
                                            $label = e($status->getLabel());
                                            $color = match ($status) {
                                                OpportunityStatus::NEW => 'bg-blue-100 text-blue-800 dark:bg-blue-900/60 dark:text-blue-200',
                                                OpportunityStatus::CONTACTED => 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/60 dark:text-indigo-200',
                                                OpportunityStatus::SITE_VISIT_SCHEDULED, OpportunityStatus::SITE_VISIT_COMPLETED => 'bg-amber-100 text-amber-800 dark:bg-amber-900/60 dark:text-amber-200',
                                                OpportunityStatus::NEGOTIATION => 'bg-purple-100 text-purple-800 dark:bg-purple-900/60 dark:text-purple-200',
                                                OpportunityStatus::READY_FOR_MOU => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/60 dark:text-emerald-200 font-bold',
                                                OpportunityStatus::MOU_CREATED => 'bg-sky-100 text-sky-800 dark:bg-sky-900/60 dark:text-sky-200',
                                                OpportunityStatus::MOU_SIGNED, OpportunityStatus::CONVERTED => 'bg-green-100 text-green-800 dark:bg-green-900/60 dark:text-green-200 font-bold',
                                                OpportunityStatus::CLOSED_LOST, OpportunityStatus::CANCELLED => 'bg-red-100 text-red-800 dark:bg-red-900/60 dark:text-red-200',
                                            };

                                            return new HtmlString("<div class=\"flex items-center gap-1.5 flex-wrap\"><span class=\"inline-flex items-center px-3 py-1 rounded-full text-xs font-bold {$color}\">{$label}</span></div>");
                                        }),

                                    Placeholder::make('opportunity_number')
                                        ->label('Opportunity ID')
                                        ->content(function (?Opportunity $record) {
                                            $num = $record?->number;
                                            if (! $num) {
                                                return new HtmlString('<span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-mono text-gray-400 bg-gray-50 dark:bg-gray-800 border border-dashed border-gray-300 dark:border-gray-700">Auto-generated</span>');
                                            }

                                            return new HtmlString("<span class=\"inline-flex items-center px-3 py-1 rounded-md text-xs font-mono font-bold bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200 border border-gray-300 dark:border-gray-700\">#{$num}</span>");
                                        }),

                                    Placeholder::make('pipeline_guidance_banner')
                                        ->hiddenLabel()
                                        ->columnSpanFull()
                                        ->content(function (?Opportunity $record) {
                                            if (! $record) {
                                                return null;
                                            }

                                            if ($record->status === OpportunityStatus::READY_FOR_MOU && ! $record->mou) {
                                                return new HtmlString(
                                                    '<div style="background-color: rgba(16, 185, 129, 0.08); border-left: 4px solid #10b981; padding: 10px 14px; border-radius: 6px; font-size: 13px; color: #065f46;">' .
                                                    '<strong>✅ Ready for MOU:</strong> Commercials & contacts are verified. Click <strong>Create MOU</strong> in header actions to generate the onboarding agreement.' .
                                                    '</div>'
                                                );
                                            }

                                            if ($record->status === OpportunityStatus::CLOSED_LOST) {
                                                return new HtmlString(
                                                    '<div style="background-color: rgba(239, 68, 68, 0.08); border-left: 4px solid #ef4444; padding: 10px 14px; border-radius: 6px; font-size: 13px; color: #991b1b;">' .
                                                    '<strong>❌ Closed / Lost:</strong> This opportunity has been marked as lost.' .
                                                    '</div>'
                                                );
                                            }

                                            return null;
                                        })
                                        ->visible(fn (?Opportunity $record) => $record && ($record->status === OpportunityStatus::READY_FOR_MOU || $record->status === OpportunityStatus::CLOSED_LOST)),

                                    // Associated MOU Card
                                    Placeholder::make('mou_bridge_card')
                                        ->hiddenLabel()
                                        ->columnSpanFull()
                                        ->visible(fn (?Opportunity $record) => $record?->mou !== null)
                                        ->content(function (?Opportunity $record) {
                                            $mou = $record?->mou;
                                            if (! $mou) {
                                                return '';
                                            }

                                            $mouUrl = MOUResource::getUrl('view', ['record' => $mou]);
                                            $mouStatus = e($mou->status?->getLabel() ?? ucfirst((string) $mou->status));
                                            $mouNumber = e($mou->number);

                                            return new HtmlString(
                                                '<div style="background-color: rgba(37, 99, 235, 0.04); border: 1px solid rgba(37, 99, 235, 0.2); border-radius: 8px; padding: 14px; font-size: 13px;">' .
                                                '<div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">' .
                                                '<div><strong style="font-size: 14px;">📄 MOU #' . $mouNumber . '</strong></div>' .
                                                '<span style="padding: 2px 8px; font-size: 11px; border-radius: 4px; background: #2563eb; color: #fff; font-weight: 600;">' . $mouStatus . '</span>' .
                                                '</div>' .
                                                '<a href="' . e($mouUrl) . '" style="display: inline-flex; align-items: center; gap: 4px; padding: 5px 12px; background-color: #2563eb; color: #fff; font-weight: 600; font-size: 12px; border-radius: 6px; text-decoration: none; margin-top: 4px;">Open MOU Workspace &rarr;</a>' .
                                                '</div>'
                                            );
                                        }),

                                    // Associated Property Card
                                    Placeholder::make('property_bridge_card')
                                        ->hiddenLabel()
                                        ->columnSpanFull()
                                        ->visible(fn (?Opportunity $record) => $record?->mou?->property !== null)
                                        ->content(function (?Opportunity $record) {
                                            $property = $record?->mou?->property;
                                            if (! $property) {
                                                return '';
                                            }

                                            $propUrl = PropertyResource::getUrl('edit', ['record' => $property]);
                                            $propCode = e($property->code ?? "Property #{$property->id}");
                                            $building = e($property->building_name ?: 'Building');

                                            return new HtmlString(
                                                '<div style="background-color: rgba(16, 185, 129, 0.04); border: 1px solid rgba(16, 185, 129, 0.2); border-radius: 8px; padding: 14px; font-size: 13px;">' .
                                                '<div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">' .
                                                '<div><strong style="font-size: 14px;">🏢 ' . $propCode . '</strong><br><span style="color: gray; font-size: 12px;">' . $building . '</span></div>' .
                                                '<span style="padding: 2px 8px; font-size: 11px; border-radius: 4px; background: #10b981; color: #fff; font-weight: 600;">ONBOARDED</span>' .
                                                '</div>' .
                                                '<a href="' . e($propUrl) . '" style="display: inline-flex; align-items: center; gap: 4px; padding: 5px 12px; background-color: #10b981; color: #fff; font-weight: 600; font-size: 12px; border-radius: 6px; text-decoration: none; margin-top: 4px;">View Property Profile &rarr;</a>' .
                                                '</div>'
                                            );
                                        }),
                                ]),
                        ]),
                ]),
        ]);
    }
}
