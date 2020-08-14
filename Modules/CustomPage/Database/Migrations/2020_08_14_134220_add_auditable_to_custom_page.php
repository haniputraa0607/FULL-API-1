<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddAuditableToCustomPage extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('custom_pages', function (Blueprint $table) {
        	$table->auditable();
        });
    
        Schema::table('custom_page_images', function (Blueprint $table) {
        	$table->auditable();
        });
    
        Schema::table('custom_page_outlets', function (Blueprint $table) {
        	$table->auditable();
        });
    
        Schema::table('custom_page_products', function (Blueprint $table) {
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
        Schema::table('custom_pages', function (Blueprint $table) {
        	$table->dropAuditable();
        });
    
        Schema::table('custom_page_images', function (Blueprint $table) {
        	$table->dropAuditable();
        });
    
        Schema::table('custom_page_outlets', function (Blueprint $table) {
        	$table->dropAuditable();
        });
    
        Schema::table('custom_page_products', function (Blueprint $table) {
        	$table->dropAuditable();
        });
    }
}
