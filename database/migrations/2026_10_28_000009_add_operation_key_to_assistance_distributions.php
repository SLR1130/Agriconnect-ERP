<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration { public function up():void {Schema::table('assistance_distributions',function(Blueprint $t){$t->string('operation_key')->nullable()->unique()->after('reference');});} public function down():void {Schema::table('assistance_distributions',function(Blueprint $t){$t->dropUnique(['operation_key']);$t->dropColumn('operation_key');});}};
