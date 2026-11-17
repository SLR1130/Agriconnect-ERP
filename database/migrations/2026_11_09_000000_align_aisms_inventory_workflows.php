<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supply_batches', function (Blueprint $table) {
            $table->foreignId('supplier_id')->nullable()->after('location_id')
                ->constrained('procurement_suppliers')->nullOnDelete();
            $table->date('manufactured_on')->nullable()->after('received_on');
            $table->decimal('unit_cost', 14, 2)->nullable()->after('quantity_available');
        });

        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->foreignId('related_batch_id')->nullable()->after('supply_batch_id')
                ->constrained('supply_batches')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('related_batch_id');
        });

        Schema::table('supply_batches', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supplier_id');
            $table->dropColumn(['manufactured_on', 'unit_cost']);
        });
    }
};
