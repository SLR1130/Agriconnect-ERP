<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var array<array{string, list<string>, string}> */
    private array $indexes = [
        ['farmers', ['created_at'], 'ffrms_farmers_created_idx'],
        ['crop_production_records', ['created_at'], 'apms_crops_created_idx'],
        ['crop_production_records', ['season_id', 'harvested_on'], 'apms_crop_season_harvest_idx'],
        ['livestock_records', ['status', 'recorded_on'], 'apms_livestock_status_date_idx'],
        ['fishery_records', ['recorded_on'], 'apms_fishery_recorded_idx'],
        ['pest_disease_incidents', ['status', 'reported_on'], 'apms_incident_status_date_idx'],
        ['farm_monitoring_records', ['visited_on'], 'apms_monitoring_visited_idx'],
        ['inventory_movements', ['status', 'created_at'], 'aisms_movement_status_created_idx'],
        ['assistance_applications', ['created_at', 'status'], 'aasms_application_created_status_idx'],
        ['assistance_distributions', ['status', 'distributed_at'], 'aasms_distribution_status_date_idx'],
        ['equipment_requests', ['created_at', 'status'], 'aeams_request_created_status_idx'],
        ['equipment_requests', ['status', 'schedule_start', 'schedule_end'], 'aeams_request_status_schedule_idx'],
        ['procurement_requests', ['created_at', 'status'], 'abpms_request_created_status_idx'],
        ['purchase_orders', ['created_at', 'status'], 'abpms_order_created_status_idx'],
        ['procurement_receipts', ['received_on'], 'abpms_receipt_received_idx'],
        ['procurement_equipment_acquisitions', ['acquired_on'], 'abpms_acquisition_date_idx'],
    ];

    public function up(): void
    {
        foreach ($this->indexes as [$tableName, $columns, $indexName]) {
            Schema::table($tableName, fn (Blueprint $table) => $table->index($columns, $indexName));
        }
    }

    public function down(): void
    {
        foreach (array_reverse($this->indexes) as [$tableName, $columns, $indexName]) {
            Schema::table($tableName, fn (Blueprint $table) => $table->dropIndex($indexName));
        }
    }
};
