<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateLogNobuAndTransactionPaymentNobuTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('transaction_payment_nobu', function(Blueprint $table)
		{
			$table->increments('id_transaction_payment_nobu');
			$table->integer('id_transaction')->unsigned()->index('fk_transaction_payments_nobu_transactions');
			$table->string('bank', 191)->nullable();
			$table->string('transaction_time', 191)->nullable();
			$table->string('gross_amount', 191);
			$table->string('order_id', 191);
			$table->string('payment_type', 191)->nullable();
			$table->string('signature_key', 191)->nullable();
			$table->string('status_code', 191)->nullable();
			$table->string('vt_transaction_id', 191)->nullable();
			$table->string('transaction_status', 191)->nullable();
			$table->string('fraud_status', 191)->nullable();
			$table->string('status_message', 191)->nullable();
			$table->timestamps();
		});

        Schema::create('log_nobu', function (Blueprint $table) {
            $table->bigIncrements('id_log_nobu');
            $table->string('type')->nullable();
            $table->string('id_reference')->nullable();
            $table->text('request')->nullable();
            $table->text('request_header')->nullable();
            $table->text('request_url')->nullable();
            $table->text('response')->nullable();
            $table->string('response_status_code')->nullable();
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
        Schema::dropIfExists('transaction_payment_nobu');
        Schema::dropIfExists('log_nobu');
    }
}
