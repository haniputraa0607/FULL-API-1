<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateTransactionPickupGoSendUpdatesTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('transaction_pickup_go_send_updates', function(Blueprint $table)
		{
			$table->bigInteger('id_transaction_pickup_go_send_update', true)->unsigned();
			$table->integer('id_transaction')->unsigned()->index('fk_id_trx_trx_pickup_go_send');
			$table->integer('id_transaction_pickup_go_send')->unsigned()->index('fk_id_trx_pickup_gosend_trx_pickup_go_send');
			$table->string('go_send_order_no', 191)->nullable();
			$table->string('status', 191);
			$table->string('description', 191)->nullable();
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
		Schema::drop('transaction_pickup_go_send_updates');
	}

}
