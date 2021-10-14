<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateProductModifierPricePeriodesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::connection('mysql')->hasTable('outlet_product_modifier_price_periodes')) {
            Schema::connection('mysql')->create('outlet_product_modifier_price_periodes', function ($table) {
                $table->bigIncrements('id_product_modifier_price_periode');
                $table->unsignedInteger('id_product_modifier');
                $table->unsignedInteger('id_outlet');
                $table->float('price', 10, 2)->nullable();
                $table->dateTime('start_date')->nullable();
                $table->dateTime('end_date')->nullable();
                $table->timestamps();
                $table->index(['id_product_modifier', 'id_outlet', 'start_date', 'end_date'], 'index_modifier_price');
            });
        }
        if (!Schema::connection('mysql')->hasTable('outlet_product_modifier_price_periode_temps')) {
            Schema::connection('mysql')->create('outlet_product_modifier_price_periode_temps', function ($table) {
                $table->bigIncrements('id_product_modifier_price_periode');
                $table->unsignedInteger('id_product_modifier');
                $table->unsignedInteger('id_outlet');
                $table->float('price', 10, 2)->nullable();
                $table->dateTime('start_date')->nullable();
                $table->dateTime('end_date')->nullable();
                $table->timestamp('created_at');
                $table->index(['id_product_modifier', 'id_outlet'], 'index_modifier_price_temp');
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('outlet_product_modifier_price_periode_temps');
        Schema::dropIfExists('outlet_product_modifier_price_periodes');
    }
}
