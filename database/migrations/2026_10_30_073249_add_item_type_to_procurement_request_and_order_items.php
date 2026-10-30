<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procurement_request_items', function (Blueprint $table) {
            $table->string('item_type', 20)->default('equipment')->after('supply_item_id');
        });

        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->string('item_type', 20)->default('equipment')->after('supply_item_id');
        });

        DB::table('procurement_request_items')->whereNotNull('supply_item_id')->update(['item_type' => 'supply']);
        DB::table('purchase_order_items')->whereNotNull('supply_item_id')->update(['item_type' => 'supply']);
    }

    public function down(): void
    {
        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->dropColumn('item_type');
        });

        Schema::table('procurement_request_items', function (Blueprint $table) {
            $table->dropColumn('item_type');
        });
    }
};
