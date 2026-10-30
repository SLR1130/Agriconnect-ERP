<?php
use Illuminate\Database\Migrations\Migration; use Illuminate\Database\Schema\Blueprint; use Illuminate\Support\Facades\Schema;
return new class extends Migration {public function up():void{Schema::table('assistance_applications',function(Blueprint $t){$t->decimal('approved_quantity',14,2)->nullable()->after('review_notes');});}public function down():void{Schema::table('assistance_applications',function(Blueprint $t){$t->dropColumn('approved_quantity');});}};
