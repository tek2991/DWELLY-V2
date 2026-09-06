<?php

namespace App\Console\Commands;

use App\Domain\Agreement\Models\TenancyAgreement;
use App\Domain\Task\Enums\TaskCategory;
use App\Domain\Task\Enums\TaskPriority;
use App\Domain\Task\Models\Task;
use App\Domain\Task\Services\TaskService;
use Illuminate\Console\Command;

class CheckAgreementRenewals extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ops:check-agreement-renewals {--days=60}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scans for active tenancy agreements expiring within 60 days and creates renewal tasks';

    /**
     * Execute the console command.
     */
    public function handle(TaskService $taskService): int
    {
        $days = (int) $this->option('days');
        $this->info("Scanning for active tenancy agreements expiring in the next {$days} days...");

        $agreements = TenancyAgreement::where('status', 'active')
            ->whereNotNull('end_date')
            ->whereBetween('end_date', [now()->startOfDay(), now()->addDays($days)->endOfDay()])
            ->with(['property.owner', 'primaryTenant.party'])
            ->get();

        $createdCount = 0;

        foreach ($agreements as $agreement) {
            // Check if a renewal task already exists for this agreement
            $existingTask = Task::where('taskable_type', TenancyAgreement::class)
                ->where('taskable_id', $agreement->id)
                ->where('category', TaskCategory::LIFECYCLE)
                ->where('title', 'like', '%Agreement Renewal%')
                ->first();

            if ($existingTask) {
                continue;
            }

            $property = $agreement->property;
            if (!$property) {
                continue;
            }

            $tenantName = $agreement->primaryTenant?->party?->display_name ?? 'Tenant';
            $daysLeft = (int) now()->startOfDay()->diffInDays(\Carbon\Carbon::parse($agreement->end_date)->startOfDay(), false);

            $taskService->createTask([
                'branch_id' => $property->branch_id,
                'property_id' => $property->id,
                'taskable_type' => TenancyAgreement::class,
                'taskable_id' => $agreement->id,
                'category' => TaskCategory::LIFECYCLE,
                'title' => "Tenancy Agreement Renewal Discussion — {$tenantName} ({$agreement->code})",
                'description' => "Agreement {$agreement->code} expires on " . \Carbon\Carbon::parse($agreement->end_date)->format('d M Y') . " ({$daysLeft} days remaining). Initiate renewal discussion with owner and tenant.",
                'priority' => $daysLeft <= 30 ? TaskPriority::HIGH : TaskPriority::MEDIUM,
                'due_date' => now()->addDays(7),
                'checklist_items' => [
                    ['title' => 'Contact owner to confirm lease renewal willingness and revised rent expectations', 'is_mandatory' => true],
                    ['title' => 'Contact tenant to gauge extension intent and share proposed terms', 'is_mandatory' => true],
                    ['title' => 'Draft revised Tenancy Agreement on platform if terms agreed', 'is_mandatory' => false],
                    ['title' => 'Initiate move-out deboarding workflow if tenant decides to vacate', 'is_mandatory' => false],
                ],
            ]);

            $createdCount++;
        }

        $this->info("Scan completed. Created {$createdCount} renewal follow-up tasks.");

        return self::SUCCESS;
    }
}
