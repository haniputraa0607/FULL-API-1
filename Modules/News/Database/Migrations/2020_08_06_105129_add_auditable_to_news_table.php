<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddAuditableToNewsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('news', function (Blueprint $table) {
        	$table->unsignedInteger('created_by')->nullable();
            $table->unsignedInteger('updated_by')->nullable();
        });
        Schema::table('news_categories', function (Blueprint $table) {
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
        Schema::table('news', function (Blueprint $table) {
        	$table->dropColumn('created_by');
        	$table->dropColumn('updated_by');
        });
        Schema::table('news_categories', function (Blueprint $table) {
        	$table->dropColumn('created_by');
        	$table->dropColumn('updated_by');
        });
    }
}
