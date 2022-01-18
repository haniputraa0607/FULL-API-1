<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateAdjustmentPointUser extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('adjustment_point_users', function (Blueprint $table) {
            $table->bigIncrements('id_adjustment_point_user');
            $table->unsignedInteger('id_user');
            $table->integer('point_adjust');
            $table->mediumText('reason');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('adjustment_point_users');
    }
}
