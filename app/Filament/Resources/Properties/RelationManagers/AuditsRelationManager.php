<?php

namespace App\Filament\Resources\Properties\RelationManagers;

use App\Domain\Audit\Enums\AuditStatus;
use App\Domain\Audit\Enums\AuditType;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class AuditsRelationManager extends RelationManager
{
    protected static string $relationship = 'audits';

    protected static ?string $title = 'Audits';

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
                        return \App\Domain\Audit\Models\Audit::where('property_id', $propertyId)
                            ->whereIn('status', [AuditStatus::COMPLETED, AuditStatus::APPROVED])
                            ->get()
                            ->mapWithKeys(fn ($a) => [$a->id => $a->audit_number . ' (' . $a->audit_type->getLabel() . ')']);
                    })
                    ->searchable()
                    ->preload(),
                Forms\Components\Select::make('inspector_id')
                    ->relationship('inspector', 'name')
                    ->searchable()
                    ->preload()
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
                \Filament\Actions\CreateAction::make()
                    ->disabled(function (\Filament\Resources\RelationManagers\RelationManager $livewire) {
                        $record = $livewire->getOwnerRecord();
                        if ($record instanceof \App\Domain\Property\Models\Property) {
                            return empty($record->code) || $record->onboardingProject?->status !== 'Activated';
                        }
                        return false;
                    })
                    ->tooltip(function (\Filament\Resources\RelationManagers\RelationManager $livewire) {
                        $record = $livewire->getOwnerRecord();
                        if ($record instanceof \App\Domain\Property\Models\Property) {
                            $isDisabled = empty($record->code) || $record->onboardingProject?->status !== 'Activated';
                            return $isDisabled ? 'Complete onboarding and generate property code first.' : null;
                        }
                        return null;
                    })
                    ->mutateFormDataUsing(function (array $data): array {
                        if (!isset($data['reference_audit_id'])) {
                            $latestAudit = \App\Domain\Audit\Models\Audit::where('property_id', $this->getOwnerRecord()->id)
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
                \Filament\Actions\Action::make('openAudit')
                    ->label('Manage')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (\App\Domain\Audit\Models\Audit $record): string => \App\Filament\Resources\Operations\AuditResource::getUrl('edit', ['record' => $record])),
            ])
            ->bulkActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
