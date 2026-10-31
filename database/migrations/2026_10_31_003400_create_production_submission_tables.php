<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('production_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farmer_id')->constrained()->restrictOnDelete();
            $table->foreignId('farm_id')->constrained()->restrictOnDelete();
            $table->string('category', 20);
            $table->json('payload');
            $table->string('status', 20)->default('pending');
            $table->string('idempotency_key', 100);
            $table->text('review_notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->unsignedBigInteger('official_record_id')->nullable();
            $table->timestamps();
            $table->unique(['farmer_id', 'idempotency_key'], 'production_submission_retry_unique');
            $table->index(['status', 'category', 'created_at'], 'production_submission_queue_idx');
        });
        Schema::create('production_submission_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_submission_id')->constrained()->restrictOnDelete();
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('production_submission_histories');
        Schema::dropIfExists('production_submissions');
    }
};
