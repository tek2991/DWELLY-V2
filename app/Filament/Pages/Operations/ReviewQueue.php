<?php

namespace App\Filament\Pages\Operations;

use App\Domain\Audit\Models\Audit;
use Filament\Pages\Page;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Actions\Action;
use Illuminate\Database\Eloquent\Builder;

class ReviewQueue extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.pages.operations.review-queue';

    public static function getNavigationIcon(): string|\Illuminate\Contracts\Support\Htmlable|null
    {
        return 'heroicon-o-inbox-stack';
    }

    public static function getNavigationSort(): ?int
    {
        return 3;
    }

    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return 'Review Queue';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Operations';
    }

    public static function getNavigationLabel(): string
    {
        return 'Review Queue';
    }

    public static function canAccess(): bool
    {
        return auth()->user()->can('audit.review') || auth()->user()->can('audit.approve');
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
                    ->label(fn (Audit $record) => $record->reviewer_id === auth()->id() ? 'Continue Review' : ($record->reviewer_id ? 'View' : 'Claim & Review'))
                    ->icon('heroicon-o-magnifying-glass')
                    ->action(function (Audit $record) {
                        if (!$record->reviewer_id && (auth()->user()->can('audit.review') || auth()->user()->can('audit.approve'))) {
                            $record->update(['reviewer_id' => auth()->id()]);
                        }
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
            'assigned' => \Filament\Resources\Components\Tab::make('Assigned To Me')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('reviewer_id', auth()->id())),
            'unassigned' => \Filament\Resources\Components\Tab::make('Unassigned')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNull('reviewer_id')),
            'all' => \Filament\Resources\Components\Tab::make('All Pending'),
        ];
    }
}
