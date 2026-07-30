<?php

namespace Tests\Feature;

use App\Domain\Audit\Enums\AuditStatus;
use App\Domain\Audit\Enums\AuditType;
use App\Domain\Audit\Models\Audit;
use App\Domain\Property\Models\Property;
use App\Filament\Pages\Operations\InspectionQueue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class InspectionQueueTest extends TestCase
{
    use RefreshDatabase;

    public function test_inspection_queue_page_can_be_rendered()
    {
        $user = User::factory()->create();

        $property = Property::create([
            'building_name' => 'Ganeshguri Property',
            'address_line_1' => 'Ganeshguri, Guwahati',
            'status' => 'draft',
        ]);

        $audit = Audit::create([
            'property_id' => $property->id,
            'audit_number' => 'AUD-TEST-001',
            'audit_type' => AuditType::MOVE_IN,
            'status' => AuditStatus::DRAFT,
            'inspector_id' => $user->id,
        ]);

        $this->actingAs($user);

        Livewire::test(InspectionQueue::class)
            ->assertSuccessful()
            ->assertSee('AUD-TEST-001')
            ->assertSee('Ganeshguri Property');
    }
}
