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
        	$table->auditable();
        });
        Schema::table('news_categories', function (Blueprint $table) {
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
        Schema::table('news', function (Blueprint $table) {
        	$table->dropAuditable();
        });
        Schema::table('news_categories', function (Blueprint $table) {
        	$table->dropAuditable();
        });
    }
}
