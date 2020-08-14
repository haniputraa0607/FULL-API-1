<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddAuditableToBrand extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('brands', function (Blueprint $table) {
			$table->auditable();
        });
    
        Schema::table('brand_outlet', function (Blueprint $table) {
			$table->auditable();
        });
    
        Schema::table('brand_product', function (Blueprint $table) {
			$table->auditable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('brands', function (Blueprint $table) {
        	$table->dropAuditable();
        });
    
        Schema::table('brand_outlet', function (Blueprint $table) {
        	$table->dropAuditable();
        });
    
        Schema::table('brand_product', function (Blueprint $table) {
        	$table->dropAuditable();
        });
    }
}
