<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTransactionPaymentCimbsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('transaction_payment_cimbs', function (Blueprint $table) {
            $table->bigIncrements('id_transaction_payment_cimb');
            $table->unsignedInteger('id_transaction');
            $table->bigInteger('transaction_id');
            $table->char('txn_status');
            $table->string('txn_signature');
            $table->text('secure_signature');
            $table->dateTime('tran_date');
            $table->string('merchant_tranid');
            $table->integer('response_code');
            $table->string('response_desc');
            $table->integer('auth_id');
            $table->integer('fr_level');
            $table->dateTime('sales_date');
            $table->float('fr_score');
            $table->timestamps();

            $table->foreign('id_transaction', 'fk_transaction_payment_cimbs_transactions')->references('id_transaction')->on('transactions')->onUpdate('CASCADE')->onDelete('CASCADE');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('transaction_payment_cimbs');
    }
}
