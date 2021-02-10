<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class AddForeignKeysToTransactionPickupGoSendUpdatesTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::table('transaction_pickup_go_send_updates', function(Blueprint $table)
		{
			$table->foreign('id_transaction_pickup_go_send', 'fk_id_trx_pickup_gosend_trx_pickup_go_send')->references('id_transaction_pickup_go_send')->on('transaction_pickup_go_sends')->onUpdate('RESTRICT')->onDelete('CASCADE');
			$table->foreign('id_transaction', 'fk_id_trx_trx_pickup_go_send')->references('id_transaction')->on('transactions')->onUpdate('RESTRICT')->onDelete('CASCADE');
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::table('transaction_pickup_go_send_updates', function(Blueprint $table)
		{
			$table->dropForeign('fk_id_trx_pickup_gosend_trx_pickup_go_send');
			$table->dropForeign('fk_id_trx_trx_pickup_go_send');
		});
	}

}
