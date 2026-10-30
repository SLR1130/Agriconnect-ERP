<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('modules')
            ->where('slug', 'aeams')
            ->update([
                'is_active' => true,
                'development_progress' => 100,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('modules')
            ->where('slug', 'aeams')
            ->update([
                'is_active' => false,
                'development_progress' => 45,
                'updated_at' => now(),
            ]);
    }
};
