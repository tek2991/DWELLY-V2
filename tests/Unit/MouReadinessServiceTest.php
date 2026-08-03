<?php

namespace Tests\Unit;

use App\Domain\Mou\Models\Mou;
use App\Domain\Mou\Services\MouReadinessService;
use App\Domain\Opportunity\Enums\MouStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class MouReadinessServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_generate_pdf_requires_electricity_bill_for_onboarding_mou()
    {
        $user = \App\Models\User::factory()->create();
        $opportunity = \App\Domain\Opportunity\Models\Opportunity::create([
            'number' => 'OPP-BILL-001',
            'title' => 'Test Opportunity Bill',
            'owner_name' => 'John Owner',
            'owner_phone' => '9998887776',
            'assigned_user_id' => $user->id,
            'status' => \App\Domain\Opportunity\Enums\OpportunityStatus::NEW,
        ]);

        $party = \App\Domain\Party\Models\Party::create([
            'party_type' => 'individual',
            'display_name' => 'John Owner',
            'phone' => '9998887776',
        ]);

        $mou = Mou::create([
            'number' => 'MOU-TEST-BILL-001',
            'opportunity_id' => $opportunity->id,
            'party_id' => $party->id,
            'status' => MouStatus::DRAFT,
            'bank_details' => [
                'account_number' => '123456789',
                'ifsc_code' => 'SBIN0001234',
            ],
        ]);

        $service = new MouReadinessService();

        // Without electricity bill
        $this->assertFalse($service->hasElectricityBill($mou));
        $readiness = $service->canGeneratePdf($mou);
        $this->assertFalse($readiness['is_ready']);
        $this->assertContains('Electricity bill document is required.', $readiness['errors']);

        // Attach electricity bill
        $file = UploadedFile::fake()->create('electricity_bill.pdf', 100, 'application/pdf');
        $mou->addMedia($file)->toMediaCollection('electricity_bill');
        $mou->refresh();

        $this->assertTrue($service->hasElectricityBill($mou));
        $readiness = $service->canGeneratePdf($mou);
        $this->assertTrue($readiness['is_ready']);
        $this->assertEmpty($readiness['errors']);
    }
}
