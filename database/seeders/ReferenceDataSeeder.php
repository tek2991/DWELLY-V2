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
        $this->insertReferenceData('room_types', [
            'Bedroom', 'Living Room', 'Kitchen', 'Bathroom', 'Balcony', 'Pooja Room', 'Store Room'
        ]);
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
            'Inverter', 'Study Table', 'Chair'
        ]);
    }

    private function seedEstablishmentTypes()
    {
        $this->insertReferenceData('establishment_types', [
            'Hospital', 'School', 'IT Park', 'Metro Station', 'Shopping Mall', 'Airport', 'Railway Station', 'Park'
        ]);
    }
}
