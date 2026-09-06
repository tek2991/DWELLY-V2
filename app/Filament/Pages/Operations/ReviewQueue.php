<?php

namespace App\Filament\Pages\Operations;

use App\Domain\Audit\Models\Audit;
use App\Filament\Clusters\AuditsCluster;
use App\Filament\Resources\Operations\AuditResource;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Resources\Components\Tab;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;

class ReviewQueue extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.pages.operations.review-queue';

    protected static ?string $cluster = AuditsCluster::class;

    protected static ?string $navigationLabel = 'Audit Review Queue';

    protected static ?int $navigationSort = 3;

    public function getTitle(): string|Htmlable
    {
        return 'Audit Review Queue';
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        if ($user->roles->isEmpty()) {
            return true;
        }

        // 4-Eyes Gate: Operations Executives CANNOT access the Audit Review Queue
        if ($user->hasRole('Operations Executive') && ! $user->hasAnyRole(['Business Owner', 'City Manager', 'Operations Manager'])) {
            return false;
        }

        return $user->can('audit.review') || $user->hasAnyRole(['Business Owner', 'City Manager', 'Operations Manager']);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Audit::query()->whereIn('status', ['pending_review', 'in_review'])
            )
            ->columns([
                TextColumn::make('id')->label('ID')->sortable(),
                TextColumn::make('property.code')->label('Property')->sortable()->searchable(),
                TextColumn::make('status')->badge(),
                TextColumn::make('submitted_at')->dateTime()->sortable(),
                TextColumn::make('reviewer.name')->label('Reviewer')->placeholder('Unassigned'),
            ])
            ->actions([
                Action::make('review')
                    ->label(fn (Audit $record) => $record->reviewer_id === auth()->id() ? 'Review Audit' : 'View Audit')
                    ->icon('heroicon-o-magnifying-glass')
                    ->action(function (Audit $record) {
                        return redirect(AuditResource::getUrl('review', ['record' => $record]));
                    }),
            ])
            ->modifyQueryUsing(function (Builder $query) {
                // Determine active tab context if any, but we will rely on Filament Tabs in a custom way or standard tabs
            });
    }

    public function getTabs(): array
    {
        return [
            'assigned' => Tab::make('Assigned To Me')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('reviewer_id', auth()->id())),
            'unassigned' => Tab::make('Unassigned')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNull('reviewer_id')),
            'all' => Tab::make('All Pending'),
        ];
    }
}
