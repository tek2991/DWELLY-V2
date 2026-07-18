<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReferenceDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedPropertyTypes();
        $this->seedBhkTypes();
        $this->seedFurnishingTypes();
        $this->seedFlooringTypes();
        $this->seedRoomTypes();
        $this->seedAmenityTypes();
        $this->seedInventoryTypes();
        $this->seedEstablishmentTypes();
        $this->seedUtilityTypes();
    }

    private function insertReferenceData(string $table, array $items)
    {
        $now = now();
        $data = array_map(function ($item) use ($now) {
            return [
                'id' => (string) Str::ulid(),
                'name' => $item,
                'slug' => Str::slug($item),
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }, $items);

        DB::table($table)->upsert($data, ['slug'], ['name', 'updated_at']);
    }

    private function seedPropertyTypes()
    {
        $this->insertReferenceData('property_types', [
            'Apartment', 'Independent House', 'Villa', 'Builder Floor', 'Studio'
        ]);
    }

    private function seedBhkTypes()
    {
        $this->insertReferenceData('bhk_types', [
            '1 BHK', '2 BHK', '3 BHK', '4 BHK', '5+ BHK', '1 RK'
        ]);
    }

    private function seedFurnishingTypes()
    {
        $this->insertReferenceData('furnishing_types', [
            'Unfurnished', 'Semi-Furnished', 'Fully Furnished'
        ]);
    }

    private function seedFlooringTypes()
    {
        $this->insertReferenceData('flooring_types', [
            'Vitrified', 'Marble', 'Granite', 'Wooden', 'Ceramic'
        ]);
    }

    private function seedRoomTypes()
    {
        // 1. Seed the broad categories as RoomTypes
        $roomTypes = [
            'Bedroom',
            'Bathroom',
            'Kitchen & Utility',
            'Living Space',
            'Balcony & Outdoors',
            'Additional Rooms',
        ];
        $this->insertReferenceData('room_types', $roomTypes);

        // 2. Fetch the created RoomTypes to get their IDs
        $typesMap = DB::table('room_types')->whereIn('name', $roomTypes)->pluck('id', 'name');

        // 3. Define the definitions mapping
        $definitionsData = [
            'Bedroom' => ['Master Bedroom', 'Second Bedroom', 'Third Bedroom', 'Guest Bedroom', 'Kids Bedroom'],
            'Bathroom' => ['Attached Bathroom', 'Common Bathroom', 'Master Bathroom', 'Powder Room', 'Guest Bathroom'],
            'Kitchen & Utility' => ['Kitchen', 'Modular Kitchen', 'Open Kitchen', 'Closed Kitchen', 'Pantry'],
            'Living Space' => ['Living Room', 'Family Lounge', 'Drawing Room', 'Dining Room', 'Home Theater'],
            'Balcony & Outdoors' => ['Front Balcony', 'Rear Balcony', 'Utility Balcony', 'Master Balcony', 'Terrace', 'Private Garden'],
            'Additional Rooms' => ['Office Room', 'Pooja Room', 'Store Room', 'Servant Room', 'Foyer', 'Gym Room', 'Garage'],
        ];

        // 4. Prepare and insert RoomDefinitions
        $now = now();
        $definitionsToInsert = [];
        foreach ($definitionsData as $typeName => $definitions) {
            $typeId = $typesMap[$typeName] ?? null;
            if (!$typeId) continue;
            
            foreach ($definitions as $index => $defName) {
                $definitionsToInsert[] = [
                    'id' => (string) Str::ulid(),
                    'room_type_id' => $typeId,
                    'name' => $defName,
                    'slug' => Str::slug($defName),
                    'display_order' => $index,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        DB::table('room_definitions')->upsert($definitionsToInsert, ['slug'], ['name', 'display_order', 'updated_at']);
    }

    private function seedAmenityTypes()
    {
        $this->insertReferenceData('amenity_types', [
            'Lift', 'Power Backup', 'Security', 'Parking', 'Gym', 'Swimming Pool', 'Club House'
        ]);
    }

    private function seedInventoryTypes()
    {
        $this->insertReferenceData('inventory_types', [
            'Fan', 'Light', 'Kitchen Cabinet', 'Wardrobe', 'Sofa', 'Dining Set',
            'Bed', 'Gas Stove', 'Induction Stove', 'Microwave', 'Gas Cylinder',
            'Exhaust Fan', 'Kitchen Chimney', 'Water Purifier', 'Geyser',
            'Air Conditioner', 'Television', 'Fridge', 'Washing Machine',
            'Inverter', 'Study Table', 'Chair', 'Keys'
        ]);
    }

    private function seedEstablishmentTypes()
    {
        $this->insertReferenceData('establishment_types', [
            'Hospital', 'School', 'IT Park', 'Metro Station', 'Shopping Mall', 'Airport', 'Railway Station', 'Park'
        ]);
    }

    private function seedUtilityTypes()
    {
        $this->insertReferenceData('utility_types', [
            'Electricity', 'Water', 'Gas', 'Internet', 'DTH', 'Maintenance'
        ]);

        $electricityType = DB::table('utility_types')->where('slug', 'electricity')->first();
        if ($electricityType) {
            DB::table('utility_providers')->updateOrInsert(
                ['name' => 'APDCL', 'utility_type_id' => $electricityType->id],
                [
                    'id' => (string) Str::ulid(),
                    'slug' => 'apdcl',
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}
