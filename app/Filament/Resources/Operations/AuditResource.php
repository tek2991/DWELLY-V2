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

    protected static ?string $cluster = \App\Filament\Clusters\AuditsCluster::class;

    protected static ?string $navigationLabel = 'All Audits';

    protected static ?int $navigationSort = 1;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return $record->status !== AuditStatus::APPROVED && !$record->is_locked;
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
                                ->getOptionLabelFromRecordUsing(fn($record) => $record->building_name . ($record->code ? ' (' . $record->code . ')' : ''))
                                ->searchable()
                                ->preload()
                                ->required()
                                ->default(request()->query('property_id'))
                                ->disabled(fn(string $operation): bool => $operation === 'edit' || request()->has('property_id'))
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
                                ->disabled(fn(string $operation): bool => $operation === 'edit'),

                            Forms\Components\Select::make('tenant_id')
                                ->label('Linked Tenant')
                                ->relationship('tenant', 'display_name')
                                ->searchable()
                                ->preload()
                                ->placeholder('Select tenant (if applicable)'),

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
                                ->preload(),

                            Forms\Components\Select::make('inspector_id')
                                ->label('Assigned Inspector')
                                ->relationship('inspector', 'name')
                                ->searchable()
                                ->preload()
                                ->required()
                                ->default(fn() => auth()->id()),

                            Forms\Components\Select::make('reviewer_id')
                                ->label('Assigned Reviewer')
                                ->relationship('reviewer', 'name')
                                ->searchable()
                                ->preload()
                                ->default(fn() => auth()->id()),

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
                                ->content(fn(?Audit $record): string => $record?->audit_number ?? 'Auto-generated'),

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
                                ->content(fn(?Audit $record): ?string => $record?->completedBy?->name ?? '-')
                                ->visible(fn(?Audit $record) => $record && $record->completed_at),

                            Forms\Components\Placeholder::make('approved_by_id')
                                ->label('Approved By')
                                ->content(fn(?Audit $record): ?string => $record?->approvedBy?->name ?? '-')
                                ->visible(fn(?Audit $record) => $record && $record->approved_at),
                        ]),
                ])->columnSpan(['lg' => 1]),
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
                Tables\Columns\TextColumn::make('tenant.display_name')
                    ->label('Tenant')
                    ->searchable()
                    ->placeholder('N/A'),
                Tables\Columns\TextColumn::make('audit_type')
                    ->label('Type & Status')
                    ->html()
                    ->formatStateUsing(function ($state, Audit $record) {
                        $typeLabel = $record->audit_type?->getLabel() ?? '-';
                        $typeColor = $record->audit_type?->getColor() ?? 'gray';
                        $statusLabel = $record->status?->getLabel() ?? '-';
                        $statusColor = $record->status?->getColor() ?? 'gray';

                        $getBadgeStyle = function (string $color): string {
                            return match ($color) {
                                'success' => 'background-color: #dcfce7; color: #15803d; border: 1px solid #bbf7d0;',
                                'warning' => 'background-color: #fef3c7; color: #b45309; border: 1px solid #fde68a;',
                                'danger' => 'background-color: #fee2e2; color: #b91c1c; border: 1px solid #fecaca;',
                                'info' => 'background-color: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd;',
                                'primary' => 'background-color: #e0e7ff; color: #4338ca; border: 1px solid #c7d2fe;',
                                'purple' => 'background-color: #f3e8ff; color: #6b21a8; border: 1px solid #e9d5ff;',
                                'teal' => 'background-color: #ccfbf1; color: #0f766e; border: 1px solid #99f6e4;',
                                default => 'background-color: #f1f5f9; color: #334155; border: 1px solid #cbd5e1;',
                            };
                        };

                        $baseStyle = 'display: inline-flex; align-items: center; padding: 2px 8px; border-radius: 6px; font-size: 0.75rem; font-weight: 600; line-height: 1.25;';

                        $typeBadge = '<span style="' . $baseStyle . ' ' . $getBadgeStyle($typeColor) . '">' . e($typeLabel) . '</span>';
                        $statusBadge = '<span style="' . $baseStyle . ' ' . $getBadgeStyle($statusColor) . '">' . e($statusLabel) . '</span>';

                        $lockedStyle = 'background-color: #ffe4e6; color: #be123c; border: 1px solid #fecdd3; gap: 4px;';
                        $lockedBadge = $record->is_locked
                            ? '<span style="' . $baseStyle . ' ' . $lockedStyle . '" title="Audit is locked"><svg style="width: 0.75rem; height: 0.75rem; color: #be123c;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>Locked</span>'
                            : '';

                        return '<div style="display: flex; flex-wrap: wrap; align-items: center; gap: 6px;">' .
                            $typeBadge .
                            $statusBadge .
                            $lockedBadge .
                            '</div>';
                    }),
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
                Tables\Filters\TernaryFilter::make('is_locked')
                    ->label('Locked Status'),
            ])
            ->actions([])
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
            'inspect' => Pages\InspectAudit::route('/{record}/inspect'),
            'review' => Pages\ReviewAudit::route('/{record}/review'),
        ];
    }
}
