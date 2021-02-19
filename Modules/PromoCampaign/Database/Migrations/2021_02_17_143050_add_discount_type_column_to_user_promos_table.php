<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddDiscountTypeColumnToUserPromosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('user_promos', function (Blueprint $table) {
        	$table->enum('discount_type', ['discount', 'delivery'])->after('id_reference')->default('discount');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('user_promos', function (Blueprint $table) {
        	$table->dropColumn('discount_type');
        });
    }
}
