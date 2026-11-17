<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('assistance_programs', function (Blueprint $table) {
            $table->string('category')->nullable()->after('name');
            $table->json('eligibility_rules')->nullable()->after('description');
            $table->foreignId('supply_item_id')->nullable()->after('eligibility_rules')->constrained('supply_items')->nullOnDelete();
            $table->decimal('allocation_limit', 14, 2)->nullable()->after('supply_item_id');
        });
        Schema::table('assistance_applications', function (Blueprint $table) {
            $table->boolean('eligibility_passed')->nullable()->after('purpose');
            $table->text('eligibility_notes')->nullable()->after('eligibility_passed');
            $table->foreignId('approved_supply_item_id')->nullable()->after('approved_quantity')->constrained('supply_items')->nullOnDelete();
            $table->timestamp('allocation_confirmed_at')->nullable()->after('approved_supply_item_id');
        });
    }
    public function down(): void
    {
        Schema::table('assistance_applications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approved_supply_item_id');
            $table->dropColumn(['eligibility_passed', 'eligibility_notes', 'allocation_confirmed_at']);
        });
        Schema::table('assistance_programs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supply_item_id');
            $table->dropColumn(['category', 'eligibility_rules', 'allocation_limit']);
        });
    }
};
