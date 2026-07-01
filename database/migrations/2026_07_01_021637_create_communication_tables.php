<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_templates', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('event_name')->unique(); // e.g. TenancyActivated, RentInvoiceGenerated
            $table->string('channel'); // email, whatsapp, sms
            $table->string('subject')->nullable();
            $table->text('body');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('communication_logs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('party_id', 26)->nullable();
            $table->char('template_id', 26)->nullable();
            $table->string('channel');
            $table->string('recipient');
            $table->string('subject')->nullable();
            $table->text('body');
            $table->string('status')->default('sent'); // sent, delivered, failed
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->foreign('party_id')->references('id')->on('parties')->nullOnDelete();
            $table->foreign('template_id')->references('id')->on('notification_templates')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('communication_logs');
        Schema::dropIfExists('notification_templates');
    }
};
