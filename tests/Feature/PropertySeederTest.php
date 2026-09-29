<?php

namespace Tests\Feature;

use App\Domain\Opportunity\Models\Opportunity;
use App\Domain\Shared\Models\NumberingSequence;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PropertySeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_property_seeder_generates_sequential_opportunity_numbers_via_numbering_service(): void
    {
        $this->seed(DatabaseSeeder::class);

        $opportunities = Opportunity::orderBy('number')->get();
        $this->assertNotEmpty($opportunities);

        // First opportunity should be 00001
        $year = now()->year;
        $this->assertEquals("OPP-{$year}-00001", $opportunities->first()->number);

        // Sequence in numbering_sequences table should be recorded and match count
        $sequence = NumberingSequence::where('entity_type', 'opportunity')->first();
        $this->assertNotNull($sequence);
        $this->assertEquals($opportunities->count(), $sequence->last_sequence);
    }
}
