<?php

namespace App\Filament\Pages\Properties;

use App\Domain\Property\Enums\OnboardingStatus;
use App\Domain\Property\Models\Property;
use App\Domain\Property\Services\PropertyOnboardingValidator;
use App\Filament\Clusters\PropertiesCluster;
use App\Filament\Resources\Properties\PropertyResource;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Resources\Components\Tab;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;

class OnboardingQueue extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.pages.properties.onboarding-queue';

    protected static ?string $cluster = PropertiesCluster::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $navigationLabel = 'Onboarding Queue';

    protected static ?int $navigationSort = 2;

    public function getTitle(): string|Htmlable
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
                    ->with(['localityRef.city', 'onboardingProject', 'owner'])
                    ->latest('updated_at')
            )
            ->columns([
                TextColumn::make('code')
                    ->label('Code')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->copyable()
                    ->copyMessage('Property code copied')
                    ->tooltip('Click to copy Property Code')
                    ->placeholder('Unassigned'),

                TextColumn::make('building_name')
                    ->label('Property / Building')
                    ->searchable()
                    ->sortable()
                    ->weight('semibold')
                    ->description(function (Property $record): ?string {
                        $locality = $record->localityRef?->name;
                        $city = $record->localityRef?->city?->name ?? $record->city;
                        if ($locality && $city) {
                            return "📍 {$locality}, {$city}";
                        }
                        if ($locality) {
                            return "📍 {$locality}";
                        }

                        return $record->address_line_1 ? "📍 {$record->address_line_1}" : null;
                    }),

                TextColumn::make('onboarding_progress')
                    ->label('Progress')
                    ->state(function (Property $record): string {
                        $validator = app(PropertyOnboardingValidator::class);
                        $data = $validator->validate($record);

                        return $data['progress'].'%';
                    })
                    ->badge()
                    ->color(fn (string $state): string => (int) $state === 100 ? 'success' : ((int) $state >= 50 ? 'warning' : 'danger')),

                TextColumn::make('onboardingProject.status')
                    ->label('Stage')
                    ->placeholder('Draft')
                    ->badge()
                    ->formatStateUsing(function ($state) {
                        $enum = OnboardingStatus::fromValue((string) $state);

                        return $enum?->getLabel() ?? ucfirst((string) $state);
                    })
                    ->color(function ($state) {
                        $enum = OnboardingStatus::fromValue((string) $state);

                        return $enum?->getColor() ?? match ($state) {
                            'Pending Review' => 'warning',
                            'Changes Requested' => 'danger',
                            'Activated' => 'success',
                            default => 'gray',
                        };
                    })
                    ->icon(function ($state) {
                        $enum = OnboardingStatus::fromValue((string) $state);

                        return $enum?->getIcon();
                    }),

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
                Action::make('review_and_activate')
                    ->label('Review & Activate')
                    ->icon('heroicon-m-shield-check')
                    ->color('warning')
                    ->visible(function (Property $record): bool {
                        if ($record->onboardingProject?->status !== 'Pending Review') {
                            return false;
                        }

                        $user = auth()->user();
                        if (! $user) {
                            return false;
                        }

                        return $user->can('review', $record)
                            || $user->hasAnyRole(['Business Owner', 'City Manager', 'Operations Manager', 'Admin', 'Super Admin']);
                    })
                    ->url(fn (Property $record): string => PropertyResource::getUrl('onboarding', ['record' => $record])),

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
            'all' => Tab::make('All Onboarding'),
            'pending_review' => Tab::make('Pending Review')
                ->badge(fn () => Property::whereHas('onboardingProject', fn ($q) => $q->where('status', 'Pending Review'))->count())
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereHas('onboardingProject', fn ($q) => $q->where('status', 'Pending Review'))),
            'changes_requested' => Tab::make('Changes Requested')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereHas('onboardingProject', fn ($q) => $q->where('status', 'Changes Requested'))),
            'in_progress' => Tab::make('In Progress')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereHas('onboardingProject', fn ($q) => $q->where('status', 'In Progress'))),
            'draft' => Tab::make('Draft')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereHas('onboardingProject', fn ($q) => $q->where('status', 'Draft'))),
            'audit_pending' => Tab::make('Audit Pending')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereHas('onboardingProject', fn ($q) => $q->where('status', 'Audit Pending'))),
        ];
    }
}
