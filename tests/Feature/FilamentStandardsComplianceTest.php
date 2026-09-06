<?php

namespace Tests\Feature;

use App\Domain\Agreement\Models\TenancyAgreement;
use App\Domain\Party\Models\Party;
use App\Domain\Property\Models\Property;
use App\Domain\Task\Models\Task;
use App\Filament\Pages\Operations\ReviewQueue as AuditReviewQueue;
use App\Filament\Pages\Properties\ReviewQueue as OnboardingReviewQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FilamentStandardsComplianceTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_deprecated_filament_forms_section_imports_exist_in_app(): void
    {
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(app_path()));
        $violations = [];

        foreach ($files as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $content = file_get_contents($file->getPathname());
                if (str_contains($content, 'use Filament\Forms\Components\Section;')) {
                    $violations[] = $file->getPathname();
                }
            }
        }

        $this->assertEmpty($violations, 'Found deprecated Filament\Forms\Components\Section imports in: ' . implode(', ', $violations));
    }

    public function test_no_deprecated_filament_forms_grid_imports_exist_in_app(): void
    {
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(app_path()));
        $violations = [];

        foreach ($files as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $content = file_get_contents($file->getPathname());
                if (str_contains($content, 'use Filament\Forms\Components\Grid;')) {
                    $violations[] = $file->getPathname();
                }
            }
        }

        $this->assertEmpty($violations, 'Found deprecated Filament\Forms\Components\Grid imports in: ' . implode(', ', $violations));
    }

    public function test_no_deprecated_filament_forms_tabs_imports_exist_in_app(): void
    {
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(app_path()));
        $violations = [];

        foreach ($files as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $content = file_get_contents($file->getPathname());
                if (str_contains($content, 'use Filament\Forms\Components\Tabs;')) {
                    $violations[] = $file->getPathname();
                }
            }
        }

        $this->assertEmpty($violations, 'Found deprecated Filament\Forms\Components\Tabs imports in: ' . implode(', ', $violations));
    }

    public function test_no_deprecated_tables_actions_imports_exist_in_app(): void
    {
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(app_path()));
        $violations = [];

        foreach ($files as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $content = file_get_contents($file->getPathname());
                if (str_contains($content, 'Filament\Tables\Actions\\')) {
                    $violations[] = $file->getPathname();
                }
            }
        }

        $this->assertEmpty($violations, 'Found deprecated Filament\Tables\Actions imports in: ' . implode(', ', $violations));
    }

    public function test_review_queues_have_disambiguated_navigation_and_titles(): void
    {
        $this->assertEquals('Audit Review Queue', AuditReviewQueue::getNavigationLabel());
        $this->assertEquals('Audit Review Queue', (new AuditReviewQueue())->getTitle());

        $this->assertEquals('Review Queue', OnboardingReviewQueue::getNavigationLabel());
        $this->assertEquals('Onboarding Review Queue', (new OnboardingReviewQueue())->getTitle());
    }

    public function test_party_observer_synchronizes_accounting_contact_on_party_update(): void
    {
        $org = \Tek2991\Accounting\Models\Organization::firstOrCreate(['name' => 'Dwelly Org'], [
            'legal_name' => 'Dwelly Living Private Limited',
        ]);

        $branch = \App\Models\Branch::firstOrCreate(['code' => 'HQ'], [
            'organization_id' => $org->id,
            'name' => 'Headquarters',
            'is_active' => true,
        ]);

        $party = Party::create([
            'party_type' => 'individual',
            'display_name' => 'Original Name',
            'email' => 'party.test@example.com',
            'phone' => '+919999999999',
        ]);

        $party->enableRole(\App\Domain\Party\Enums\BusinessRole::OWNER);
        $this->assertNotNull($party->accountingContact);
        $this->assertEquals('Original Name', $party->accountingContact->name);

        // Update display name directly on Party model
        $party->update(['display_name' => 'Updated Name']);

        // Assert that the observer synced the new name to the accounting contact
        $party->accountingContact->refresh();
        $this->assertEquals('Updated Name', $party->accountingContact->name);
    }

    public function test_check_agreement_renewals_command_flags_expiring_leases(): void
    {
        $org = \Tek2991\Accounting\Models\Organization::firstOrCreate(['name' => 'Dwelly Org'], [
            'legal_name' => 'Dwelly Living Private Limited',
        ]);

        $branch = \App\Models\Branch::firstOrCreate(['code' => 'HQ'], [
            'organization_id' => $org->id,
            'name' => 'Headquarters',
            'is_active' => true,
        ]);

        $owner = Party::create([
            'party_type' => 'individual',
            'display_name' => 'Owner Person',
            'email' => 'owner@example.com',
            'phone' => '+919999999991',
        ]);
        $owner->enableRole(\App\Domain\Party\Enums\BusinessRole::OWNER);

        $tenant = Party::create([
            'party_type' => 'individual',
            'display_name' => 'Tenant Person',
            'email' => 'tenant@example.com',
            'phone' => '+919999999992',
        ]);
        $tenant->enableRole(\App\Domain\Party\Enums\BusinessRole::TENANT);

        $property = Property::create([
            'branch_id' => $branch->id,
            'code' => 'PROP-REN-01',
            'building_name' => 'Sunset Apartments 101',
            'status' => 'occupied',
        ]);

        $opp = \App\Domain\Opportunity\Models\Opportunity::create([
            'number' => 'OPP-REN-001',
            'title' => 'Renewal Test Opportunity',
            'status' => \App\Domain\Opportunity\Enums\OpportunityStatus::CONVERTED,
            'owner_party_id' => $owner->id,
        ]);

        \App\Domain\Mou\Models\Mou::create([
            'property_id' => $property->id,
            'party_id' => $owner->id,
            'opportunity_id' => $opp->id,
            'type' => \App\Domain\Mou\Enums\MouType::ONBOARDING,
            'status' => \App\Domain\Opportunity\Enums\MouStatus::CONVERTED,
            'number' => 'MOU-2026-0001',
        ]);

        $agreement = TenancyAgreement::create([
            'property_id' => $property->id,
            'branch_id' => $branch->id,
            'code' => 'TA-2026-0099',
            'status' => 'active',
            'start_date' => now()->subMonths(11)->toDateString(),
            'end_date' => now()->addDays(45)->toDateString(), // 45 days left
            'rent_amount' => 25000.00,
            'security_deposit' => 50000.00,
        ]);

        $agreement->roles()->create([
            'party_id' => $tenant->id,
            'role_type' => 'Primary Tenant',
            'is_primary' => true,
        ]);

        $this->artisan('ops:check-agreement-renewals --days=60')
            ->assertExitCode(0);

        $task = Task::where('taskable_type', TenancyAgreement::class)
            ->where('taskable_id', $agreement->id)
            ->first();

        $this->assertNotNull($task);
        $this->assertStringContainsString('Tenancy Agreement Renewal Discussion', $task->title);
    }
}
