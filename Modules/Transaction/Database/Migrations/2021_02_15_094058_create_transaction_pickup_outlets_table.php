<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTransactionPickupOutletsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('transaction_pickup_outlets', function (Blueprint $table) {
            $table->increments('id_transaction_pickup_outlet');
            $table->unsignedInteger('id_transaction_pickup');
            $table->unsignedInteger('id_transaction');
            $table->unsignedInteger('id_user_address')->nullable();
            $table->string('destination_address')->nullable();
            $table->string('destination_address_name')->nullable();
            $table->string('destination_short_address')->nullable();
            $table->string('destination_note')->nullable();
            $table->float('destination_latitude', 11, 8)->nullable();
            $table->float('destination_longitude', 11, 8)->nullable();
            $table->float('distance')->nullable();
            $table->timestamps();

            $table->foreign('id_transaction','fk_id_trx_trx_pickup_outlets')->references('id_transaction')->on('transactions')->onDelete('CASCADE');
            $table->foreign('id_transaction_pickup')->references('id_transaction_pickup')->on('transaction_pickups')->onUpdate('CASCADE')->onDelete('CASCADE');
        });

        DB::statement("ALTER TABLE `transaction_pickups` CHANGE COLUMN `pickup_by` `pickup_by` ENUM('Customer', 'GO-SEND', 'Outlet') NOT NULL DEFAULT 'Customer'");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("ALTER TABLE `transaction_pickups` CHANGE COLUMN `pickup_by` `pickup_by` ENUM('Customer', 'GO-SEND') NOT NULL DEFAULT 'Customer'");
        Schema::dropIfExists('transaction_pickup_outlets');
    }
}
