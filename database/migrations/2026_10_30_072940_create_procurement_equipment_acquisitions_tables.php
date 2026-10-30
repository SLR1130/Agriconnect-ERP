<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procurement_equipment_acquisitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_item_id')->constrained('purchase_order_items')->restrictOnDelete();
            $table->foreignId('procurement_receipt_item_id')->constrained('procurement_receipt_items')->restrictOnDelete();
            $table->foreignId('category_id')->constrained('equipment_categories')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('idempotency_key')->unique();
            $table->unsignedInteger('quantity');
            $table->string('asset_name', 150);
            $table->string('condition', 30)->default('good');
            $table->string('assigned_location', 150)->nullable();
            $table->date('acquired_on');
            $table->decimal('unit_acquisition_cost', 14, 2);
            $table->timestamps();

            $table->index(['purchase_order_item_id', 'created_at'], 'abpms_equipment_acquisition_item_idx');
            $table->index(['procurement_receipt_item_id', 'created_at'], 'abpms_equipment_acquisition_receipt_idx');
        });

        Schema::create('procurement_equipment_acquisition_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('procurement_equipment_acquisition_id')
                ->constrained('procurement_equipment_acquisitions')
                ->cascadeOnDelete();
            $table->foreignId('equipment_asset_id')->unique()->constrained('equipment_assets')->restrictOnDelete();
            $table->unsignedInteger('unit_number');
            $table->timestamps();

            $table->unique(
                ['procurement_equipment_acquisition_id', 'unit_number'],
                'abpms_equipment_acquisition_unit_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procurement_equipment_acquisition_assets');
        Schema::dropIfExists('procurement_equipment_acquisitions');
    }
};
