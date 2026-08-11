<?php

namespace App\Filament\Resources\Properties\RelationManagers;

use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ActivitiesRelationManager extends RelationManager
{
    use \App\Filament\Resources\Properties\RelationManagers\Traits\LocksDuringPropertyOnboarding;

    protected static string $relationship = 'activities';

    protected static ?string $recordTitleAttribute = 'description';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\TextInput::make('description')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    protected function getTableQuery(): Builder
    {
        $property = $this->getOwnerRecord();
        if (!$property) {
            return parent::getTableQuery();
        }

        $roomIds = $property->rooms()->pluck('id')->toArray();
        $inventoryIds = $property->inventories()->pluck('id')->toArray();
        $utilityIds = $property->utilities()->pluck('id')->toArray();
        $agreementIds = $property->agreements()->pluck('id')->toArray();
        $audits = $property->audits()->with('items')->get();
        $auditIds = $audits->pluck('id')->toArray();
        $auditItemIds = $audits->flatMap->items->pluck('id')->toArray();
        $maintenanceIds = $property->maintenanceRequests()->pluck('id')->toArray();
        $mouIds = $property->mous()->pluck('id')->toArray();

        $onboardingProjectId = $property->onboardingProject?->id;

        $documentIds = $property->documents()->pluck('id')->toArray();
        $photoIds = $property->photos()->pluck('id')->toArray();
        $financialTermIds = $property->financialTerms()->pluck('id')->toArray();

        return \Spatie\Activitylog\Models\Activity::query()
            ->where(function ($query) use ($property, $onboardingProjectId, $roomIds, $inventoryIds, $utilityIds, $documentIds, $photoIds, $financialTermIds, $agreementIds, $auditIds, $auditItemIds, $maintenanceIds, $mouIds) {
                $query->where(fn($q) => $q->where('subject_type', get_class($property))->where('subject_id', $property->id));

                if ($onboardingProjectId) {
                    $query->orWhere(fn($q) => $q->where('subject_type', \App\Domain\Property\Models\OnboardingProject::class)->where('subject_id', $onboardingProjectId));
                }
                if (!empty($roomIds)) {
                    $query->orWhere(fn($q) => $q->where('subject_type', \App\Domain\Property\Models\PropertyRoom::class)->whereIn('subject_id', $roomIds));
                }
                if (!empty($inventoryIds)) {
                    $query->orWhere(fn($q) => $q->where('subject_type', \App\Domain\Property\Models\PropertyInventory::class)->whereIn('subject_id', $inventoryIds));
                }
                if (!empty($utilityIds)) {
                    $query->orWhere(fn($q) => $q->where('subject_type', \App\Domain\Property\Models\PropertyUtility::class)->whereIn('subject_id', $utilityIds));
                }
                if (!empty($documentIds)) {
                    $query->orWhere(fn($q) => $q->where('subject_type', \App\Domain\Property\Models\PropertyDocument::class)->whereIn('subject_id', $documentIds));
                }
                if (!empty($photoIds)) {
                    $query->orWhere(fn($q) => $q->where('subject_type', \App\Domain\Property\Models\PropertyPhoto::class)->whereIn('subject_id', $photoIds));
                }
                if (!empty($financialTermIds)) {
                    $query->orWhere(fn($q) => $q->where('subject_type', \App\Domain\Property\Models\PropertyFinancialTerm::class)->whereIn('subject_id', $financialTermIds));
                }
                if (!empty($agreementIds)) {
                    $query->orWhere(fn($q) => $q->where('subject_type', \App\Domain\Agreement\Models\TenancyAgreement::class)->whereIn('subject_id', $agreementIds));
                }
                if (!empty($auditIds)) {
                    $query->orWhere(fn($q) => $q->where('subject_type', \App\Domain\Audit\Models\Audit::class)->whereIn('subject_id', $auditIds));
                }
                if (!empty($auditItemIds)) {
                    $query->orWhere(fn($q) => $q->where('subject_type', \App\Domain\Audit\Models\AuditItem::class)->whereIn('subject_id', $auditItemIds));
                }
                if (!empty($maintenanceIds)) {
                    $query->orWhere(fn($q) => $q->where('subject_type', \App\Domain\Maintenance\Models\MaintenanceRequest::class)->whereIn('subject_id', $maintenanceIds));
                }
                if (!empty($mouIds)) {
                    $query->orWhere(fn($q) => $q->where('subject_type', \App\Domain\Mou\Models\Mou::class)->whereIn('subject_id', $mouIds));
                }
            });
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('description')
            ->columns([
                Tables\Columns\TextColumn::make('subject_type')
                    ->label('Module')
                    ->formatStateUsing(function ($state) {
                        $basename = class_basename($state);
                        return match ($basename) {
                            'Property' => 'Property',
                            'OnboardingProject' => 'Onboarding',
                            'TenancyAgreement' => 'Tenancy',
                            'Audit' => 'Audit',
                            'AuditItem' => 'Audit Item',
                            'MaintenanceRequest' => 'Maintenance',
                            'PropertyRoom' => 'Room',
                            'PropertyInventory' => 'Inventory',
                            'PropertyUtility' => 'Utility',
                            'PropertyDocument' => 'Document',
                            'PropertyPhoto' => 'Photo',
                            'PropertyFinancialTerm' => 'Financial Terms',
                            'Mou' => 'MOU',
                            default => $basename ?: 'General',
                        };
                    })
                    ->badge()
                    ->color(function ($state) {
                        $basename = class_basename($state);
                        return match ($basename) {
                            'Property' => 'primary',
                            'OnboardingProject' => 'info',
                            'TenancyAgreement' => 'success',
                            'Audit', 'AuditItem' => 'warning',
                            'MaintenanceRequest' => 'danger',
                            'PropertyRoom', 'PropertyInventory', 'PropertyUtility' => 'cyan',
                            'PropertyDocument', 'PropertyPhoto' => 'gray',
                            'PropertyFinancialTerm' => 'emerald',
                            'Mou' => 'purple',
                            default => 'gray',
                        };
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('description')
                    ->label('Activity & Change Details')
                    ->formatStateUsing(function ($record) {
                        return static::formatActivityDescription($record);
                    })
                    ->wrap()
                    ->searchable(),

                Tables\Columns\TextColumn::make('causer.name')
                    ->label('User / Trigger')
                    ->default('System')
                    ->searchable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Date & Time')
                    ->dateTime('M j, Y g:i A')
                    ->description(fn($record) => $record->created_at?->diffForHumans())
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('entity')
                    ->label('Filter by Module')
                    ->options([
                        'Property' => 'Property Base Details',
                        'OnboardingProject' => 'Onboarding Progress',
                        'PropertyRoom' => 'Rooms & Facilities',
                        'PropertyInventory' => 'Inventory Items',
                        'PropertyUtility' => 'Utilities & Bills',
                        'PropertyDocument' => 'Documents',
                        'PropertyPhoto' => 'Photos & Media',
                        'PropertyFinancialTerm' => 'Financial Terms',
                        'TenancyAgreement' => 'Tenancy Agreements',
                        'Audit' => 'Audits & Inspections',
                        'MaintenanceRequest' => 'Maintenance Requests',
                        'Mou' => 'MOUs & Agreements',
                    ])
                    ->query(function ($query, array $data) {
                        if (empty($data['value'])) {
                            return $query;
                        }
                        $selected = $data['value'];
                        if ($selected === 'Audit') {
                            return $query->whereIn('subject_type', [\App\Domain\Audit\Models\Audit::class, \App\Domain\Audit\Models\AuditItem::class]);
                        }
                        return $query->where('subject_type', 'like', "%{$selected}%");
                    }),
            ])
            ->headerActions([])
            ->actions([])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc');
    }

    public static function formatActivityDescription($record): string
    {
        $desc = $record->description;
        $event = $record->event;
        $subjectType = class_basename($record->subject_type ?? '');

        $subjectLabel = match ($subjectType) {
            'Property' => 'Property',
            'OnboardingProject' => 'Onboarding Progress',
            'TenancyAgreement' => 'Tenancy Agreement',
            'Audit' => 'Audit',
            'AuditItem' => 'Audit Item',
            'MaintenanceRequest' => 'Maintenance Request',
            'PropertyRoom' => 'Room',
            'PropertyInventory' => 'Inventory Item',
            'PropertyUtility' => 'Utility',
            'PropertyDocument' => 'Document',
            'PropertyPhoto' => 'Photo',
            'PropertyFinancialTerm' => 'Financial Term',
            'Mou' => 'MOU',
            default => $subjectType ?: 'Record',
        };

        if ($desc && !in_array(strtolower(trim($desc)), ['created', 'updated', 'deleted'])) {
            return $desc;
        }

        $properties = $record->properties ?? [];
        $attributes = $properties['attributes'] ?? [];
        $old = $properties['old'] ?? [];

        if ($event === 'created' || strtolower(trim($desc)) === 'created') {
            return "{$subjectLabel} Created";
        }

        if ($event === 'deleted' || strtolower(trim($desc)) === 'deleted') {
            return "{$subjectLabel} Deleted";
        }

        if (!empty($attributes)) {
            $changes = [];
            foreach ($attributes as $key => $newValue) {
                if (in_array($key, ['updated_at', 'created_at', 'deleted_at', 'remember_token'])) {
                    continue;
                }

                $oldValue = $old[$key] ?? null;

                if (is_bool($newValue)) $newValue = $newValue ? 'Yes' : 'No';
                if (is_bool($oldValue)) $oldValue = $oldValue ? 'Yes' : 'No';

                if (is_array($newValue)) $newValue = json_encode($newValue);
                if (is_array($oldValue)) $oldValue = json_encode($oldValue);

                $keyName = ucwords(str_replace('_', ' ', $key));

                if (array_key_exists($key, $old) && $oldValue !== null) {
                    $changes[] = "{$keyName}: '{$oldValue}' → '{$newValue}'";
                } else {
                    $changes[] = "{$keyName}: '{$newValue}'";
                }
            }

            if (!empty($changes)) {
                return "{$subjectLabel} Updated (" . implode(', ', array_slice($changes, 0, 3)) . (count($changes) > 3 ? '...' : '') . ")";
            }
        }

        return "{$subjectLabel} Updated";
    }
}
