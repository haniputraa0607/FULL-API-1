<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateDealsPaymentCimbsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('deals_payment_cimbs', function (Blueprint $table) {
            $table->bigIncrements('id_deals_payment_cimb');
            $table->unsignedInteger('id_deals');
            $table->unsignedInteger('id_deals_user');
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

            $table->foreign('id_deals', 'fk_deals_payment_cimbs_deals')->references('id_deals')->on('deals')->onUpdate('CASCADE')->onDelete('CASCADE');
            $table->foreign('id_deals_user', 'fk_deals_payment_cimbs_deals_users')->references('id_deals_user')->on('deals_users')->onUpdate('CASCADE')->onDelete('CASCADE');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('deals_payment_cimbs');
    }
}
