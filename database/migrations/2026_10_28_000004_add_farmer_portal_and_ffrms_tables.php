<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('password');
            $table->timestamp('suspended_at')->nullable()->after('is_active');
            $table->timestamp('activated_at')->nullable()->after('suspended_at');
        });

        Schema::create('farmers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('farmer_code')->unique();
            $table->string('phone', 30)->nullable();
            $table->string('address')->nullable();
            $table->string('barangay')->nullable()->index();
            $table->string('registration_status')->default('pending')->index();
            $table->text('review_notes')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('farmer_registration_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farmer_id')->constrained()->cascadeOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->text('reason')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['farmer_id', 'created_at']);
        });

        Schema::create('farms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farmer_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('barangay')->index();
            $table->string('location_details')->nullable();
            $table->decimal('area', 10, 2)->nullable();
            $table->string('area_unit', 20)->default('hectare');
            $table->string('ownership_type')->default('owner');
            $table->json('agricultural_activities')->nullable();
            $table->json('categories')->nullable();
            $table->string('status')->default('active')->index();
            $table->timestamps();

            $table->index(['farmer_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('farms');
        Schema::dropIfExists('farmer_registration_histories');
        Schema::dropIfExists('farmers');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['is_active', 'suspended_at', 'activated_at']);
        });
    }
};
