<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('workflow_requests')) {
            Schema::create('workflow_requests', function (Blueprint $table) {
                $table->id();
                $table->string('reference')->unique();
                $table->foreignId('module_id')->nullable()->constrained('modules')->nullOnDelete();
                $table->foreignId('requester_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
                $table->string('type');
                $table->string('title');
                $table->text('description')->nullable();
                $table->string('status')->default('pending')->index();
                $table->json('payload')->nullable();
                $table->timestamp('due_at')->nullable()->index();
                $table->timestamp('submitted_at')->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->timestamps();

                $table->index(['module_id', 'status']);
                $table->index(['requester_id', 'status']);
            });
        }

        if (! Schema::hasTable('workflow_transitions')) {
            Schema::create('workflow_transitions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workflow_request_id')->constrained('workflow_requests')->cascadeOnDelete();
                $table->string('from_status')->nullable();
                $table->string('to_status');
                $table->foreignId('transitioned_by')->nullable()->constrained('users')->nullOnDelete();
                $table->text('comments')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['workflow_request_id', 'created_at']);
            });
        }

        if (! Schema::hasTable('notifications')) {
            Schema::create('notifications', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('type');
                $table->string('notifiable_type');
                $table->unsignedBigInteger('notifiable_id');
                $table->json('data');
                $table->timestamp('read_at')->nullable();
                $table->timestamps();

                $table->index(['notifiable_type', 'notifiable_id']);
                $table->index(['notifiable_id', 'read_at']);
            });
        }

        if (! Schema::hasTable('system_settings')) {
            Schema::create('system_settings', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();
                $table->string('group')->default('general')->index();
                $table->json('value')->nullable();
                $table->boolean('is_public')->default(false);
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_transitions');
        Schema::dropIfExists('workflow_requests');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('system_settings');
    }
};
