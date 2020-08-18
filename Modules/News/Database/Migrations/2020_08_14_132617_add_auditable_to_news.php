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
        	$table->unsignedInteger('created_by')->nullable();
            $table->unsignedInteger('updated_by')->nullable();
        });
    
        Schema::table('news_form_data_details', function (Blueprint $table) {
        	$table->unsignedInteger('created_by')->nullable();
            $table->unsignedInteger('updated_by')->nullable();
        });
    
        Schema::table('news_form_structures', function (Blueprint $table) {
        	$table->unsignedInteger('created_by')->nullable();
            $table->unsignedInteger('updated_by')->nullable();
        });
    
        Schema::table('news_outlets', function (Blueprint $table) {
        	$table->unsignedInteger('created_by')->nullable();
            $table->unsignedInteger('updated_by')->nullable();
        });
    
        Schema::table('news_products', function (Blueprint $table) {
        	$table->unsignedInteger('created_by')->nullable();
            $table->unsignedInteger('updated_by')->nullable();
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
        	$table->dropColumn('created_by');
        	$table->dropColumn('updated_by');
        });
    
        Schema::table('news_form_data_details', function (Blueprint $table) {
        	$table->dropColumn('created_by');
        	$table->dropColumn('updated_by');
        });
    
        Schema::table('news_form_structures', function (Blueprint $table) {
        	$table->dropColumn('created_by');
        	$table->dropColumn('updated_by');
        });
    
        Schema::table('news_outlets', function (Blueprint $table) {
        	$table->dropColumn('created_by');
        	$table->dropColumn('updated_by');
        });
    
        Schema::table('news_products', function (Blueprint $table) {
        	$table->dropColumn('created_by');
        	$table->dropColumn('updated_by');
        });
    }
}
