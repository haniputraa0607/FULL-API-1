<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateDealsUserLimitsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('deals_user_limits', function (Blueprint $table) {
            $table->increments('id_deals_user_limit');
			$table->integer('id_user')->unsigned()->index('fk_deals_user_limits_users');
			$table->integer('id_deals')->unsigned()->index('fk_deals_user_limits_deals');
			$table->timestamps();

			$table->foreign('id_user', 'fk_deals_user_limits_users')->references('id')->on('users')->onUpdate('CASCADE')->onDelete('CASCADE');
			$table->foreign('id_deals', 'fk_deals_user_limits_deals')->references('id_deals')->on('deals')->onUpdate('CASCADE')->onDelete('CASCADE');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('deals_user_limits');
    }
}
