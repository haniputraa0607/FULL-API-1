<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddAuditableToNews extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
    	Schema::table('news_form_datas', function (Blueprint $table) {
        	$table->auditable();
        });
    
        Schema::table('news_form_data_details', function (Blueprint $table) {
        	$table->auditable();
        });
    
        Schema::table('news_form_structures', function (Blueprint $table) {
        	$table->auditable();
        });
    
        Schema::table('news_outlets', function (Blueprint $table) {
        	$table->auditable();
        });
    
        Schema::table('news_products', function (Blueprint $table) {
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
    	Schema::table('news_form_datas', function (Blueprint $table) {
        	$table->dropAuditable();
        });
    
        Schema::table('news_form_data_details', function (Blueprint $table) {
        	$table->dropAuditable();
        });
    
        Schema::table('news_form_structures', function (Blueprint $table) {
        	$table->dropAuditable();
        });
    
        Schema::table('news_outlets', function (Blueprint $table) {
        	$table->dropAuditable();
        });
    
        Schema::table('news_products', function (Blueprint $table) {
        	$table->dropAuditable();
        });
    }
}
