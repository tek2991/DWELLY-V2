<?php

namespace App\Filament\Resources\Operations;

use App\Domain\Opportunity\Models\Opportunity;
use App\Domain\Opportunity\Enums\OpportunityStatus;
use App\Filament\Resources\Operations\OpportunityResource\Pages;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Domain\Opportunity\Services\OpportunityWorkflowService;

class OpportunityResource extends Resource
{
    protected static ?string $model = Opportunity::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-briefcase';
    
    protected static \UnitEnum|string|null $navigationGroup = 'Operations';
    
    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                \Filament\Schemas\Components\Group::make()->schema([
                    \Filament\Schemas\Components\Section::make('Basic Information')
                        ->schema([
                            Forms\Components\TextInput::make('title')
                                ->required()
                                ->maxLength(255),
                            Forms\Components\Select::make('opportunity_source_id')
                                ->relationship('opportunitySource', 'name')
                                ->searchable()
                                ->preload(),
                            Forms\Components\Select::make('assigned_user_id')
                                ->relationship('assignedUser', 'name')
                                ->searchable()
                                ->preload()
                                ->default(fn () => auth()->id()),
                        ])->columns(2),

                    \Filament\Schemas\Components\Section::make('Property Estimates')
                        ->schema([
                            Forms\Components\Select::make('estimated_property_type_id')
                                ->relationship('estimatedPropertyType', 'name')
                                ->searchable()
                                ->preload(),
                            Forms\Components\Select::make('estimated_bhk')
                                ->options([
                                    '1 RK' => '1 RK',
                                    '1 BHK' => '1 BHK',
                                    '2 BHK' => '2 BHK',
                                    '3 BHK' => '3 BHK',
                                    '4 BHK' => '4 BHK',
                                    '5+ BHK' => '5+ BHK',
                                    'Villa' => 'Villa',
                                    'Independent House' => 'Independent House',
                                ])
                                ->searchable(),
                            Forms\Components\TextInput::make('estimated_size')
                                ->numeric(),
                            Forms\Components\Toggle::make('estimated_is_furnished'),
                        ])->columns(2),

                    \Filament\Schemas\Components\Section::make('Commercials & Dates')
                        ->schema([
                            Forms\Components\TextInput::make('expected_rent')
                                ->numeric()
                                ->prefix('₹'),
                            Forms\Components\Select::make('expected_financial_model_id')
                                ->relationship('expectedFinancialModel', 'name')
                                ->searchable()
                                ->preload(),
                            Forms\Components\DatePicker::make('expected_onboarding_date'),
                        ])->columns(2),
                        
                    \Filament\Schemas\Components\Section::make('Internal Summary')
                        ->schema([
                            Forms\Components\Textarea::make('internal_summary')
                                ->maxLength(65535)
                                ->columnSpanFull(),
                        ]),
                ])->columnSpan(['lg' => 2]),

