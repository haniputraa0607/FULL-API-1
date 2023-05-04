<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddSomeColumnToTransactionPaymentNobuTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('transaction_payment_nobu', function (Blueprint $table) {
			$table->string('payment_status', 191)->after('payment_type')->nullable();
			$table->string('payment_reference_no', 191)->after('order_id')->nullable();
			$table->string('payment_date', 191)->after('payment_reference_no')->nullable();
			$table->string('id_issuer', 191)->after('payment_date')->nullable();
			$table->string('retrieval_reference_no', 191)->after('id_issuer')->nullable();
			$table->string('no_transaction_reference', 191)->after('no_transaction')->nullable();
            
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('transaction_payment_nobu', function (Blueprint $table) {
            $table->dropColumn('payment_status');
            $table->dropColumn('payment_reference_no');
            $table->dropColumn('payment_date');
            $table->dropColumn('id_issuer');
            $table->dropColumn('retrieval_reference_no');
        });
    }
}
