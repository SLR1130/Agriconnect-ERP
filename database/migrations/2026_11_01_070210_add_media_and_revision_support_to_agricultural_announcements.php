<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agricultural_announcement_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('announcement_id')->constrained('agricultural_announcements')->restrictOnDelete();
            $table->unsignedInteger('revision_number');
            $table->string('state', 20)->default('draft');
            $table->json('content');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->unique(['announcement_id', 'revision_number'], 'announcement_revision_number_unique');
            $table->index(['announcement_id', 'state']);
        });

        Schema::create('agricultural_announcement_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('revision_id')->constrained('agricultural_announcement_revisions')->restrictOnDelete();
            $table->string('storage_path', 500);
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('file_size');
            $table->string('alt_text', 180);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_cover')->default(false);
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['revision_id', 'sort_order']);
        });

        Schema::table('agricultural_announcements', function (Blueprint $table) {
            $table->string('office', 180)->nullable();
            $table->string('category', 100)->nullable();
            $table->string('service_area', 180)->nullable();
            $table->text('requirements_summary')->nullable();
            $table->text('contact_details')->nullable();
            $table->text('instructions')->nullable();
            $table->foreignId('current_revision_id')->nullable()->constrained('agricultural_announcement_revisions')->nullOnDelete();
            $table->foreignId('published_revision_id')->nullable()->constrained('agricultural_announcement_revisions')->nullOnDelete();
            $table->index(['category', 'status'], 'announcement_category_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('agricultural_announcements', function (Blueprint $table) {
            $table->dropForeign(['current_revision_id']);
            $table->dropForeign(['published_revision_id']);
            $table->dropIndex('announcement_category_status_idx');
            $table->dropColumn([
                'office', 'category', 'service_area', 'requirements_summary',
                'contact_details', 'instructions', 'current_revision_id', 'published_revision_id',
            ]);
        });

        Schema::dropIfExists('agricultural_announcement_media');
        Schema::dropIfExists('agricultural_announcement_revisions');
    }
};