                \Filament\Schemas\Components\Group::make()->schema([
                    \Filament\Schemas\Components\Section::make('Owner Information')
                        ->schema([
                            Forms\Components\TextInput::make('owner_name')
                                ->label('Owner Name')
                                ->maxLength(255)
                                ->required(),
                            Forms\Components\TextInput::make('owner_phone')
                                ->label('Owner Phone')
                                ->tel()
                                ->maxLength(255),
                            Forms\Components\TextInput::make('owner_email')
                                ->label('Owner Email')
                                ->email()
                                ->maxLength(255),
                            
                            Forms\Components\Textarea::make('address')
                                ->label('Address')
                                ->columnSpanFull(),
                        ]),
                        
                    \Filament\Schemas\Components\Section::make('Status')
                        ->schema([
                            Forms\Components\Placeholder::make('number')
                                ->content(fn (?Opportunity $record): string => $record?->number ?? 'Auto-generated'),
                            Forms\Components\Placeholder::make('status')
                                ->content(fn (?Opportunity $record): string => $record?->status?->getLabel() ?? 'New'),
                            Forms\Components\Placeholder::make('mou_status')
                                ->content(fn (?Opportunity $record): string => $record?->mou_status?->getLabel() ?? '-'),
                        ])->hiddenOn('create'),
                ])->columnSpan(['lg' => 1]),
            ])
            ->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('number')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('title')
                    ->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge(),
                Tables\Columns\TextColumn::make('owner_name')
                    ->label('Owner')
                    ->searchable(),
                Tables\Columns\TextColumn::make('assignedUser.name')
                    ->label('Assigned To'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordUrl(
                fn (Opportunity $record): string => static::getUrl('view', ['record' => $record]),
            )
            ->filters([
                Tables\Filters\TrashedFilter::make(),
                Tables\Filters\SelectFilter::make('status')
                    ->options(OpportunityStatus::class),
            ])
            ->actions([
                \Filament\Actions\ViewAction::make(),
                \Filament\Actions\EditAction::make(),
                // Workflow Actions
                \Filament\Actions\ActionGroup::make([
                    \Filament\Actions\Action::make('markContacted')
                        ->label('Mark Contacted')
                        ->icon('heroicon-o-phone')
                        ->color('primary')
                        ->visible(fn (Opportunity $record) => $record->status === OpportunityStatus::NEW)
                        ->form([
                            Forms\Components\Textarea::make('notes')->label('Notes'),
                        ])
                        ->action(fn (Opportunity $record, array $data) => app(OpportunityWorkflowService::class)->markContacted($record, $data['notes'] ?? null)),

                    \Filament\Actions\Action::make('scheduleSiteVisit')
                        ->label('Schedule Site Visit')
                        ->icon('heroicon-o-calendar')
                        ->color('warning')
                        ->visible(fn (Opportunity $record) => in_array($record->status, [OpportunityStatus::NEW, OpportunityStatus::CONTACTED]))
                        ->form([
                            Forms\Components\DatePicker::make('date')->required(),
                            Forms\Components\Textarea::make('notes')->label('Notes'),
                        ])
                        ->action(fn (Opportunity $record, array $data) => app(OpportunityWorkflowService::class)->scheduleSiteVisit($record, $data['date'], $data['notes'] ?? null)),
                        
                    \Filament\Actions\Action::make('completeSiteVisit')
                        ->label('Complete Site Visit')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->visible(fn (Opportunity $record) => $record->status === OpportunityStatus::SITE_VISIT_SCHEDULED)
                        ->form([
                            Forms\Components\Textarea::make('notes')->label('Notes'),
                        ])
                        ->action(fn (Opportunity $record, array $data) => app(OpportunityWorkflowService::class)->completeSiteVisit($record, $data['notes'] ?? null)),
                        
                    \Filament\Actions\Action::make('startNegotiation')
                        ->label('Start Negotiation')
                        ->icon('heroicon-o-chat-bubble-left-right')
                        ->color('purple')
                        ->visible(fn (Opportunity $record) => in_array($record->status, [OpportunityStatus::SITE_VISIT_COMPLETED, OpportunityStatus::CONTACTED]))
                        ->form([
                            Forms\Components\Textarea::make('notes')->label('Notes'),
                        ])
                        ->action(fn (Opportunity $record, array $data) => app(OpportunityWorkflowService::class)->startNegotiation($record, $data['notes'] ?? null)),
                        
                    \Filament\Actions\Action::make('generateMOU')
                        ->label('Prepare MOU')
                        ->icon('heroicon-o-document-text')
                        ->color('warning')
                        ->visible(fn (Opportunity $record) => !in_array($record->status, [OpportunityStatus::MOU_PENDING, OpportunityStatus::MOU_SIGNED, OpportunityStatus::CLOSED_LOST, OpportunityStatus::CANCELLED, OpportunityStatus::CONVERTED]))
                        ->steps([
                            \Filament\Schemas\Components\Wizard\Step::make('Legal Entity')
                                ->description('Who are we signing with?')
                                ->schema([
                                    Forms\Components\Radio::make('party_type')
                                        ->label('Entity Type')
                                        ->options([
                                            'individual' => 'Individual',
                                            'company' => 'Company',
                                        ])
                                        ->required()
                                        ->live(),
                                    Forms\Components\TextInput::make('legal_name')
                                        ->label(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('party_type') === 'company' ? 'Company Name' : 'Full Name')
                                        ->required(),
                                    Forms\Components\TextInput::make('pan_number')
                                        ->label('PAN Number')
                                        ->required(),
                                    Forms\Components\TextInput::make('gst_number')
                                        ->label('GST Number')
                                        ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('party_type') === 'company'),
                                    Forms\Components\TextInput::make('aadhar_number')
                                        ->label('Aadhar Number')
                                        ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('party_type') === 'individual'),
                                ]),
                            \Filament\Schemas\Components\Wizard\Step::make('Bank Details')
                                ->description('For payouts')
                                ->schema([
                                    Forms\Components\TextInput::make('bank_name')->required(),
                                    Forms\Components\TextInput::make('account_number')->required(),
                                    Forms\Components\TextInput::make('ifsc_code')->required(),
                                    Forms\Components\TextInput::make('account_holder_name')->required(),
                                ]),
                            \Filament\Schemas\Components\Wizard\Step::make('Legal Terms')
                                ->description('Core commercial terms')
                                ->schema([
                                    Forms\Components\TextInput::make('rent_amount')
                                        ->label('Agreed Rent (INR)')
                                        ->numeric()
                                        ->required()
                                        ->default(fn (Opportunity $record) => $record->expected_rent),
                                    Forms\Components\TextInput::make('security_deposit')
                                        ->label('Security Deposit (INR)')
                                        ->numeric()
                                        ->required(),
                                    Forms\Components\TextInput::make('lock_in_months')
                                        ->label('Lock-in Period (Months)')
                                        ->numeric()
                                        ->required(),
                                    Forms\Components\TextInput::make('notice_period_months')
                                        ->label('Notice Period (Months)')
                                        ->numeric()
                                        ->required(),
                                    Forms\Components\Textarea::make('notes')->label('Additional Notes'),
                                ]),
                        ])
                        ->action(fn (Opportunity $record, array $data) => app(OpportunityWorkflowService::class)->generateMOU($record, $data)),
                        
                    \Filament\Actions\Action::make('uploadSignedMOU')
                        ->label('Upload Signed MOU')
                        ->icon('heroicon-o-document-check')
                        ->color('success')
                        ->visible(fn (Opportunity $record) => $record->status === OpportunityStatus::MOU_PENDING)
                        ->form([
                            Forms\Components\Textarea::make('notes')->label('Notes'),
                        ])
                        ->action(fn (Opportunity $record, array $data) => app(OpportunityWorkflowService::class)->uploadSignedMOU($record, $data['notes'] ?? null)),
                        
                    \Filament\Actions\Action::make('closeLost')
                        ->label('Close Lost')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->visible(fn (Opportunity $record) => !in_array($record->status, [OpportunityStatus::CONVERTED, OpportunityStatus::CLOSED_LOST, OpportunityStatus::CANCELLED]))
                        ->form([
                            Forms\Components\Textarea::make('notes')->label('Reason for losing'),
                        ])
                        ->action(fn (Opportunity $record, array $data) => app(OpportunityWorkflowService::class)->closeLost($record, $data['notes'] ?? null)),
                ])->label('Actions'),
            ])
            ->bulkActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\DeleteBulkAction::make(),
                    \Filament\Actions\ForceDeleteBulkAction::make(),
                    \Filament\Actions\RestoreBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function infolist(\Filament\Schemas\Schema $schema): \Filament\Schemas\Schema
    {
        return $schema
            ->components([
                \Filament\Schemas\Components\Group::make([
                    \Filament\Schemas\Components\Section::make('Opportunity Details')
                        ->schema([
                            \Filament\Infolists\Components\TextEntry::make('number')->label('Number'),
                            \Filament\Infolists\Components\TextEntry::make('status')
                                ->badge(),
                            \Filament\Infolists\Components\TextEntry::make('assignedUser.name')->label('Assigned To'),
                        ])->columns(3),
                        
                    \Filament\Schemas\Components\Section::make('Owner Information')
                        ->schema([
                            \Filament\Infolists\Components\TextEntry::make('owner_name')->label('Name'),
                            \Filament\Infolists\Components\TextEntry::make('owner_phone')->label('Phone'),
                            \Filament\Infolists\Components\TextEntry::make('owner_email')->label('Email'),
                            \Filament\Infolists\Components\TextEntry::make('address')->label('Address')->columnSpanFull(),
                        ])->columns(3),
                        
                    \Filament\Schemas\Components\Section::make('Property Estimate')
                        ->schema([
                            \Filament\Infolists\Components\TextEntry::make('estimatedPropertyType.name')->label('Type'),
                            \Filament\Infolists\Components\TextEntry::make('estimated_bhk')->label('BHK'),
                            \Filament\Infolists\Components\TextEntry::make('estimated_size')->label('Size (Sq.Ft.)'),
                            \Filament\Infolists\Components\IconEntry::make('estimated_is_furnished')->boolean()->label('Furnished'),
                            \Filament\Infolists\Components\TextEntry::make('expected_rent')->money('INR')->label('Expected Rent'),
                            \Filament\Infolists\Components\TextEntry::make('expectedFinancialModel.name')->label('Financial Model'),
                        ])->columns(3),
                ])->columnSpan(['lg' => 2]),

                \Filament\Schemas\Components\Group::make([
                    \Filament\Schemas\Components\Section::make('Activity Timeline')
                        ->schema([
                            \App\Filament\Infolists\Components\ActivityTimeline::make('activities'),
                        ]),
                ])->columnSpan(['lg' => 1]),
            ])->columns(3);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOpportunities::route('/'),
            'create' => Pages\CreateOpportunity::route('/create'),
            'view' => Pages\ViewOpportunity::route('/{record}'),
            'edit' => Pages\EditOpportunity::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
