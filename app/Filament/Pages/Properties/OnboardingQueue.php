<?php

namespace App\Filament\Pages\Properties;

use App\Domain\Property\Models\Property;
use App\Domain\Property\Services\PropertyOnboardingValidator;
use App\Filament\Resources\Properties\PropertyResource;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OnboardingQueue extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.pages.properties.onboarding-queue';

    protected static ?string $cluster = \App\Filament\Clusters\PropertiesCluster::class;

    protected static ?string $navigationLabel = 'Onboarding Queue';

    protected static ?int $navigationSort = 2;

    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return 'Onboarding Queue';
    }

    public static function canAccess(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Property::query()
                    ->where(function (Builder $query) {
                        $query->where('status', 'Onboarding')
                            ->orWhereHas('onboardingProject', fn (Builder $q) => $q->where('status', '!=', 'Activated'));
                    })
                    ->with(['localityRef', 'onboardingProject', 'owner'])
                    ->latest('updated_at')
            )
            ->columns([
                TextColumn::make('code')
                    ->label('Property Code')
                    ->sortable()
                    ->searchable()
                    ->placeholder('Unassigned'),

                TextColumn::make('title')
                    ->label('Property / Building')
                    ->sortable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('localityRef.name')
                    ->label('Locality')
                    ->placeholder('N/A')
                    ->searchable(),

                TextColumn::make('onboarding_progress')
                    ->label('Progress')
                    ->state(function (Property $record): string {
                        $validator = app(PropertyOnboardingValidator::class);
                        $data = $validator->validate($record);
                        return $data['progress'] . '%';
                    })
                    ->badge()
                    ->color(fn (string $state): string => (int)$state === 100 ? 'success' : 'warning'),

                TextColumn::make('onboardingProject.status')
                    ->label('Stage')
                    ->placeholder('Draft')
                    ->badge(),

                TextColumn::make('owner.display_name')
                    ->label('Owner')
                    ->placeholder('N/A')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label('Last Updated')
                    ->since()
                    ->sortable(),
            ])
            ->actions([
                Action::make('open_onboarding')
                    ->label('Checklist')
                    ->icon('heroicon-m-clipboard-document-check')
                    ->color('primary')
                    ->url(fn (Property $record): string => PropertyResource::getUrl('onboarding', ['record' => $record])),
            ]);
    }

    public function getTabs(): array
    {
        return [
            'all' => \Filament\Resources\Components\Tab::make('All Onboarding'),
            'draft' => \Filament\Resources\Components\Tab::make('Draft')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereHas('onboardingProject', fn ($q) => $q->where('status', 'Draft'))),
            'in_progress' => \Filament\Resources\Components\Tab::make('In Progress')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereHas('onboardingProject', fn ($q) => $q->where('status', 'In Progress'))),
            'audit_pending' => \Filament\Resources\Components\Tab::make('Audit Pending')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereHas('onboardingProject', fn ($q) => $q->where('status', 'Audit Pending'))),
        ];
    }
}
