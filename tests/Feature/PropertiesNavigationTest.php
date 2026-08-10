<?php

namespace Tests\Feature;

use App\Domain\Property\Models\OnboardingProject;
use App\Domain\Property\Models\Property;
use App\Filament\Clusters\PropertiesCluster;
use App\Filament\Pages\Properties\OnboardingQueue;
use App\Filament\Resources\Properties\PropertyResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PropertiesNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_properties_cluster_has_correct_navigation_properties(): void
    {
        $this->assertEquals('Properties', PropertiesCluster::getNavigationLabel());
        $this->assertEquals('Portfolio & Operations', PropertiesCluster::getNavigationGroup());
    }

    public function test_property_resource_belongs_to_properties_cluster(): void
    {
        $this->assertEquals(PropertiesCluster::class, PropertyResource::getCluster());
        $this->assertEquals('All Properties', PropertyResource::getNavigationLabel());
    }

    public function test_onboarding_queue_belongs_to_properties_cluster(): void
    {
        $this->assertEquals(PropertiesCluster::class, OnboardingQueue::getCluster());
        $this->assertEquals('Onboarding Queue', OnboardingQueue::getNavigationLabel());
    }

    public function test_authenticated_user_can_access_onboarding_queue_page(): void
    {
        $user = User::factory()->create();

        $property = Property::create([
            'code' => 'PROP-101',
            'building_name' => 'Royal Palms',
            'status' => 'Onboarding',
        ]);

        OnboardingProject::create([
            'property_id' => $property->id,
            'status' => 'Draft',
        ]);

        $this->actingAs($user);

        Livewire::test(OnboardingQueue::class)
            ->assertSuccessful()
            ->assertSee('PROP-101');
    }
}
