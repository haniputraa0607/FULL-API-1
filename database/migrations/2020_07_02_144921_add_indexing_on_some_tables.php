<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddIndexingOnSomeTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('brands', function (Blueprint $table) {
            $table->index('code_brand');
        });

        Schema::table('configs', function (Blueprint $table) {
            $table->index('config_name');
        });

        Schema::table('deals_payment_midtrans', function (Blueprint $table) {
            $table->foreign('id_deals_user', 'fk_deals_payment_midtrans_deals_users')->references('id_deals_user')->on('deals_users')->onUpdate('CASCADE');
        });

        Schema::table('deals_vouchers', function (Blueprint $table) {
            $table->index('voucher_code');
            $table->foreign('id_deals_subscription', 'fk_deals_payment_midtrans_deals_subscriptions')->references('id_deals_subscription')->on('deals_subscriptions')->onUpdate('CASCADE');
        });

        Schema::table('log_api_sms', function (Blueprint $table) {
            $table->index('phone');
            $table->index('created_at');
        });

        Schema::table('outlet_product_modifier_price_periodes', function (Blueprint $table) {
            $table->index('id_outlet');
            $table->index('start_date');
            $table->index('end_date');
        });

        Schema::table('outlet_product_price_periodes', function (Blueprint $table) {
            $table->index('id_outlet');
            $table->index('start_date');
            $table->index('end_date');
        });

        Schema::table('settings', function (Blueprint $table) {
            $table->index('key');
        });

        Schema::table('user_outlets', function (Blueprint $table) {
            $table->index('id_outlet');
        });

        Schema::connection('mysql2')->table('daily_check_promo_code', function (Blueprint $table) {
            $table->index('id_user');
        });

        Schema::connection('mysql2')->table('daily_transactions', function (Blueprint $table) {
            $table->index('transaction_date');
            $table->index('id_user');
        });

        Schema::connection('mysql2')->table('log_activities_apps', function (Blueprint $table) {
            $table->index('phone');
            $table->index('created_at');
        });

        Schema::connection('mysql2')->table('log_activities_be', function (Blueprint $table) {
            $table->index('phone');
            $table->index('created_at');
        });
    }

}
