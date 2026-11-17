<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * MySQL executes CREATE TABLE statements outside a transaction. If an
         * older attempt stopped after creating the tables but before the
         * long-name indexes were added, resume that additive migration safely.
         */
        if (Schema::hasTable('budget_periods')) {
            if (! Schema::hasTable('budget_transactions')) {
                Schema::create('budget_transactions', function (Blueprint $table) {
                    $table->id();
                    $table->foreignId('budget_allocation_id')->constrained('budget_allocations')->restrictOnDelete();
                    $table->string('entry_type', 30);
                    $table->string('direction', 10);
                    $table->decimal('amount', 14, 2);
                    $table->string('reference')->unique();
                    $table->text('description');
                    $table->foreignId('performed_by')->constrained('users')->restrictOnDelete();
                    $table->foreignId('procurement_request_id')->nullable()->constrained('procurement_requests')->nullOnDelete();
                    $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders')->nullOnDelete();
                    $table->timestamps();
                    $table->index(['budget_allocation_id', 'entry_type'], 'abpms_budget_allocation_type_idx');
                });
            }

            $database = DB::getDatabaseName();
            $indexExists = static fn (string $table, string $index): bool => DB::table('information_schema.statistics')
                ->where('table_schema', $database)
                ->where('table_name', $table)
                ->where('index_name', $index)
                ->exists();

            if (! $indexExists('procurement_status_histories', 'abpms_status_request_created_idx')) {
                Schema::table('procurement_status_histories', fn (Blueprint $table) => $table->index(['procurement_request_id', 'created_at'], 'abpms_status_request_created_idx'));
            }
            if (! $indexExists('procurement_status_histories', 'abpms_status_order_created_idx')) {
                Schema::table('procurement_status_histories', fn (Blueprint $table) => $table->index(['purchase_order_id', 'created_at'], 'abpms_status_order_created_idx'));
            }

            return;
        }

        Schema::create('budget_periods', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year')->unique();
            $table->string('name');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('status', 20)->default('open')->index();
            $table->timestamps();
        });

        Schema::create('budget_allocations', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->foreignId('budget_period_id')->constrained('budget_periods')->restrictOnDelete();
            $table->string('program_code', 80);
            $table->string('funding_category', 120);
            $table->text('description');
            $table->decimal('authorized_amount', 14, 2);
            $table->string('status', 20)->default('active')->index();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['budget_period_id', 'program_code']);
        });

        Schema::create('procurement_suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('contact_person')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('address')->nullable();
            $table->string('registration_reference')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('procurement_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_number')->unique();
            $table->foreignId('budget_allocation_id')->constrained('budget_allocations')->restrictOnDelete();
            $table->foreignId('requester_id')->constrained('users')->restrictOnDelete();
            $table->string('requesting_office', 150);
            $table->text('purpose');
            $table->text('justification')->nullable();
            $table->decimal('estimated_total', 14, 2);
            $table->string('status', 30)->default('submitted')->index();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->string('idempotency_key')->nullable()->unique();
            $table->timestamps();

            $table->index(['budget_allocation_id', 'status']);
        });

        Schema::create('procurement_request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('procurement_request_id')->constrained('procurement_requests')->cascadeOnDelete();
            $table->foreignId('supply_item_id')->nullable()->constrained('supply_items')->nullOnDelete();
            $table->string('description');
            $table->decimal('quantity', 14, 2);
            $table->decimal('estimated_unit_price', 14, 2);
            $table->decimal('estimated_total', 14, 2);
            $table->timestamps();
        });

        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('purchase_order_number')->unique();
            $table->foreignId('procurement_request_id')->constrained('procurement_requests')->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained('procurement_suppliers')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('total_amount', 14, 2);
            $table->date('ordered_on')->nullable();
            $table->date('expected_delivery_on')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('status', 30)->default('draft')->index();
            $table->text('notes')->nullable();
            $table->string('idempotency_key')->nullable()->unique();
            $table->timestamps();

            $table->index(['procurement_request_id', 'status']);
        });

        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->foreignId('procurement_request_item_id')->constrained('procurement_request_items')->restrictOnDelete();
            $table->foreignId('supply_item_id')->nullable()->constrained('supply_items')->nullOnDelete();
            $table->foreignId('equipment_asset_id')->nullable()->unique()->constrained('equipment_assets')->nullOnDelete();
            $table->string('description');
            $table->decimal('ordered_quantity', 14, 2);
            $table->decimal('unit_price', 14, 2);
            $table->decimal('line_total', 14, 2);
            $table->decimal('received_quantity', 14, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('procurement_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_reference')->unique();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->restrictOnDelete();
            $table->foreignId('received_by')->constrained('users')->restrictOnDelete();
            $table->date('received_on');
            $table->text('notes')->nullable();
            $table->string('idempotency_key')->unique();
            $table->timestamps();
        });

        Schema::create('procurement_receipt_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('procurement_receipt_id')->constrained('procurement_receipts')->cascadeOnDelete();
            $table->foreignId('purchase_order_item_id')->constrained('purchase_order_items')->restrictOnDelete();
            $table->decimal('quantity', 14, 2);
            $table->foreignId('inventory_movement_id')->nullable()->constrained('inventory_movements')->nullOnDelete();
            $table->string('batch_number')->nullable();
            $table->date('expires_on')->nullable();
            $table->foreignId('inventory_location_id')->nullable()->constrained('inventory_locations')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('procurement_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('procurement_request_id')->nullable()->constrained('procurement_requests')->cascadeOnDelete();
            $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders')->cascadeOnDelete();
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
            $table->text('notes')->nullable();
            $table->foreignId('processed_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['procurement_request_id', 'created_at'], 'abpms_status_request_created_idx');
            $table->index(['purchase_order_id', 'created_at'], 'abpms_status_order_created_idx');
        });

        Schema::create('budget_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_allocation_id')->constrained('budget_allocations')->restrictOnDelete();
            $table->string('entry_type', 30);
            $table->string('direction', 10);
            $table->decimal('amount', 14, 2);
            $table->string('reference')->unique();
            $table->text('description');
            $table->foreignId('performed_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('procurement_request_id')->nullable()->constrained('procurement_requests')->nullOnDelete();
            $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders')->nullOnDelete();
            $table->timestamps();

            $table->index(['budget_allocation_id', 'entry_type'], 'abpms_budget_allocation_type_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_transactions');
        Schema::dropIfExists('procurement_status_histories');
        Schema::dropIfExists('procurement_receipt_items');
        Schema::dropIfExists('procurement_receipts');
        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');
        Schema::dropIfExists('procurement_request_items');
        Schema::dropIfExists('procurement_requests');
        Schema::dropIfExists('procurement_suppliers');
        Schema::dropIfExists('budget_allocations');
        Schema::dropIfExists('budget_periods');
    }
};
