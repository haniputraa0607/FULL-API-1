<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterIdTransactionUniqueOnUserRatingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('user_ratings', function (Blueprint $table) {
            $table->unsignedInteger('id_transaction')->unique()->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('user_ratings', function (Blueprint $table) {
            $table->unsignedInteger('id_transaction')->change();
        });
    }
}
