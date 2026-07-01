<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Reference data lookup tables that share an identical schema.
     */
    protected array $lookupTables = [
        'property_types',
        'bhk_types',
        'flooring_types',
        'furnishing_types',
        'appliance_types',
        'room_types',
        'amenity_types',
        'establishment_types',
        'vendor_trades',
        'maintenance_categories',
        'utility_types',
        'task_types',
        'task_priorities',
        'payment_modes',
        'media_collections',
        'document_types',
        'conversation_channel_types',
        'notification_trigger_types',
        'approval_step_types',
        'party_relationship_types'
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach ($this->lookupTables as $tableName) {
            Schema::create($tableName, function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->string('name');
                $table->string('slug')->unique()->nullable();
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (array_reverse($this->lookupTables) as $tableName) {
            Schema::dropIfExists($tableName);
        }
    }
};
