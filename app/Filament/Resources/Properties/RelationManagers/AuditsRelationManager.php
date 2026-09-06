<?php

namespace App\Filament\Resources\Properties\RelationManagers;

use App\Domain\Audit\Enums\AuditStatus;
use App\Domain\Audit\Enums\AuditType;
use App\Domain\Audit\Models\Audit;
use App\Domain\Property\Models\Property;
use App\Filament\Resources\Operations\AuditResource;
use App\Filament\Resources\Properties\Pages\OnboardingDashboard;
use App\Filament\Resources\Properties\RelationManagers\Traits\LocksDuringPropertyOnboarding;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class AuditsRelationManager extends RelationManager
{
    use LocksDuringPropertyOnboarding;

    protected static string $relationship = 'audits';

    protected static ?string $title = 'Audits';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        if ($pageClass === OnboardingDashboard::class) {
            return false;
        }

        return parent::canViewForRecord($ownerRecord, $pageClass);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\Select::make('audit_type')
                    ->options(AuditType::class)
                    ->required(),
                Forms\Components\Select::make('reference_audit_id')
                    ->label('Reference Audit')
                    ->options(function () {
                        // In RelationManager, ownerRecord is available via $this->getOwnerRecord()
                        // But inside a closure, it's safer to query it directly or let the user choose
                        // Actually, I can't easily access $this in a static context if it was static, but form() is not static.
                        $propertyId = $this->getOwnerRecord()->id;

                        return Audit::where('property_id', $propertyId)
                            ->whereIn('status', [AuditStatus::COMPLETED, AuditStatus::APPROVED])
                            ->get()
                            ->mapWithKeys(fn ($a) => [$a->id => $a->audit_number.' ('.$a->audit_type->getLabel().')']);
                    })
                    ->searchable()
                    ->preload(),
                Forms\Components\Select::make('inspector_id')
                    ->label('Assigned Inspector')
                    ->relationship('inspector', 'name')
                    ->searchable()
                    ->preload()
                    ->default(auth()->id()),
                Forms\Components\Select::make('reviewer_id')
                    ->label('Assigned Reviewer')
                    ->relationship('reviewer', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->default(auth()->id()),
                Forms\Components\DatePicker::make('scheduled_at')
                    ->label('Scheduled Date'),
                Forms\Components\Textarea::make('notes')
                    ->maxLength(65535)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('audit_number')
            ->columns([
                Tables\Columns\TextColumn::make('audit_number')
                    ->searchable(),
                Tables\Columns\TextColumn::make('audit_type')
                    ->badge(),
                Tables\Columns\TextColumn::make('status')
                    ->badge(),
                Tables\Columns\TextColumn::make('inspector.name'),
                Tables\Columns\TextColumn::make('scheduled_at')
                    ->date(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make()
                    ->disabled(function (RelationManager $livewire) {
                        $record = $livewire->getOwnerRecord();
                        if ($record instanceof Property) {
                            return empty($record->code) || $record->onboardingProject?->status !== 'Activated';
                        }

                        return false;
                    })
                    ->tooltip(function (RelationManager $livewire) {
                        $record = $livewire->getOwnerRecord();
                        if ($record instanceof Property) {
                            $isDisabled = empty($record->code) || $record->onboardingProject?->status !== 'Activated';

                            return $isDisabled ? 'Complete onboarding and generate property code first.' : null;
                        }

                        return null;
                    })
                    ->mutateFormDataUsing(function (array $data): array {
                        if (! isset($data['reference_audit_id'])) {
                            $latestAudit = Audit::where('property_id', $this->getOwnerRecord()->id)
                                ->whereIn('status', [AuditStatus::COMPLETED, AuditStatus::APPROVED])
                                ->orderBy('created_at', 'desc')
                                ->first();
                            if ($latestAudit) {
                                $data['reference_audit_id'] = $latestAudit->id;
                            }
                        }

                        return $data;
                    }),
            ])
            ->actions([
                Action::make('openAudit')
                    ->label('Manage')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (Audit $record): string => AuditResource::getUrl('edit', ['record' => $record])),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
