<?php

namespace Tests\Unit;

use App\Filament\Resources\Properties\RelationManagers\ActivitiesRelationManager;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class PropertyActivityLogItemNameTest extends TestCase
{
    public function test_format_activity_description_includes_item_name()
    {
        $roomActivity = new Activity([
            'subject_type' => 'App\Domain\Property\Models\PropertyRoom',
            'event' => 'created',
            'description' => 'created',
            'properties' => [
                'item_name' => 'Master Bedroom',
            ],
        ]);

        $this->assertEquals('Room "Master Bedroom" Created', ActivitiesRelationManager::formatActivityDescription($roomActivity));

        $inventoryActivity = new Activity([
            'subject_type' => 'App\Domain\Property\Models\PropertyInventory',
            'event' => 'updated',
            'description' => 'updated',
            'properties' => [
                'item_name' => 'Air Conditioner',
                'attributes' => ['count' => 3],
                'old' => ['count' => 2],
            ],
        ]);

        $this->assertEquals('Inventory Item "Air Conditioner" Updated (Count: \'2\' → \'3\')', ActivitiesRelationManager::formatActivityDescription($inventoryActivity));

        $amenityActivity = new Activity([
            'subject_type' => 'App\Domain\Property\Models\PropertyAmenity',
            'event' => 'created',
            'description' => 'created',
            'properties' => [
                'item_name' => 'Swimming Pool',
            ],
        ]);

        $this->assertEquals('Amenity "Swimming Pool" Created', ActivitiesRelationManager::formatActivityDescription($amenityActivity));

        $establishmentActivity = new Activity([
            'subject_type' => 'App\Domain\Property\Models\PropertyEstablishment',
            'event' => 'deleted',
            'description' => 'deleted',
            'properties' => [
                'item_name' => 'Apollo Hospital',
            ],
        ]);

        $this->assertEquals('Establishment "Apollo Hospital" Deleted', ActivitiesRelationManager::formatActivityDescription($establishmentActivity));
    }
}
