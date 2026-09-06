<?php

namespace App\Filament\Resources\Properties\RelationManagers;

use App\Domain\Agreement\Models\TenancyAgreement;
use App\Domain\Audit\Models\Audit;
use App\Domain\Audit\Models\AuditItem;
use App\Domain\Maintenance\Models\MaintenanceRequest;
use App\Domain\Mou\Models\Mou;
use App\Domain\Property\Models\AmenityType;
use App\Domain\Property\Models\Establishment;
use App\Domain\Property\Models\InventoryType;
use App\Domain\Property\Models\OnboardingProject;
use App\Domain\Property\Models\PropertyAmenity;
use App\Domain\Property\Models\PropertyDocument;
use App\Domain\Property\Models\PropertyEstablishment;
use App\Domain\Property\Models\PropertyFinancialTerm;
use App\Domain\Property\Models\PropertyInventory;
use App\Domain\Property\Models\PropertyPhoto;
use App\Domain\Property\Models\PropertyRoom;
use App\Domain\Property\Models\PropertyUtility;
use App\Domain\Property\Models\RoomDefinition;
use App\Filament\Resources\Properties\RelationManagers\Traits\LocksDuringPropertyOnboarding;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Activitylog\Models\Activity;

class ActivitiesRelationManager extends RelationManager
{
    use LocksDuringPropertyOnboarding;

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
        if (! $property) {
            return parent::getTableQuery();
        }

        $roomIds = $property->rooms()->pluck('id')->toArray();
        $inventoryIds = $property->inventories()->pluck('id')->toArray();
        $utilityIds = $property->utilities()->pluck('id')->toArray();
        $amenityIds = $property->amenities()->pluck('id')->toArray();
        $establishmentIds = $property->establishments()->pluck('id')->toArray();
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

