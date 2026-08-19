<?php

namespace Tests\Feature\Administration;

use App\Domain\Maintenance\Models\MaintenanceClientQuote;
use App\Domain\Maintenance\Models\MaintenanceRequest;
use App\Domain\Property\Models\Property;
use App\Domain\Shared\Models\SystemSetting;
use App\Domain\Shared\Services\SettingService;
use App\Filament\Pages\Administration\ManageFinancialSettings;
use App\Filament\Resources\Billing\MaintenanceQuotationResource\Pages\ManageClientQuotation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FinancialSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_setting_service_gets_and_sets_values(): void
    {
        $this->assertEquals(10.00, SettingService::get('financials.default_margin_percentage', 10.00));

        SettingService::set('financials.default_margin_percentage', 15.50, 'decimal', 'Custom margin');

        $this->assertEquals(15.50, SettingService::get('financials.default_margin_percentage'));
        $this->assertDatabaseHas('system_settings', [
            'group' => 'financials',
            'key' => 'default_margin_percentage',
            'value' => '15.5',
        ]);
    }

    public function test_manage_financial_settings_page_can_be_rendered(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(ManageFinancialSettings::class)
            ->assertSuccessful()
            ->assertSee('Financial Settings')
            ->assertSee('Dwelly Coordination / Margin Markup (%)')
            ->assertSee('GST / Tax Rate (%)')
            ->assertSee('Quotation Validity (Days)');
    }

    public function test_manage_financial_settings_page_saves_new_values(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(ManageFinancialSettings::class)
            ->fillForm([
                'default_margin_percentage' => 12.50,
                'default_gst_percentage' => 18.00,
                'default_quotation_validity_days' => 30,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertEquals(12.50, SettingService::get('financials.default_margin_percentage'));
        $this->assertEquals(18.00, SettingService::get('financials.default_gst_percentage'));
        $this->assertEquals(30, SettingService::get('financials.default_quotation_validity_days'));
    }

    public function test_maintenance_quotation_uses_custom_financial_defaults(): void
    {
        SettingService::set('financials.default_margin_percentage', 15.00, 'decimal');
        SettingService::set('financials.default_gst_percentage', 12.00, 'decimal');
        SettingService::set('financials.default_quotation_validity_days', 21, 'integer');

        $user = User::factory()->create();
        $property = Property::create([
            'building_name' => 'Setting Test Villa',
            'code' => 'STV-01',
            'status' => 'active',
        ]);

        $request = MaintenanceRequest::create([
            'ticket_number' => 'TICK-4455',
            'property_id' => $property->id,
            'title' => 'Test Ticket',
            'status' => 'submitted',
        ]);

        $quote = MaintenanceClientQuote::create([
            'quote_number' => 'QT-2026-4455',
            'maintenance_request_id' => $request->id,
            'status' => 'draft',
        ]);

        Livewire::actingAs($user)
            ->test(ManageClientQuotation::class, ['record' => $quote->getRouteKey()])
            ->assertSuccessful()
            ->assertFormSet([
                'margin_percentage' => '15.00',
                'gst_percentage' => '12.00',
            ]);

        $this->assertEquals(15.00, SettingService::get('financials.default_margin_percentage'));
        $this->assertEquals(12.00, SettingService::get('financials.default_gst_percentage'));
        $this->assertEquals(21, SettingService::get('financials.default_quotation_validity_days'));
    }
}
