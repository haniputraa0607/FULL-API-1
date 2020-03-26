<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateOutletOvoTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('outlet_ovos', function (Blueprint $table) {
            $table->unsignedInteger('id_outlet');
            $table->string('store_code');
            $table->string('tid')->nullable();
            $table->string('mid')->nullable();
            $table->timestamps();

            $table->foreign('id_outlet', 'fk_outlet_ovo_outlet')->references('id_outlet')->on('outlets')->onUpdate('CASCADE')->onDelete('CASCADE')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('outlet_ovo');
    }
}
