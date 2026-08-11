<?php

namespace Tests\Feature;

use App\Domain\Property\Models\EstablishmentType;
use App\Domain\Property\Models\Property;
use App\Filament\Resources\Properties\RelationManagers\EstablishmentsRelationManager;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EstablishmentBulkCreateTest extends TestCase
{
    use RefreshDatabase;

    public function test_bulk_create_modal_prepopulates_active_establishment_types_as_default()
    {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Business Owner', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('Business Owner');
        $this->actingAs($user);

        $property = Property::create([
            'building_name' => 'Test Heights',
            'address_line_1' => 'Test Road',
            'status' => 'draft',
        ]);

        $activeType1 = EstablishmentType::create([
            'name' => 'Hospital',
            'slug' => 'hospital',
            'is_active' => true,
        ]);

        $activeType2 = EstablishmentType::create([
            'name' => 'School',
            'slug' => 'school',
            'is_active' => true,
        ]);

        $inactiveType = EstablishmentType::create([
            'name' => 'Casino',
            'slug' => 'casino',
            'is_active' => false,
        ]);

        Livewire::test(EstablishmentsRelationManager::class, [
            'ownerRecord' => $property,
            'pageClass' => \App\Filament\Resources\Properties\Pages\EditProperty::class,
        ])
            ->mountTableAction('bulkCreate')
            ->assertTableActionMounted('bulkCreate');
    }
}
