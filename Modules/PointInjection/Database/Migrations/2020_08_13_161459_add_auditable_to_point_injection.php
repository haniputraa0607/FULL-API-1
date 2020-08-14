<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddAuditableToPointInjection extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('pivot_point_injections', function (Blueprint $table) {
			$table->auditable();
        });

        Schema::table('point_injections', function (Blueprint $table) {
			$table->unsignedBigInteger('updated_by')->nullable()->index();
        });
    
        Schema::table('point_injection_rules', function (Blueprint $table) {
			$table->auditable();
        });
    
        Schema::table('point_injection_rule_parents', function (Blueprint $table) {
			$table->auditable();
        });

        Schema::table('point_injection_users', function (Blueprint $table) {
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
        Schema::table('pivot_point_injections', function (Blueprint $table) {
        	$table->dropAuditable();
        });

        Schema::table('point_injections', function (Blueprint $table) {
        	$table->dropColumn('updated_by');
        });
    
        Schema::table('point_injection_rules', function (Blueprint $table) {
        	$table->dropAuditable();
        });
    
        Schema::table('point_injection_rule_parents', function (Blueprint $table) {
        	$table->dropAuditable();
        });
        
        Schema::table('point_injection_users', function (Blueprint $table) {
        	$table->dropAuditable();
        });
    }
}
