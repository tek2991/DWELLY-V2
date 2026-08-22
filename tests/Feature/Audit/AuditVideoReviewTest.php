<?php

namespace Tests\Feature\Audit;

use App\Domain\Audit\Enums\AuditStatus;
use App\Domain\Audit\Enums\AuditType;
use App\Domain\Audit\Enums\ItemCondition;
use App\Domain\Audit\Enums\ItemStatus;
use App\Domain\Audit\Models\Audit;
use App\Domain\Audit\Models\AuditCategory;
use App\Domain\Audit\Models\AuditItem;
use App\Domain\Audit\Services\AuditReviewService;
use App\Domain\Property\Models\Property;
use App\Livewire\Operations\AuditInspectionComponent;
use App\Livewire\Operations\AuditReviewComponent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class AuditVideoReviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Storage::fake('media');
    }

    protected function createAuditWithVideo(User $inspector, User $reviewer): Audit
    {
        $property = Property::create([
            'building_name' => 'Sunset Heights 301',
            'status' => 'vacant',
        ]);

        $audit = Audit::create([
            'property_id' => $property->id,
            'audit_type' => AuditType::MOVE_IN,
            'status' => AuditStatus::IN_REVIEW,
            'inspector_id' => $inspector->id,
            'reviewer_id' => $reviewer->id,
            'submitted_at' => now(),
            'review_started_at' => now(),
        ]);

        $file = UploadedFile::fake()->create('walkthrough.mp4', 1024, 'video/mp4');
        $audit->addMedia($file)->toMediaCollection('layout_video');

        $category = AuditCategory::create([
            'audit_id' => $audit->id,
            'name' => 'Master Bedroom',
            'sort_order' => 1,
        ]);

        AuditItem::create([
            'audit_category_id' => $category->id,
            'name' => 'Wardrobe',
            'status' => ItemStatus::INSPECTED,
            'condition' => ItemCondition::GOOD,
        ]);

        return $audit->fresh();
    }

    public function test_service_can_approve_video()
    {
        $inspector = User::factory()->create();
        $reviewer = User::factory()->create();
        $audit = $this->createAuditWithVideo($inspector, $reviewer);

        $service = app(AuditReviewService::class);
        $service->approveVideo($audit, $reviewer);

        $audit->refresh();
        $this->assertEquals('approved', $audit->video_status);
        $this->assertTrue($audit->isVideoApproved());
        $this->assertNotNull($audit->video_reviewed_at);
        $this->assertEquals($reviewer->id, $audit->video_reviewed_by_id);
        $this->assertNull($audit->video_rejection_reason);
    }

    public function test_service_can_reject_video()
    {
        $inspector = User::factory()->create();
        $reviewer = User::factory()->create();
        $audit = $this->createAuditWithVideo($inspector, $reviewer);

        $service = app(AuditReviewService::class);
        $service->rejectVideo($audit, $reviewer, 'The bathroom walkthrough was skipped', 'INCOMPLETE');

        $audit->refresh();
        $this->assertEquals('rejected', $audit->video_status);
        $this->assertTrue($audit->isVideoRejected());
        $this->assertEquals('The bathroom walkthrough was skipped', $audit->video_rejection_reason);
        $this->assertEquals('INCOMPLETE', $audit->video_rejection_type);
        $this->assertEquals($reviewer->id, $audit->video_reviewed_by_id);
    }

    public function test_service_can_reset_video_decision()
    {
        $inspector = User::factory()->create();
        $reviewer = User::factory()->create();
        $audit = $this->createAuditWithVideo($inspector, $reviewer);

        $service = app(AuditReviewService::class);
        $service->rejectVideo($audit, $reviewer, 'Blurry video', 'QUALITY');
        $this->assertTrue($audit->fresh()->isVideoRejected());

        $service->resetVideo($audit, $reviewer);

        $audit->refresh();
        $this->assertEquals('pending', $audit->video_status);
        $this->assertNull($audit->video_reviewed_at);
        $this->assertNull($audit->video_reviewed_by_id);
        $this->assertNull($audit->video_rejection_reason);
        $this->assertNull($audit->video_rejection_type);
    }

    public function test_audit_requires_both_items_and_video_to_be_approved_before_explicit_audit_approval()
    {
        $inspector = User::factory()->create();
        $reviewer = User::factory()->create();
        $audit = $this->createAuditWithVideo($inspector, $reviewer);

        $item = $audit->items->first();
        $service = app(AuditReviewService::class);

        // 1. Approve only the item - video is still pending -> cannot approve audit yet
        $service->approveItem($item, $reviewer);
        $audit->refresh();
        $this->assertEquals(AuditStatus::IN_REVIEW, $audit->status);
        $this->assertFalse($audit->canApprove());

        // 2. Approve the video - now both item and video are approved -> audit remains IN_REVIEW but canApprove is true
        $service->approveVideo($audit, $reviewer);
        $audit->refresh();
        $this->assertEquals(AuditStatus::IN_REVIEW, $audit->status);
        $this->assertTrue($audit->canApprove());

        // 3. Explicitly approve audit
        $service->approveAudit($audit, $reviewer);
        $audit->refresh();
        $this->assertEquals(AuditStatus::APPROVED, $audit->status);
        $this->assertNotNull($audit->approved_at);
        $this->assertEquals($reviewer->id, $audit->approved_by_id);
    }

    public function test_audit_demotes_from_approved_if_video_is_rejected_or_reset()
    {
        $inspector = User::factory()->create();
        $reviewer = User::factory()->create();
        $audit = $this->createAuditWithVideo($inspector, $reviewer);

        $item = $audit->items->first();
        $service = app(AuditReviewService::class);

        // Approve item, approve video, and explicitly approve audit
        $service->approveItem($item, $reviewer);
        $service->approveVideo($audit, $reviewer);
        $service->approveAudit($audit, $reviewer);
        $audit->refresh();
        $this->assertEquals(AuditStatus::APPROVED, $audit->status);

        // Scenario 1: Demotes to PARTIALLY_APPROVED when video is rejected
        $service->rejectVideo($audit, $reviewer, 'Lighting issue', 'QUALITY');
        $audit->refresh();
        $this->assertEquals(AuditStatus::PARTIALLY_APPROVED, $audit->status);
        $this->assertNull($audit->approved_at);

        // Re-approve video and audit
        $service->approveVideo($audit, $reviewer);
        $service->approveAudit($audit, $reviewer);
        $audit->refresh();
        $this->assertEquals(AuditStatus::APPROVED, $audit->status);

        // Scenario 2: Demotes to IN_REVIEW when video is reset directly from APPROVED
        $service->resetVideo($audit, $reviewer);
        $audit->refresh();
        $this->assertEquals(AuditStatus::IN_REVIEW, $audit->status);
        $this->assertNull($audit->approved_at);
    }

    public function test_accept_all_approves_all_items_and_layout_video_without_auto_approving_audit()
    {
        $inspector = User::factory()->create();
        $reviewer = User::factory()->create();
        $audit = $this->createAuditWithVideo($inspector, $reviewer);

        $service = app(AuditReviewService::class);
        $service->acceptAllItems($audit, $reviewer);

        $audit->refresh();
        // Accepting all items should NOT automatically approve the audit; it stays IN_REVIEW until explicitly approved
        $this->assertEquals(AuditStatus::IN_REVIEW, $audit->status);
        $this->assertEquals('approved', $audit->video_status);
        $this->assertEquals(ItemStatus::APPROVED, $audit->items->first()->status);
        $this->assertTrue($audit->canApprove());

        // Now explicitly approve audit
        $service->approveAudit($audit, $reviewer);
        $audit->refresh();
        $this->assertEquals(AuditStatus::APPROVED, $audit->status);
    }

    public function test_livewire_review_component_can_approve_reject_and_reset_video()
    {
        $inspector = User::factory()->create();
        $reviewer = User::factory()->create();
        $audit = $this->createAuditWithVideo($inspector, $reviewer);
        $this->actingAs($reviewer);

        $component = Livewire::test(AuditReviewComponent::class, ['audit' => $audit]);

        // Call approve video action
        $component->callAction('approveVideo');
        $this->assertEquals('approved', $audit->fresh()->video_status);

        // Call reject video action with modal form data
        $component->callAction('rejectVideo', [
            'comment_type' => 'QUALITY',
            'reason' => 'Shaky camera movement throughout walkthrough',
        ]);
        $audit->refresh();
        $this->assertEquals('rejected', $audit->video_status);
        $this->assertEquals('Shaky camera movement throughout walkthrough', $audit->video_rejection_reason);

        // Call reset video action
        $component->callAction('resetVideo');
        $audit->refresh();
        $this->assertEquals('pending', $audit->video_status);
    }

    public function test_livewire_review_component_can_approve_audit_via_separate_action_button()
    {
        $inspector = User::factory()->create();
        $reviewer = User::factory()->create();
        $audit = $this->createAuditWithVideo($inspector, $reviewer);
        $this->actingAs($reviewer);

        $component = Livewire::test(AuditReviewComponent::class, ['audit' => $audit]);

        // Accept all items and video
        $component->callAction('acceptAll');
        $audit->refresh();
        $this->assertEquals(AuditStatus::IN_REVIEW, $audit->status);

        // Explicitly call approveAudit action
        $component->callAction('approveAudit');
        $audit->refresh();
        $this->assertEquals(AuditStatus::APPROVED, $audit->status);
        $this->assertNotNull($audit->approved_at);
        $this->assertEquals($reviewer->id, $audit->approved_by_id);
    }

    public function test_inspector_reuploading_video_resets_rejected_status()
    {
        $inspector = User::factory()->create();
        $reviewer = User::factory()->create();
        $audit = $this->createAuditWithVideo($inspector, $reviewer);
        $audit->update([
            'status' => AuditStatus::PARTIALLY_APPROVED,
            'video_status' => 'rejected',
            'video_rejection_reason' => 'Too dark',
            'video_rejection_type' => 'QUALITY',
        ]);

        $this->actingAs($inspector);

        $newVideo = UploadedFile::fake()->create('new_walkthrough.mp4', 2048, 'video/mp4');

        Livewire::test(AuditInspectionComponent::class, ['audit' => $audit])
            ->set('videoUpload', $newVideo);

        $audit->refresh();
        $this->assertEquals('pending', $audit->video_status);
        $this->assertNull($audit->video_rejection_reason);
        $this->assertNull($audit->video_rejection_type);
    }

    public function test_inspector_cannot_submit_audit_if_layout_video_is_rejected()
    {
        $inspector = User::factory()->create();
        $reviewer = User::factory()->create();
        $audit = $this->createAuditWithVideo($inspector, $reviewer);
        $audit->update([
            'status' => AuditStatus::PARTIALLY_APPROVED,
            'video_status' => 'rejected',
            'video_rejection_reason' => 'Audio muted',
            'video_rejection_type' => 'AUDIO',
        ]);

        $this->actingAs($inspector);

        Livewire::test(AuditInspectionComponent::class, ['audit' => $audit])
            ->callAction('submitForReview');

        // Audit status should remain PARTIALLY_APPROVED, not PENDING_REVIEW
        $audit->refresh();
        $this->assertEquals(AuditStatus::PARTIALLY_APPROVED, $audit->status);
    }
}
