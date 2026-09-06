<?php

namespace Tests\Feature\Navigation;

use App\Filament\Clusters\AdministrationCluster;
use App\Filament\Clusters\AuditsCluster;
use App\Filament\Clusters\GeographicCluster;
use App\Filament\Clusters\PropertiesCluster;
use App\Filament\Clusters\ReferenceData\ReferenceDataCluster;
use App\Filament\Pages\Billing\BulkGenerateMonthlyRent;
use App\Filament\Pages\Billing\BulkGenerateOwnerPayouts;
use App\Filament\Pages\Billing\FinancialDashboard;
use App\Filament\Pages\Billing\FinancialOperationsHub;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\Operations\OperationsDashboard;
use App\Filament\Resources\Billing\MaintenanceBillingResource;
use App\Filament\Resources\Billing\MaintenanceQuotationResource;
use App\Filament\Resources\Billing\Pages\ListRentDemands;
use App\Filament\Resources\Billing\RentDemandsResource;
use App\Filament\Resources\CommunicationLogs\CommunicationLogResource;
use App\Filament\Resources\NotificationTemplates\NotificationTemplateResource;
use App\Filament\Resources\Operations\MaintenanceRequestResource;
use App\Filament\Resources\Operations\MOUResource;
use App\Filament\Resources\Operations\OpportunityResource;
use App\Filament\Resources\Operations\TaskResource;
use App\Filament\Resources\Operations\TenantDeboardingResource;
use App\Filament\Resources\OwnerPayouts\OwnerPayoutResource;
use App\Filament\Resources\OwnerPayouts\Pages\ListOwnerPayouts;
use App\Filament\Resources\Parties\PartyResource;
use App\Filament\Resources\TenancyAgreements\TenancyAgreementResource;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class NavigationHierarchyTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_main_dashboard_anchors_top(): void
    {
        $this->assertEquals(-1, Dashboard::getNavigationSort());
        $this->assertEquals('Main Dashboard', Dashboard::getNavigationLabel());
    }

    public function test_properties_and_leasing_group_items(): void
    {
        $this->assertEquals('Properties & Leasing', OperationsDashboard::getNavigationGroup());
        $this->assertEquals(1, OperationsDashboard::getNavigationSort());

        $this->assertEquals('Properties & Leasing', PropertiesCluster::getNavigationGroup());
        $this->assertEquals(2, PropertiesCluster::getNavigationSort());

        $this->assertEquals('Properties & Leasing', TenancyAgreementResource::getNavigationGroup());
        $this->assertEquals(3, TenancyAgreementResource::getNavigationSort());

        $this->assertEquals('Properties & Leasing', TenantDeboardingResource::getNavigationGroup());
        $this->assertEquals(4, TenantDeboardingResource::getNavigationSort());
    }

    public function test_maintenance_and_field_ops_group_items(): void
    {
        $this->assertEquals('Maintenance & Field Ops', MaintenanceRequestResource::getNavigationGroup());
        $this->assertEquals(1, MaintenanceRequestResource::getNavigationSort());

        $this->assertEquals('Maintenance & Field Ops', TaskResource::getNavigationGroup());
        $this->assertEquals(2, TaskResource::getNavigationSort());

        $this->assertEquals('Maintenance & Field Ops', AuditsCluster::getNavigationGroup());
        $this->assertEquals('Audits & Inspections', AuditsCluster::getNavigationLabel());
        $this->assertEquals(3, AuditsCluster::getNavigationSort());
    }

    public function test_billing_and_finance_group_items(): void
    {
        $this->assertEquals('Billing & Finance', FinancialDashboard::getNavigationGroup());
        $this->assertEquals(1, FinancialDashboard::getNavigationSort());

        $this->assertEquals('Billing & Finance', RentDemandsResource::getNavigationGroup());
        $this->assertEquals(2, RentDemandsResource::getNavigationSort());

        $this->assertEquals('Billing & Finance', OwnerPayoutResource::getNavigationGroup());
        $this->assertEquals(3, OwnerPayoutResource::getNavigationSort());

        $this->assertEquals('Billing & Finance', MaintenanceBillingResource::getNavigationGroup());
        $this->assertEquals(4, MaintenanceBillingResource::getNavigationSort());

        $this->assertEquals('Billing & Finance', MaintenanceQuotationResource::getNavigationGroup());
        $this->assertEquals(5, MaintenanceQuotationResource::getNavigationSort());

        $this->assertEquals('Billing & Finance', FinancialOperationsHub::getNavigationGroup());
        $this->assertEquals(6, FinancialOperationsHub::getNavigationSort());

        // Bulk generators should NOT register in sidebar
        $this->assertFalse(BulkGenerateMonthlyRent::shouldRegisterNavigation());
        $this->assertFalse(BulkGenerateOwnerPayouts::shouldRegisterNavigation());
    }

    public function test_directory_and_crm_group_items(): void
    {
        $this->assertEquals('Directory & CRM', PartyResource::getNavigationGroup());
        $this->assertEquals('Parties & Contacts', PartyResource::getNavigationLabel());
        $this->assertEquals(1, PartyResource::getNavigationSort());

        $this->assertEquals('Directory & CRM', OpportunityResource::getNavigationGroup());
        $this->assertEquals('Sales Opportunities', OpportunityResource::getNavigationLabel());
        $this->assertEquals(2, OpportunityResource::getNavigationSort());

        $this->assertEquals('Directory & CRM', MOUResource::getNavigationGroup());
        $this->assertEquals('Owner MOUs', MOUResource::getNavigationLabel());
        $this->assertEquals(3, MOUResource::getNavigationSort());

        $this->assertEquals('Directory & CRM', CommunicationLogResource::getNavigationGroup());
        $this->assertEquals('Communication Logs', CommunicationLogResource::getNavigationLabel());
        $this->assertEquals(4, CommunicationLogResource::getNavigationSort());
    }

    public function test_settings_group_clusters_sort(): void
    {
        $this->assertEquals('Settings', AdministrationCluster::getNavigationGroup());
        $this->assertEquals(1, AdministrationCluster::getNavigationSort());

        $this->assertEquals('Settings', GeographicCluster::getNavigationGroup());
        $this->assertEquals(2, GeographicCluster::getNavigationSort());

        $this->assertEquals('Settings', ReferenceDataCluster::getNavigationGroup());
        $this->assertEquals(3, ReferenceDataCluster::getNavigationSort());

        $this->assertEquals('Settings', NotificationTemplateResource::getNavigationGroup());
        $this->assertEquals(4, NotificationTemplateResource::getNavigationSort());
    }

    public function test_rent_demands_list_page_has_bulk_generate_header_action(): void
    {
        $this->actingAs($this->user);

        Livewire::test(ListRentDemands::class)
            ->assertActionExists('bulkGenerateRent');
    }

    public function test_owner_payouts_list_page_has_bulk_disburse_header_action(): void
    {
        $this->actingAs($this->user);

        Livewire::test(ListOwnerPayouts::class)
            ->assertActionExists('bulkDisbursePayouts');
    }
}
