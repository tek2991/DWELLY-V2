<?php

namespace App\Filament\Pages\Operations;

use App\Domain\Audit\Enums\AuditStatus;
use App\Domain\Audit\Models\Audit;
use App\Filament\Resources\Operations\AuditResource;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class InspectionQueue extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.pages.operations.inspection-queue';

    protected static ?string $cluster = \App\Filament\Clusters\AuditsCluster::class;

    protected static ?string $navigationLabel = 'Inspection Queue';

    protected static ?int $navigationSort = 2;

    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return 'Inspection Queue';
    }

    public static function canAccess(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Audit::query()->whereIn('status', [
                    AuditStatus::DRAFT,
                    AuditStatus::IN_PROGRESS,
                    AuditStatus::PARTIALLY_APPROVED,
                ])
            )
            ->columns([
                TextColumn::make('audit_number')
                    ->label('Audit #')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('property.code')
                    ->label('Property Code')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('property.building_name')
                    ->label('Building / Address')
                    ->sortable()
                    ->searchable()
                    ->placeholder('-'),
                TextColumn::make('audit_type')
                    ->badge()
                    ->label('Type'),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('inspector.name')
                    ->label('Inspector')
                    ->placeholder('Unassigned'),
                TextColumn::make('scheduled_at')
                    ->date()
                    ->sortable()
                    ->placeholder('Not scheduled'),
            ])
            ->actions([
                Action::make('inspect')
                    ->label(function (Audit $record) {
                        if ($record->inspector_id === auth()->id()) {
                            return match ($record->status) {
                                AuditStatus::DRAFT => 'Start Inspection',
                                AuditStatus::PARTIALLY_APPROVED => 'Resolve Feedback',
                                default => 'Continue Inspection',
                            };
                        }

                        return $record->inspector_id ? 'View Inspection' : 'Claim & Inspect';
                    })
                    ->icon('heroicon-o-clipboard-document-check')
                    ->color(fn (Audit $record) => $record->status === AuditStatus::PARTIALLY_APPROVED ? 'warning' : 'primary')
                    ->action(function (Audit $record) {
                        if (!$record->inspector_id) {
                            $record->update(['inspector_id' => auth()->id()]);
                        }
                        return redirect(AuditResource::getUrl('inspect', ['record' => $record]));
                    }),
            ]);
    }

    public function getTabs(): array
    {
        return [
            'assigned' => \Filament\Resources\Components\Tab::make('Assigned To Me')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('inspector_id', auth()->id())),
            'changes_requested' => \Filament\Resources\Components\Tab::make('Changes Requested')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', AuditStatus::PARTIALLY_APPROVED)->where('inspector_id', auth()->id())),
            'unassigned' => \Filament\Resources\Components\Tab::make('Unassigned')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNull('inspector_id')),
            'all' => \Filament\Resources\Components\Tab::make('All Active'),
        ];
    }
}
