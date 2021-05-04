<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTransactionVoidFailedsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('transaction_void_faileds', function (Blueprint $table) {
            $table->increments('id_transaction_void_failed');
            $table->unsignedInteger('id_transaction');
            $table->unsignedInteger('id_payment');
            $table->string('payment_type');
            $table->boolean('retry_status')->default(0);
            $table->unsignedInteger('retry_count')->default(0);
            $table->timestamps();

            $table->foreign('id_transaction')->references('id_transaction')->on('transactions')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('transaction_void_faileds');
    }
}
