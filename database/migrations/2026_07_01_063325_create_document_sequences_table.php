<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected function prefix(): string
    {
        return config('accounting.table_prefix', 'acc_');
    }

    public function up(): void
    {
        $prefix = $this->prefix();

        Schema::create("{$prefix}document_sequences", function (Blueprint $table) use ($prefix) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained("branches")->restrictOnDelete();
            
            $table->string('document_type', 50); // invoice, bill, credit_note, debit_note, payment, journal
            $table->string('prefix', 20)->default('');
            $table->unsignedBigInteger('next_number')->default(1);
            
            $table->timestamps();
            
            $table->unique(['branch_id', 'document_type'], "{$prefix}doc_seq_unique");
        });
    }

    public function down(): void
    {
        $prefix = $this->prefix();
        Schema::dropIfExists("{$prefix}document_sequences");
    }
};
