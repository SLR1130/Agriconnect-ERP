<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('equipment_assets', function (Blueprint $table) {
            $table->id();
            $table->string('asset_code')->unique();
            $table->string('name');
            $table->foreignId('category_id')->constrained('equipment_categories')->restrictOnDelete();
            $table->text('description')->nullable();
            $table->date('acquired_on')->nullable();
            $table->decimal('acquisition_cost', 14, 2)->nullable();
            $table->string('condition', 30)->default('good');
            $table->string('availability_status', 30)->default('available')->index();
            $table->string('assigned_location')->nullable();
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('equipment_requests', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->foreignId('farmer_id')->constrained('farmers')->cascadeOnDelete();
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('equipment_asset_id')->constrained('equipment_assets')->restrictOnDelete();
            $table->text('intended_use');
            $table->dateTime('schedule_start');
            $table->dateTime('schedule_end');
            $table->string('status', 30)->default('pending')->index();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('returned_at')->nullable();
            $table->string('checkout_condition', 30)->nullable();
            $table->string('return_condition', 30)->nullable();
            $table->string('idempotency_key')->nullable()->unique();
            $table->timestamps();

            $table->index(['equipment_asset_id', 'status', 'schedule_start', 'schedule_end'], 'equipment_reservation_window_index');
            $table->index(['farmer_id', 'status']);
        });

        Schema::create('equipment_maintenances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipment_asset_id')->constrained('equipment_assets')->cascadeOnDelete();
            $table->foreignId('reported_by')->constrained('users')->restrictOnDelete();
            $table->string('status', 30)->default('open')->index();
            $table->text('description');
            $table->decimal('cost', 14, 2)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['equipment_asset_id', 'status']);
        });

        Schema::create('equipment_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipment_asset_id')->constrained('equipment_assets')->cascadeOnDelete();
            $table->foreignId('equipment_request_id')->nullable()->constrained('equipment_requests')->nullOnDelete();
            $table->foreignId('performed_by')->constrained('users')->restrictOnDelete();
            $table->string('action', 40)->index();
            $table->string('condition', 30)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['equipment_asset_id', 'created_at']);
        });

        DB::table('equipment_categories')->insertOrIgnore([
            ['name' => 'Farm Machinery', 'description' => 'Tractors, tillers, harvesters, and related machinery.', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Hand Tools', 'description' => 'Portable tools used for agricultural operations.', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Post-Harvest Equipment', 'description' => 'Equipment for handling, drying, and storing produce.', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_transactions');
        Schema::dropIfExists('equipment_maintenances');
        Schema::dropIfExists('equipment_requests');
        Schema::dropIfExists('equipment_assets');
        Schema::dropIfExists('equipment_categories');
    }
};
