<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddPromoDeliveryColumnToTransactionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('transactions', function (Blueprint $table) {
        	$table->double('transaction_discount_delivery', 10, 2)->after('transaction_discount');
        	$table->unsignedInteger('id_promo_campaign_promo_code_delivery')->nullable()->after('id_promo_campaign_promo_code');
            $table->foreign('id_promo_campaign_promo_code_delivery', 'fk_id_promo_code_delivery_transactions_promo_campaigns')->references('id_promo_campaign_promo_code')->on('promo_campaign_promo_codes')->onUpdate('CASCADE')->onDelete('CASCADE');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('transactions', function (Blueprint $table) {
        	$table->dropColumn('transaction_discount_delivery');
        	$table->dropForeign('fk_id_promo_code_delivery_transactions_promo_campaigns');
            $table->dropColumn('id_promo_campaign_promo_code_delivery');
        });
    }
}
