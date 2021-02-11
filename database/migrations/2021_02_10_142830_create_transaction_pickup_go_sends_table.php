<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateTransactionPickupGoSendsTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::drop('transaction_pickup_go_sends');
		Schema::create('transaction_pickup_go_sends', function(Blueprint $table)
		{
			$table->increments('id_transaction_pickup_go_send');
			$table->integer('id_transaction_pickup')->unsigned()->index('fk_transaction_pickup_go_sends_transaction_pickups');
			$table->string('origin_name', 191);
			$table->string('origin_phone', 191);
			$table->text('origin_address', 16777215);
			$table->string('origin_note', 191)->nullable();
			$table->string('origin_latitude', 191);
			$table->string('origin_longitude', 191);
			$table->string('destination_name', 191);
			$table->string('destination_phone', 191);
			$table->text('destination_address', 16777215);
			$table->string('destination_short_address', 191)->nullable();
			$table->string('destination_address_name', 191)->nullable();
			$table->string('destination_note', 191)->nullable();
			$table->string('destination_latitude', 191);
			$table->string('destination_longitude', 191);
			$table->integer('go_send_id')->nullable();
			$table->string('go_send_order_no', 191)->nullable();
			$table->string('latest_status', 191)->nullable();
			$table->string('cancel_reason', 191)->nullable();
			$table->string('live_tracking_url', 191)->nullable();
			$table->string('driver_id', 191)->nullable();
			$table->string('driver_name', 191)->nullable();
			$table->string('driver_phone', 191)->nullable();
			$table->string('driver_photo', 191)->nullable();
			$table->string('vehicle_number', 191)->nullable();
			$table->string('receiver_name', 191)->nullable();
			$table->integer('retry_count')->unsigned()->default(0);
			$table->dateTime('stop_booking_at')->nullable();
			$table->string('manual_order_no', 191)->nullable();
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
		Schema::drop('transaction_pickup_go_sends');
	}

}
