<?php

namespace App\Domain\Audit\Services;

use App\Domain\Audit\Models\Audit;
use App\Domain\Audit\Models\AuditCategory;
use App\Domain\Audit\Models\AuditItem;

class AuditSnapshotService
{
    /**
     * Generate the runtime snapshot for a newly created audit.
     * Optionally accepts a scope filter (e.g. ['Rooms', 'Inventory']) for future Phase 3 partial audits.
     */
    public function generateSnapshot(Audit $audit, array $scopes = []): void
    {
        $property = $audit->property;
        if (!$property) {
            return;
        }

        // 1. Rooms
        if (empty($scopes) || in_array('Rooms', $scopes)) {
            $this->snapshotRooms($audit, $property);
        }

        // 2. Inventory
        if (empty($scopes) || in_array('Inventory', $scopes)) {
            $this->snapshotInventory($audit, $property);
        }

        // 3. Utilities
        if (empty($scopes) || in_array('Utilities', $scopes)) {
            $this->snapshotUtilities($audit, $property);
        }

        // We can add Amenities, Keys, Documents here in the future
    }

    protected function snapshotRooms(Audit $audit, $property): void
    {
        $rooms = $property->rooms()->with('roomDefinition')->get();
        if ($rooms->isEmpty()) {
            return;
        }

        $category = AuditCategory::create([
            'audit_id' => $audit->id,
            'name' => 'Rooms',
            'sort_order' => 10,
        ]);

        foreach ($rooms as $index => $room) {
            $name = $room->custom_name ?: ($room->roomDefinition->name ?? 'Room');
            
            AuditItem::create([
                'audit_category_id' => $category->id,
                'name' => $name,
                'source_type' => get_class($room),
                'source_id' => $room->id,
                'snapshot_data' => [
                    'floor' => $room->floor,
                    'area' => $room->area,
                    'description' => $room->description,
                    'room_definition' => $room->roomDefinition->name ?? null,
                ],
                'sort_order' => $index,
            ]);
        }
    }

    protected function snapshotInventory(Audit $audit, $property): void
    {
        $inventories = $property->inventories()->with(['inventoryType', 'room'])->get();
        if ($inventories->isEmpty()) {
            return;
        }

        $category = AuditCategory::create([
            'audit_id' => $audit->id,
            'name' => 'Inventory',
            'sort_order' => 20,
        ]);

        foreach ($inventories as $index => $inventory) {
            $name = $inventory->inventoryType->name ?? 'Item';
            if ($inventory->room) {
                $name .= ' (' . ($inventory->room->custom_name ?: 'Room') . ')';
            }

            AuditItem::create([
                'audit_category_id' => $category->id,
                'name' => $name,
                'source_type' => get_class($inventory),
                'source_id' => $inventory->id,
                'snapshot_data' => [
                    'inventory_type' => $inventory->inventoryType->name ?? null,
                    'count' => $inventory->count,
                    'room_id' => $inventory->property_room_id,
                ],
                'sort_order' => $index,
            ]);
        }
    }

    protected function snapshotUtilities(Audit $audit, $property): void
    {
        $utilities = $property->utilities()->with('utilityType')->get();
        if ($utilities->isEmpty()) {
            return;
        }

        $category = AuditCategory::create([
            'audit_id' => $audit->id,
            'name' => 'Utilities',
            'sort_order' => 30,
        ]);

        foreach ($utilities as $index => $utility) {
            $name = $utility->utilityType->name ?? 'Utility';
            
            AuditItem::create([
                'audit_category_id' => $category->id,
                'name' => $name,
                'source_type' => get_class($utility),
                'source_id' => $utility->id,
                'snapshot_data' => [
                    'utility_type' => $utility->utilityType->name ?? null,
                    'paid_by' => $utility->paid_by,
                    'details' => $utility->details,
                ],
                'sort_order' => $index,
            ]);
        }
    }
}