        return Activity::query()
            ->where(function ($query) use ($property, $onboardingProjectId, $roomIds, $inventoryIds, $utilityIds, $amenityIds, $establishmentIds, $documentIds, $photoIds, $financialTermIds, $agreementIds, $auditIds, $auditItemIds, $maintenanceIds, $mouIds) {
                $query->where(fn ($q) => $q->where('subject_type', get_class($property))->where('subject_id', $property->id));

                if ($onboardingProjectId) {
                    $query->orWhere(fn ($q) => $q->where('subject_type', OnboardingProject::class)->where('subject_id', $onboardingProjectId));
                }
                if (! empty($roomIds)) {
                    $query->orWhere(fn ($q) => $q->where('subject_type', PropertyRoom::class)->whereIn('subject_id', $roomIds));
                }
                if (! empty($inventoryIds)) {
                    $query->orWhere(fn ($q) => $q->where('subject_type', PropertyInventory::class)->whereIn('subject_id', $inventoryIds));
                }
                if (! empty($utilityIds)) {
                    $query->orWhere(fn ($q) => $q->where('subject_type', PropertyUtility::class)->whereIn('subject_id', $utilityIds));
                }
                if (! empty($amenityIds)) {
                    $query->orWhere(fn ($q) => $q->where('subject_type', PropertyAmenity::class)->whereIn('subject_id', $amenityIds));
                }
                if (! empty($establishmentIds)) {
                    $query->orWhere(fn ($q) => $q->where('subject_type', PropertyEstablishment::class)->whereIn('subject_id', $establishmentIds));
                }
                if (! empty($documentIds)) {
                    $query->orWhere(fn ($q) => $q->where('subject_type', PropertyDocument::class)->whereIn('subject_id', $documentIds));
                }
                if (! empty($photoIds)) {
                    $query->orWhere(fn ($q) => $q->where('subject_type', PropertyPhoto::class)->whereIn('subject_id', $photoIds));
                }
                if (! empty($financialTermIds)) {
                    $query->orWhere(fn ($q) => $q->where('subject_type', PropertyFinancialTerm::class)->whereIn('subject_id', $financialTermIds));
                }
                if (! empty($agreementIds)) {
                    $query->orWhere(fn ($q) => $q->where('subject_type', TenancyAgreement::class)->whereIn('subject_id', $agreementIds));
                }
                if (! empty($auditIds)) {
                    $query->orWhere(fn ($q) => $q->where('subject_type', Audit::class)->whereIn('subject_id', $auditIds));
                }
                if (! empty($auditItemIds)) {
                    $query->orWhere(fn ($q) => $q->where('subject_type', AuditItem::class)->whereIn('subject_id', $auditItemIds));
                }
                if (! empty($maintenanceIds)) {
                    $query->orWhere(fn ($q) => $q->where('subject_type', MaintenanceRequest::class)->whereIn('subject_id', $maintenanceIds));
                }
                if (! empty($mouIds)) {
                    $query->orWhere(fn ($q) => $q->where('subject_type', Mou::class)->whereIn('subject_id', $mouIds));
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
                            'PropertyAmenity' => 'Amenity',
                            'PropertyEstablishment' => 'Establishment',
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
                            'PropertyAmenity' => 'emerald',
                            'PropertyEstablishment' => 'purple',
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
                    ->description(fn ($record) => $record->created_at?->diffForHumans())
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
                        'PropertyAmenity' => 'Amenities',
                        'PropertyEstablishment' => 'Establishments & Nearby',
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
                            return $query->whereIn('subject_type', [Audit::class, AuditItem::class]);
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
            'PropertyAmenity' => 'Amenity',
            'PropertyEstablishment' => 'Establishment',
            'PropertyDocument' => 'Document',
            'PropertyPhoto' => 'Photo',
            'PropertyFinancialTerm' => 'Financial Term',
            'Mou' => 'MOU',
            default => $subjectType ?: 'Record',
        };

        if ($desc && ! in_array(strtolower(trim($desc)), ['created', 'updated', 'deleted'])) {
            return $desc;
        }

        $properties = $record->properties ?? [];
        $attributes = $properties['attributes'] ?? [];
        $old = $properties['old'] ?? [];

        // Determine item name if applicable
        $itemName = $properties['item_name'] ?? null;

        if (! $itemName && $record->subject) {
            $subject = $record->subject;
            if ($subject instanceof PropertyRoom) {
                $itemName = $subject->custom_name ?: ($subject->roomDefinition?->name ?? null);
            } elseif ($subject instanceof PropertyInventory) {
                $itemName = $subject->inventoryType?->name;
            } elseif ($subject instanceof PropertyAmenity) {
                $itemName = $subject->amenityType?->name;
            } elseif ($subject instanceof PropertyEstablishment) {
                $itemName = $subject->establishment?->name;
            }
        }

        if (! $itemName) {
            $attrSource = ! empty($attributes) ? $attributes : $old;
            if (! empty($attrSource['establishment_id'])) {
                $itemName = Establishment::find($attrSource['establishment_id'])?->name;
            } elseif (! empty($attrSource['amenity_type_id'])) {
                $itemName = AmenityType::find($attrSource['amenity_type_id'])?->name;
            } elseif (! empty($attrSource['inventory_type_id'])) {
                $itemName = InventoryType::find($attrSource['inventory_type_id'])?->name;
            } elseif (! empty($attrSource['custom_name'])) {
                $itemName = $attrSource['custom_name'];
            } elseif (! empty($attrSource['room_definition_id'])) {
                $itemName = RoomDefinition::find($attrSource['room_definition_id'])?->name;
            }
        }

        $namePrefix = $itemName ? " \"{$itemName}\"" : '';

        if ($event === 'created' || strtolower(trim($desc)) === 'created') {
            return "{$subjectLabel}{$namePrefix} Created";
        }

        if ($event === 'deleted' || strtolower(trim($desc)) === 'deleted') {
            return "{$subjectLabel}{$namePrefix} Deleted";
        }

        if (! empty($attributes)) {
            $changes = [];
            foreach ($attributes as $key => $newValue) {
                if (in_array($key, ['updated_at', 'created_at', 'deleted_at', 'remember_token'])) {
                    continue;
                }

                $oldValue = $old[$key] ?? null;

                if (is_bool($newValue)) {
                    $newValue = $newValue ? 'Yes' : 'No';
                }
                if (is_bool($oldValue)) {
                    $oldValue = $oldValue ? 'Yes' : 'No';
                }

                if (is_array($newValue)) {
                    $newValue = json_encode($newValue);
                }
                if (is_array($oldValue)) {
                    $oldValue = json_encode($oldValue);
                }

                $keyName = ucwords(str_replace('_', ' ', $key));

                if (array_key_exists($key, $old) && $oldValue !== null) {
                    $changes[] = "{$keyName}: '{$oldValue}' → '{$newValue}'";
                } else {
                    $changes[] = "{$keyName}: '{$newValue}'";
                }
            }

            if (! empty($changes)) {
                return "{$subjectLabel}{$namePrefix} Updated (".implode(', ', array_slice($changes, 0, 3)).(count($changes) > 3 ? '...' : '').')';
            }
        }

        return "{$subjectLabel}{$namePrefix} Updated";
    }
}
