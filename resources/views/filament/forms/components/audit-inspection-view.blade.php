<div class="mt-8">
    <div class="mb-4">
        <h2 class="text-xl font-bold text-gray-900 dark:text-white">Audit Engine</h2>
        <p class="text-sm text-gray-500">Record your findings below. The audit engine dynamically generated this structured snapshot from the property's operational components.</p>
    </div>
    
    @livewire(\App\Livewire\Operations\AuditInspectionComponent::class, ['audit' => $getRecord()])
</div>
