<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('modules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('developer')->nullable();
            $table->text('description')->nullable();
            $table->string('route_prefix');
            $table->string('icon')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_enabled')->default(true);
            $table->boolean('is_active')->default(false);
            $table->integer('development_progress')->default(0);
            $table->json('config')->nullable();
            $table->timestamps();
        });

        Schema::create('module_access', function (Blueprint $table) {
            $table->id();
            $table->foreignId('module_id')->constrained()->onDelete('cascade');
            $table->foreignId('role_id')->constrained()->onDelete('cascade');
            $table->json('permissions')->nullable();
            $table->timestamps();
            
            $table->unique(['module_id', 'role_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('module_access');
        Schema::dropIfExists('modules');
    }
};