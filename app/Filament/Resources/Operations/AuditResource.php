<?php

namespace App\Filament\Resources\Operations;

use App\Domain\Audit\Models\Audit;
use App\Domain\Audit\Enums\AuditType;
use App\Domain\Audit\Enums\AuditStatus;
use App\Filament\Resources\Operations\AuditResource\Pages;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Carbon;

class AuditResource extends Resource
{
    protected static ?string $model = Audit::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-clipboard-document-check';
    
    protected static \UnitEnum|string|null $navigationGroup = 'Operations'; // Wait, let's just leave this out or use 'Portfolio & Operations'
    
    // I will dynamically set it below.
    
    public static function getNavigationGroup(): ?string
    {
        return 'Portfolio & Operations';
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return $record->status !== AuditStatus::APPROVED;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                \Filament\Schemas\Components\Group::make()->schema([
                    \Filament\Schemas\Components\Section::make('Audit Details')
                        ->schema([
                            Forms\Components\Select::make('property_id')
                                ->relationship('property', 'code')
                                ->getOptionLabelFromRecordUsing(fn ($record) => $record->building_name . ($record->code ? ' (' . $record->code . ')' : ''))
                                ->searchable()
                                ->preload()
                                ->required()
                                ->default(request()->query('property_id'))
                                ->disabled(fn (string $operation): bool => $operation === 'edit' || request()->has('property_id'))
                                ->dehydrated()
                                ->live()
                                ->afterStateUpdated(function (Set $set, $state) {
                                    if (!$state) {
                                        $set('reference_audit_id', null);
                                        return;
                                    }
                                    $latestAudit = \App\Domain\Audit\Models\Audit::where('property_id', $state)
                                        ->whereIn('status', [AuditStatus::COMPLETED, AuditStatus::APPROVED])
                                        ->orderBy('created_at', 'desc')
                                        ->first();
                                    if ($latestAudit) {
                                        $set('reference_audit_id', $latestAudit->id);
                                    }
                                }),

                            Forms\Components\Select::make('audit_type')
                                ->options(AuditType::class)
                                ->required()
                                ->default(request()->query('audit_type'))
                                ->disabled(fn (string $operation): bool => $operation === 'edit'),

                            Forms\Components\Select::make('reference_audit_id')
                                ->label('Reference Audit')
                                ->options(function (Get $get, ?Audit $record) {
                                    $propertyId = $get('property_id');
                                    if (!$propertyId) return [];
                                    
                                    $query = Audit::where('property_id', $propertyId)
                                        ->whereIn('status', [AuditStatus::COMPLETED, AuditStatus::APPROVED]);
                                        
                                    if ($record) {
                                        $query->where('created_at', '<', $record->created_at);
                                    }
                                    
                                    return $query->get()->mapWithKeys(function ($audit) {
                                        return [$audit->id => $audit->audit_number . ' (' . $audit->audit_type->getLabel() . ')'];
                                    });
                                })
                                ->searchable()
                                ->preload()
                                ->hint('Used for comparisons and preloading in Phase 2'),
                                
                            Forms\Components\Select::make('inspector_id')
                                ->relationship('inspector', 'name')
                                ->searchable()
                                ->preload()
                                ->default(fn () => auth()->id()),
                                
                            Forms\Components\DatePicker::make('scheduled_at')
                                ->label('Scheduled Date'),
                        ])->columns(2),

                    \Filament\Schemas\Components\Section::make('Notes')
                        ->schema([
                            Forms\Components\Textarea::make('notes')
                                ->maxLength(65535)
                                ->columnSpanFull(),
                        ]),
                ])->columnSpan(['lg' => 2]),

                \Filament\Schemas\Components\Group::make()->schema([
                    \Filament\Schemas\Components\Section::make('Status')
                        ->schema([
                            Forms\Components\Placeholder::make('audit_number')
                                ->label('Audit Number')
                                ->content(fn (?Audit $record): string => $record?->audit_number ?? 'Auto-generated'),
                                
                            Forms\Components\Placeholder::make('status')
                                ->content(function (?Audit $record): \Illuminate\Support\HtmlString {
                                    $label = $record?->status?->getLabel() ?? 'Draft';
                                    $color = match ($record?->status) {
                                        AuditStatus::IN_PROGRESS => 'text-info-600',
                                        AuditStatus::COMPLETED => 'text-success-600',
                                        AuditStatus::APPROVED => 'text-primary-600',
                                        default => 'text-gray-600',
                                    };
                                    return new \Illuminate\Support\HtmlString("<span class=\"font-medium {$color}\">{$label}</span>");
                                }),
                                
                            Forms\Components\Placeholder::make('completed_by_id')
                                ->label('Completed By')
                                ->content(fn (?Audit $record): ?string => $record?->completedBy?->name ?? '-')
                                ->visible(fn (?Audit $record) => $record && $record->completed_at),
                                
                            Forms\Components\Placeholder::make('approved_by_id')
                                ->label('Approved By')
                                ->content(fn (?Audit $record): ?string => $record?->approvedBy?->name ?? '-')
                                ->visible(fn (?Audit $record) => $record && $record->approved_at),
                        ]),
                ])->columnSpan(['lg' => 1]),

                \Filament\Schemas\Components\Group::make()->schema([
                    \Filament\Schemas\Components\View::make('filament.forms.components.audit-inspection-view')
                        ->visible(fn (?Audit $record) => $record !== null)
                ])->columnSpan(['lg' => 3]),
            ])
            ->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('audit_number')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('property.code')
                    ->searchable(),
                Tables\Columns\TextColumn::make('audit_type')
                    ->badge(),
                Tables\Columns\TextColumn::make('status')
                    ->badge(),
                Tables\Columns\TextColumn::make('inspector.name')
                    ->label('Inspector'),
                Tables\Columns\TextColumn::make('scheduled_at')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Last Updated')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(AuditStatus::class),
                Tables\Filters\SelectFilter::make('audit_type')
                    ->options(AuditType::class),
            ])
            ->actions([
                \Filament\Actions\EditAction::make()
                    ->visible(fn ($record) => static::canEdit($record)),
                
                \Filament\Actions\ActionGroup::make([
                    \Filament\Actions\Action::make('startAudit')
                        ->label('Start Audit')
                        ->icon('heroicon-o-play')
                        ->color('info')
                        ->visible(fn (Audit $record) => $record->status === AuditStatus::DRAFT)
                        ->action(function (Audit $record) {
                            $record->update(['status' => AuditStatus::IN_PROGRESS]);
                        }),
                        
                    \Filament\Actions\Action::make('submitForReview')
                        ->label('Submit for Review')
                        ->icon('heroicon-o-paper-airplane')
                        ->color('success')
                        ->visible(fn (Audit $record) => $record->canSubmit())
                        ->action(function (Audit $record) {
                            app(\App\Domain\Audit\Services\AuditReviewService::class)->submitForReview($record);
                        }),
                ])->label('Transitions'),
            ])
            ->bulkActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAudits::route('/'),
            'create' => Pages\CreateAudit::route('/create'),
            'edit' => Pages\EditAudit::route('/{record}/edit'),
            'review' => Pages\ReviewAudit::route('/{record}/review'),
        ];
    }
}
