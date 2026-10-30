<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('farms', function (Blueprint $table) {
            $table->string('classification')->nullable()->after('ownership_type');
            $table->string('tenancy_details')->nullable()->after('classification');
            $table->string('soil_type')->nullable()->after('tenancy_details');
            $table->string('irrigation_type')->nullable()->after('soil_type');
        });

        Schema::create('farmer_associations', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('registration_reference')->nullable()->unique();
            $table->string('barangay')->nullable()->index();
            $table->text('description')->nullable();
            $table->string('status')->default('active')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('farmer_association_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('association_id')->constrained('farmer_associations')->cascadeOnDelete();
            $table->foreignId('farmer_id')->constrained()->cascadeOnDelete();
            $table->string('membership_status')->default('active');
            $table->timestamp('joined_at')->nullable();
            $table->timestamps();
            $table->unique(['association_id', 'farmer_id']);
        });

        Schema::create('agricultural_seasons', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('status')->default('open')->index();
            $table->timestamps();
            $table->unique(['name', 'starts_on']);
        });

        Schema::create('crop_production_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farmer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('farm_id')->constrained()->cascadeOnDelete();
            $table->foreignId('season_id')->nullable()->constrained('agricultural_seasons')->nullOnDelete();
            $table->string('crop_type');
            $table->string('variety')->nullable();
            $table->date('planted_on')->nullable();
            $table->date('harvested_on')->nullable();
            $table->decimal('area_planted', 10, 2)->nullable();
            $table->string('area_unit', 20)->default('hectare');
            $table->decimal('yield_quantity', 14, 2)->nullable();
            $table->string('yield_unit', 30)->default('kilogram');
            $table->string('status')->default('planned')->index();
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['farm_id', 'crop_type', 'status']);
        });

        Schema::create('livestock_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farmer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('farm_id')->constrained()->cascadeOnDelete();
            $table->string('animal_type');
            $table->string('breed')->nullable();
            $table->unsignedInteger('quantity');
            $table->date('recorded_on');
            $table->string('status')->default('active')->index();
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('fishery_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farmer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('farm_id')->constrained()->cascadeOnDelete();
            $table->string('fishery_type');
            $table->string('species')->nullable();
            $table->decimal('production_quantity', 14, 2)->nullable();
            $table->string('production_unit', 30)->default('kilogram');
            $table->date('recorded_on');
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('pest_disease_incidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farmer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('farm_id')->constrained()->cascadeOnDelete();
            $table->string('incident_type');
            $table->string('affected_crop_or_animal');
            $table->date('reported_on');
            $table->string('severity')->default('medium')->index();
            $table->string('status')->default('reported')->index();
            $table->text('description')->nullable();
            $table->text('action_taken')->nullable();
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('farm_monitoring_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farmer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('farm_id')->constrained()->cascadeOnDelete();
            $table->date('visited_on');
            $table->string('monitoring_type')->default('field_visit');
            $table->text('findings');
            $table->text('recommendations')->nullable();
            $table->string('status')->default('recorded')->index();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('farm_monitoring_records');
        Schema::dropIfExists('pest_disease_incidents');
        Schema::dropIfExists('fishery_records');
        Schema::dropIfExists('livestock_records');
        Schema::dropIfExists('crop_production_records');
        Schema::dropIfExists('agricultural_seasons');
        Schema::dropIfExists('farmer_association_members');
        Schema::dropIfExists('farmer_associations');
        Schema::table('farms', function (Blueprint $table) {
            $table->dropColumn(['classification', 'tenancy_details', 'soil_type', 'irrigation_type']);
        });
    }
};
