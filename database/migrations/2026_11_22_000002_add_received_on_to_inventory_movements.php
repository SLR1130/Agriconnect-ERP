<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table): void {
            $table->date('received_on')->nullable();
        });
    }

    public function down(): void
    {
        throw new LogicException('Removing per-receipt dates would erase historical evidence. Restore a verified backup instead.');
    }
};
