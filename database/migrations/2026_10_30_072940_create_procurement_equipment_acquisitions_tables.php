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
            $table->foreignId('purchase_order_item_id');
            $table->foreignId('procurement_receipt_item_id');
            $table->foreignId('category_id');
            $table->foreignId('created_by');
            $table->string('idempotency_key')->unique('abpms_eq_acq_idem_uk');
            $table->unsignedInteger('quantity');
            $table->string('asset_name', 150);
            $table->string('condition', 30)->default('good');
            $table->string('assigned_location', 150)->nullable();
            $table->date('acquired_on');
            $table->decimal('unit_acquisition_cost', 14, 2);
            $table->timestamps();

            $table->index(['purchase_order_item_id', 'created_at'], 'abpms_equipment_acquisition_item_idx');
            $table->index(['procurement_receipt_item_id', 'created_at'], 'abpms_equipment_acquisition_receipt_idx');
            $table->foreign('purchase_order_item_id', 'abpms_eq_acq_po_item_fk')->references('id')->on('purchase_order_items')->restrictOnDelete();
            $table->foreign('procurement_receipt_item_id', 'abpms_eq_acq_receipt_fk')->references('id')->on('procurement_receipt_items')->restrictOnDelete();
            $table->foreign('category_id', 'abpms_eq_acq_category_fk')->references('id')->on('equipment_categories')->restrictOnDelete();
            $table->foreign('created_by', 'abpms_eq_acq_created_by_fk')->references('id')->on('users')->restrictOnDelete();
        });

        Schema::create('procurement_equipment_acquisition_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('procurement_equipment_acquisition_id');
            $table->foreignId('equipment_asset_id')->unique('abpms_eq_acq_asset_uk');
            $table->unsignedInteger('unit_number');
            $table->timestamps();

            $table->unique(
                ['procurement_equipment_acquisition_id', 'unit_number'],
                'abpms_equipment_acquisition_unit_unique',
            );
            $table->foreign('procurement_equipment_acquisition_id', 'abpms_eq_acq_asset_parent_fk')
                ->references('id')->on('procurement_equipment_acquisitions')->cascadeOnDelete();
            $table->foreign('equipment_asset_id', 'abpms_eq_acq_asset_fk')
                ->references('id')->on('equipment_assets')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procurement_equipment_acquisition_assets');
        Schema::dropIfExists('procurement_equipment_acquisitions');
    }
};
