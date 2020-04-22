<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateColumnIdUserInTransactionProductModifiers extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('transaction_product_modifiers', function (Blueprint $table) {
            $table->integer('id_user')->unsigned()->nullable(true)->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('transaction_product_modifiers', function (Blueprint $table) {
            $table->integer('id_user')->unsigned()->nullable(false)->change();
        });
    }
}
