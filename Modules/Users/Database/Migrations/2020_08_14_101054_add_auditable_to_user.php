<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddAuditableToUser extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
			$table->auditable();
        });
    
        Schema::table('user_features', function (Blueprint $table) {
			$table->auditable();
        });
    
        Schema::table('dashboard_users', function (Blueprint $table) {
			$table->auditable();
        });
    
        Schema::table('user_inboxes', function (Blueprint $table) {
			$table->auditable();
        });
    
        Schema::table('user_outlets', function (Blueprint $table) {
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
        Schema::table('users', function (Blueprint $table) {
        	$table->dropAuditable();
        });
    
        Schema::table('user_features', function (Blueprint $table) {
        	$table->dropAuditable();
        });
    
        Schema::table('dashboard_users', function (Blueprint $table) {
        	$table->dropAuditable();
        });
    
        Schema::table('user_inboxes', function (Blueprint $table) {
        	$table->dropAuditable();
        });
    
        Schema::table('user_outlets', function (Blueprint $table) {
        	$table->dropAuditable();
        });
    }
}
