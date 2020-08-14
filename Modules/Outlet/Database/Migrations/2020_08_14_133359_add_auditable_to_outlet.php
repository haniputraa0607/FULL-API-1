<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddAuditableToOutlet extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('outlets', function (Blueprint $table) {
        	$table->auditable();
        });
    
        Schema::table('outlet_ovos', function (Blueprint $table) {
        	$table->auditable();
        });
    
        Schema::table('outlet_holidays', function (Blueprint $table) {
        	$table->auditable();
        });
    
        Schema::table('outlet_photos', function (Blueprint $table) {
        	$table->auditable();
        });
    
        Schema::table('outlet_schedules', function (Blueprint $table) {
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
        Schema::table('outlets', function (Blueprint $table) {
        	$table->dropAuditable();
        });
    
        Schema::table('outlet_ovos', function (Blueprint $table) {
        	$table->dropAuditable();
        });
    
        Schema::table('outlet_holidays', function (Blueprint $table) {
        	$table->dropAuditable();
        });
    
        Schema::table('outlet_photos', function (Blueprint $table) {
        	$table->dropAuditable();
        });
    
        Schema::table('outlet_schedules', function (Blueprint $table) {
        	$table->dropAuditable();
        });
    }
}
