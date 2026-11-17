<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
return new class extends Migration {
 public function up(): void {
  Schema::create('supply_categories', function(Blueprint $t){$t->id();$t->string('name')->unique();$t->text('description')->nullable();$t->boolean('is_active')->default(true);$t->timestamps();});
  Schema::create('units_of_measurement', function(Blueprint $t){$t->id();$t->string('name')->unique();$t->string('symbol',20)->unique();$t->timestamps();});
  Schema::create('inventory_locations', function(Blueprint $t){$t->id();$t->string('name')->unique();$t->string('description')->nullable();$t->boolean('is_active')->default(true);$t->timestamps();});
  Schema::create('supply_items', function(Blueprint $t){$t->id();$t->foreignId('category_id')->constrained('supply_categories');$t->foreignId('unit_id')->constrained('units_of_measurement');$t->string('code')->unique();$t->string('name');$t->text('description')->nullable();$t->decimal('minimum_stock',14,2)->default(0);$t->string('status')->default('active')->index();$t->timestamps();});
  Schema::create('supply_batches', function(Blueprint $t){$t->id();$t->foreignId('supply_item_id')->constrained()->cascadeOnDelete();$t->foreignId('location_id')->constrained('inventory_locations');$t->string('batch_number');$t->date('received_on');$t->date('expires_on')->nullable();$t->decimal('quantity_received',14,2);$t->decimal('quantity_available',14,2);$t->string('status')->default('available')->index();$t->timestamps();$t->unique(['supply_item_id','batch_number','location_id']);$t->index(['expires_on','status']);});
  Schema::create('inventory_movements', function(Blueprint $t){$t->id();$t->string('reference')->unique();$t->foreignId('supply_batch_id')->constrained()->cascadeOnDelete();$t->foreignId('user_id')->constrained();$t->string('movement_type')->index();$t->decimal('quantity',14,2);$t->string('recipient')->nullable();$t->string('purpose')->nullable();$t->text('notes')->nullable();$t->string('status')->default('posted')->index();$t->timestamps();});
  Schema::create('inventory_alert_configurations', function(Blueprint $t){$t->id();$t->foreignId('supply_item_id')->unique()->constrained()->cascadeOnDelete();$t->decimal('minimum_quantity',14,2);$t->boolean('is_active')->default(true);$t->timestamps();});
  $now=now(); DB::table('supply_categories')->insert(array_map(fn($name)=>['name'=>$name,'created_at'=>$now,'updated_at'=>$now],['Seeds','Fertilizers','Pesticides','Planting Materials','Animal Feed','Other Agricultural Supplies']));
  DB::table('units_of_measurement')->insert([['name'=>'Kilogram','symbol'=>'kg','created_at'=>$now,'updated_at'=>$now],['name'=>'Liter','symbol'=>'L','created_at'=>$now,'updated_at'=>$now],['name'=>'Piece','symbol'=>'pc','created_at'=>$now,'updated_at'=>$now]]);
  DB::table('inventory_locations')->insert(['name'=>'Main Agricultural Warehouse','description'=>'Default AISMS storage location','is_active'=>true,'created_at'=>$now,'updated_at'=>$now]);
 }
 public function down(): void { Schema::dropIfExists('inventory_alert_configurations');Schema::dropIfExists('inventory_movements');Schema::dropIfExists('supply_batches');Schema::dropIfExists('supply_items');Schema::dropIfExists('inventory_locations');Schema::dropIfExists('units_of_measurement');Schema::dropIfExists('supply_categories'); }
};
