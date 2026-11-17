<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration { public function up(): void { Schema::table('inventory_movements', function(Blueprint $t){$t->string('idempotency_key')->nullable()->unique();$t->string('adjustment_reason')->nullable();}); } public function down(): void { Schema::table('inventory_movements', function(Blueprint $t){$t->dropUnique(['idempotency_key']);$t->dropColumn(['idempotency_key','adjustment_reason']);}); } };
