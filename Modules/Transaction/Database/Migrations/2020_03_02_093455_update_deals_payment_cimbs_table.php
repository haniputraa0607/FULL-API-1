<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateDealsPaymentCimbsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('deals_payment_cimbs', function (Blueprint $table) {
            $table->unsignedInteger('id_deals')->nullable()->change();
            $table->unsignedInteger('id_deals_user')->nullable()->change();
            $table->bigInteger('transaction_id')->nullable()->change();
            $table->bigInteger('amount')->after('transaction_id');
            $table->string('txn_status')->nullable()->change();
            $table->string('txn_signature')->nullable()->change();
            $table->text('secure_signature')->nullable()->change();
            $table->dateTime('tran_date')->nullable()->change();
            $table->integer('response_code')->nullable()->change();
            $table->string('response_desc')->nullable()->change();
            $table->integer('auth_id')->nullable()->change();
            $table->integer('fr_level')->nullable()->change();
            $table->dateTime('sales_date')->nullable()->change();
            $table->float('fr_score')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('deals_payment_cimbs', function (Blueprint $table) {
        });
    }
}
