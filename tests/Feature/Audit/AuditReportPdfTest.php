<?php

namespace Tests\Feature\Audit;

use App\Domain\Audit\Enums\AuditStatus;
use App\Domain\Audit\Enums\AuditType;
use App\Domain\Audit\Enums\EvidenceStatus;
use App\Domain\Audit\Enums\ItemCondition;
use App\Domain\Audit\Enums\ItemStatus;
use App\Domain\Audit\Models\Audit;
use App\Domain\Audit\Models\AuditCategory;
use App\Domain\Audit\Models\AuditEvidence;
use App\Domain\Audit\Models\AuditItem;
use App\Domain\Audit\Services\AuditPdfService;
use App\Domain\Party\Models\Party;
use App\Domain\Property\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AuditReportPdfTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Storage::fake('media');
    }

    public function test_audit_pdf_service_generates_binary_pdf()
    {
        $inspector = User::factory()->create(['name' => 'Inspector Dave']);
        $reviewer = User::factory()->create(['name' => 'Reviewer Sarah']);

        $property = Property::create([
            'building_name' => 'Ocean View Apartments',
            'code' => 'PROP-OV-101',
            'address_line_1' => '42 Seaside Boulevard',
            'city' => 'Goa',
            'status' => 'active',
        ]);

        $tenant = Party::create([
            'party_type' => 'individual',
            'display_name' => 'Alice Walker',
            'phone' => '9876543210',
            'email' => 'alice@example.com',
        ]);

        $audit = Audit::create([
            'property_id' => $property->id,
            'tenant_id' => $tenant->id,
            'audit_type' => AuditType::MOVE_IN,
            'status' => AuditStatus::COMPLETED,
            'inspector_id' => $inspector->id,
            'reviewer_id' => $reviewer->id,
            'notes' => 'Property overall is well maintained. Minor touch-up needed in guest room.',
            'scheduled_at' => now()->subDays(2),
            'completed_at' => now()->subDay(),
            'approved_at' => now(),
        ]);

        $roomCategory = AuditCategory::create([
            'audit_id' => $audit->id,
            'name' => 'Master Bedroom',
            'sort_order' => 1,
        ]);

        $item1 = AuditItem::create([
            'audit_category_id' => $roomCategory->id,
            'name' => 'AC Unit (Daikin)',
            'status' => ItemStatus::INSPECTED,
            'condition' => ItemCondition::EXCELLENT,
            'remarks' => 'Cooling perfectly, filters clean.',
        ]);

        $evidence = AuditEvidence::create([
            'audit_item_id' => $item1->id,
            'status' => EvidenceStatus::ANNOTATED,
            'display_order' => 1,
            'annotation_json' => [
                'baseWidth' => 800,
                'baseHeight' => 600,
                'canvas' => [
                    'objects' => [
                        [
                            'type' => 'rect',
                            'customType' => 'rectangle',
                            'left' => 50,
                            'top' => 50,
                            'width' => 120,
                            'height' => 80,
                            'stroke' => '#ef4444',
                            'strokeWidth' => 4,
                            'remark' => 'Filter intake damaged',
                        ],
                        [
                            'type' => 'circle',
                            'customType' => 'circle',
                            'left' => 200,
                            'top' => 100,
                            'radius' => 40,
                            'stroke' => '#3b82f6',
                            'strokeWidth' => 3,
                            'remark' => 'Condenser coil clean',
                        ],
                        [
                            'type' => 'line',
                            'customType' => 'arrow',
                            'x1' => 100,
                            'y1' => 200,
                            'x2' => 250,
                            'y2' => 300,
                            'stroke' => '#10b981',
                            'strokeWidth' => 3,
                            'remark' => 'Drain line direction',
                        ],
                    ],
                ],
            ],
        ]);
        $evidence->addMedia(UploadedFile::fake()->image('ac_photo.jpg'))->toMediaCollection('images');

        $item2 = AuditItem::create([
            'audit_category_id' => $roomCategory->id,
            'name' => 'Wardrobe Mirror',
            'status' => ItemStatus::INSPECTED,
            'condition' => ItemCondition::FAIR,
            'remarks' => 'Minor scratch on bottom frame.',
        ]);

        $service = app(AuditPdfService::class);
        $binary = $service->getBinary($audit);

        $this->assertNotEmpty($binary);
        $this->assertStringStartsWith('%PDF-', $binary);

        $viewHtml = view('pdf.audit_report', [
            'audit' => $audit,
            'property' => $property,
            'tenant' => $tenant,
            'inspector' => $inspector,
            'reviewer' => $reviewer,
            'totalItems' => 2,
            'inspectedItems' => 2,
            'pendingItems' => 0,
            'progress' => 100,
            'conditionCounts' => ['excellent' => 1, 'good' => 0, 'fair' => 1, 'poor' => 0, 'damaged' => 0, 'other' => 0],
            'allItemsIndex' => [
                [
                    'index' => 1,
                    'category_name' => 'Master Bedroom',
                    'item' => $item1,
                    'condition_label' => 'Excellent',
                    'condition_color' => 'success',
                    'status_label' => 'Inspected',
                    'status_color' => 'success',
                    'photos' => [['file_name' => 'ac_photo.jpg', 'data' => 'data:image/jpeg;base64,...', 'is_annotated' => true]],
                    'photos_count' => 1,
                ],
                [
                    'index' => 2,
                    'category_name' => 'Master Bedroom',
                    'item' => $item2,
                    'condition_label' => 'Fair',
                    'condition_color' => 'warning',
                    'status_label' => 'Inspected',
                    'status_color' => 'success',
                    'photos' => [],
                    'photos_count' => 0,
                ],
            ],
            'categoriesData' => [],
            'generatedAt' => now()->format('d M Y, h:i A'),
        ])->render();

        $this->assertStringContainsString('Audit Items Index &bull; Summary Table', $viewHtml);
        $this->assertStringContainsString('AC Unit (Daikin)', $viewHtml);
        $this->assertStringContainsString('Wardrobe Mirror', $viewHtml);
        $this->assertStringContainsString('class="item-page"', $viewHtml);
        $this->assertStringContainsString('Photographic Evidence', $viewHtml);
        $this->assertStringContainsString('Annotated Evidence', $viewHtml);
    }

    public function test_authenticated_user_can_stream_audit_pdf_report()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $property = Property::create([
            'building_name' => 'Green Meadows',
            'status' => 'active',
        ]);

        $audit = Audit::create([
            'property_id' => $property->id,
            'audit_type' => AuditType::PERIODIC,
            'status' => AuditStatus::IN_PROGRESS,
            'inspector_id' => $user->id,
            'reviewer_id' => $user->id,
        ]);

        $response = $this->get(route('operations.audits.pdf', ['audit' => $audit]));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_authenticated_user_can_download_audit_pdf_report()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $property = Property::create([
            'building_name' => 'Sunrise Tower',
            'status' => 'active',
        ]);

        $audit = Audit::create([
            'property_id' => $property->id,
            'audit_type' => AuditType::MOVE_OUT,
            'status' => AuditStatus::APPROVED,
            'inspector_id' => $user->id,
            'reviewer_id' => $user->id,
        ]);

        $response = $this->get(route('operations.audits.pdf.download', ['audit' => $audit]));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('attachment;', $response->headers->get('Content-Disposition'));
    }

    public function test_unauthenticated_user_cannot_access_audit_pdf()
    {
        $property = Property::create([
            'building_name' => 'Sunrise Tower',
            'status' => 'active',
        ]);

        $audit = Audit::create([
            'property_id' => $property->id,
            'audit_type' => AuditType::MOVE_OUT,
            'status' => AuditStatus::APPROVED,
        ]);

        $response = $this->get(route('operations.audits.pdf', ['audit' => $audit]));
        $response->assertRedirect(route('login'));
    }

    public function test_audit_pdf_modal_view_renders_correctly()
    {
        $property = Property::create([
            'building_name' => 'Palm Grove Residency',
            'status' => 'active',
        ]);

        $audit = Audit::create([
            'property_id' => $property->id,
            'audit_type' => AuditType::MOVE_IN,
            'status' => AuditStatus::COMPLETED,
        ]);

        $modalHtml = view('components.audit-report-modal', ['audit' => $audit])->render();

        $this->assertStringContainsString('Inspection Report:', $modalHtml);
        $this->assertStringContainsString('Palm Grove Residency', $modalHtml);
        $this->assertStringContainsString('Download PDF', $modalHtml);
        $this->assertStringContainsString(route('operations.audits.pdf.download', ['audit' => $audit]), $modalHtml);
        $this->assertStringContainsString(route('operations.audits.pdf', ['audit' => $audit]), $modalHtml);
        $this->assertStringContainsString('<iframe', $modalHtml);
    }
}
