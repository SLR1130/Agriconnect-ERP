<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assistance_distributions', function (Blueprint $table): void {
            $table->string('farmer_barangay_at_distribution', 100)->nullable()->index('aasms_dist_barangay_idx');
        });
    }

    public function down(): void
    {
        throw new LogicException('Removing recorded distribution barangays would erase historical data. Restore a verified backup instead.');
    }
};
