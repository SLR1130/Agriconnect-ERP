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
        Schema::create('agricultural_announcements', function (Blueprint $table) {
            $table->id();
            $table->string('title', 180);
            $table->text('body');
            $table->string('status', 20)->default('draft');
            $table->foreignId('assistance_program_id')->nullable()->constrained('assistance_programs')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['status', 'published_at', 'expires_at'], 'announcement_publication_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agricultural_announcements');
    }
};
