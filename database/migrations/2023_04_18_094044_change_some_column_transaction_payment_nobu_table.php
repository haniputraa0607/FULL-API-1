<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ChangeSomeColumnTransactionPaymentNobuTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('transaction_payment_nobu', function (Blueprint $table) {
			$table->string('no_transaction', 191)->after('bank')->nullable();
			$table->text('qris_data')->after('no_transaction')->nullable();
            $table->dropColumn('bank');
			$table->dropColumn('signature_key');
            $table->dropColumn('vt_transaction_id');
			$table->dropColumn('fraud_status');
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
            $table->dropColumn('no_transaction');
			$table->dropColumn('qris_data');
			$table->string('bank', 191)->after('id_transaction')->nullable();
			$table->string('signature_key', 191)->after('payment_type')->nullable();
			$table->string('vt_transaction_id', 191)->after('status_code')->nullable();
			$table->string('fraud_status', 191)->after('transaction_status')->nullable();
        });
    }
}
