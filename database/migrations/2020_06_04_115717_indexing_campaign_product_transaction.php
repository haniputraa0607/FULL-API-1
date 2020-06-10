<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class IndexingCampaignProductTransaction extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->index('campaign_send_at');
            $table->index('campaign_is_sent');
            $table->index('campaign_complete');
        });

        Schema::table('favorites', function (Blueprint $table) {
            $table->string('notes')->change();
            $table->index('notes');
        });

        Schema::table('inbox_globals', function (Blueprint $table) {
            $table->index('inbox_global_start');
            $table->index('inbox_global_end');
            $table->index('created_at');
        });

        Schema::table('product_discounts', function (Blueprint $table) {
            $table->index('discount_start');
            $table->index('discount_end');
            $table->index('discount_time_start');
            $table->index('discount_time_end');
            $table->index('discount_days');
        });

        Schema::table('product_modifier_prices', function (Blueprint $table) {
            $table->index('product_modifier_visibility');
            $table->index('product_modifier_status');
            $table->index('product_modifier_stock_status');
        });

        Schema::table('product_modifiers', function (Blueprint $table) {
            $table->index('product_modifier_visibility');
        });

        Schema::table('product_prices', function (Blueprint $table) {
            $table->index('product_visibility');
            $table->index('product_status');
            $table->index('product_stock_status');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->index('product_code');
            $table->index('product_visibility');
            $table->index('product_status');
        });

        Schema::table('transaction_balances', function (Blueprint $table) {
            $table->index('approval_code');
        });

        Schema::table('transaction_duplicates', function (Blueprint $table) {
            $table->index('transaction_receipt_number');
        });

        Schema::table('transaction_multiple_payments', function (Blueprint $table) {
            $table->index('type');
        });

        Schema::table('transaction_payment_cimbs', function (Blueprint $table) {
            $table->index('merchant_tranid');
        });

        Schema::table('transaction_payment_midtrans', function (Blueprint $table) {
            $table->index('order_id');
        });

        Schema::table('transaction_payment_ovos', function (Blueprint $table) {
            $table->index('push_to_pay_at');
            $table->index('reversal');
        });

        Schema::table('transaction_pickups', function (Blueprint $table) {
            $table->index('order_id');
            $table->index('pickup_by');
            $table->index('receive_at');
            $table->index('ready_at');
            $table->index('taken_at');
            $table->index('taken_by_system_at');
            $table->index('reject_at');
        });

        Schema::table('transaction_products', function (Blueprint $table) {
            $table->index('id_brand');
            $table->index('id_outlet');
            $table->index('id_user');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->index('show_rate_popup');
            $table->index('transaction_date');
            $table->index('transaction_receipt_number');
            $table->index('trasaction_type');
            $table->index('trasaction_payment_type');
            $table->index('transaction_payment_status');
            $table->index('void_date');
            $table->index('id_deals_voucher');
        });

        Schema::table('transaction_shipments', function (Blueprint $table) {
            $table->index('order_id');
        });

        Schema::table('transactions_online_pos', function (Blueprint $table) {
            $table->index('id_transaction');
        });

        Schema::table('transaction_vouchers', function (Blueprint $table) {
            $table->index('status');
        });

        Schema::table('user_inboxes', function (Blueprint $table) {
            $table->index('inboxes_send_at');
        });

        Schema::table('user_ratings', function (Blueprint $table) {
            $table->index('id_user');
            $table->index('id_transaction');
            $table->index('rating_value');
        });

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropIndex('campaign_send_at');
            $table->dropIndex('campaign_is_sent');
            $table->dropIndex('campaign_complete');
        });

        Schema::table('favorites', function (Blueprint $table) {
            $table->dropIndex('notes');
        });

        Schema::table('inbox_globals', function (Blueprint $table) {
            $table->dropIndex('inbox_global_start');
            $table->dropIndex('inbox_global_end');
            $table->dropIndex('created_at');
        });

        Schema::table('product_discounts', function (Blueprint $table) {
            $table->dropIndex('discount_start');
            $table->dropIndex('discount_end');
            $table->dropIndex('discount_time_start');
            $table->dropIndex('discount_time_end');
            $table->dropIndex('discount_days');
        });

        Schema::table('product_modifier_prices', function (Blueprint $table) {
            $table->dropIndex('product_modifier_visibility');
            $table->dropIndex('product_modifier_status');
            $table->dropIndex('product_modifier_stock_status');
        });

        Schema::table('product_modifiers', function (Blueprint $table) {
            $table->dropIndex('product_modifier_visibility');
        });

        Schema::table('product_prices', function (Blueprint $table) {
            $table->dropIndex('product_visibility');
            $table->dropIndex('product_status');
            $table->dropIndex('product_stock_status');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('product_code');
            $table->dropIndex('product_visibility');
            $table->dropIndex('product_status');
        });

        Schema::table('transaction_balances', function (Blueprint $table) {
            $table->dropIndex('approval_code');
        });

        Schema::table('transaction_duplicates', function (Blueprint $table) {
            $table->dropIndex('transaction_receipt_number');
        });

        Schema::table('transaction_multiple_payments', function (Blueprint $table) {
            $table->dropIndex('type');
        });

        Schema::table('transaction_payment_cimbs', function (Blueprint $table) {
            $table->dropIndex('merchant_tranid');
        });

        Schema::table('transaction_payment_midtrans', function (Blueprint $table) {
            $table->dropIndex('order_id');
        });

        Schema::table('transaction_payment_ovos', function (Blueprint $table) {
            $table->dropIndex('push_to_pay_at');
            $table->dropIndex('reversal');
        });

        Schema::table('transaction_pickups', function (Blueprint $table) {
            $table->dropIndex('order_id');
            $table->dropIndex('pickup_by');
            $table->dropIndex('receive_at');
            $table->dropIndex('ready_at');
            $table->dropIndex('taken_at');
            $table->dropIndex('taken_by_system_at');
            $table->dropIndex('reject_at');
        });

        Schema::table('transaction_products', function (Blueprint $table) {
            $table->dropIndex('id_brand');
            $table->dropIndex('id_outlet');
            $table->dropIndex('id_user');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex('show_rate_popup');
            $table->dropIndex('transaction_date');
            $table->dropIndex('transaction_receipt_number');
            $table->dropIndex('trasaction_type');
            $table->dropIndex('trasaction_payment_type');
            $table->dropIndex('transaction_payment_status');
            $table->dropIndex('void_date');
            $table->dropIndex('id_deals_voucher');
        });

        Schema::table('transaction_shipments', function (Blueprint $table) {
            $table->dropIndex('order_id');
        });

        Schema::table('transactions_online_pos', function (Blueprint $table) {
            $table->dropIndex('id_transaction');
        });

        Schema::table('transaction_vouchers', function (Blueprint $table) {
            $table->dropIndex('status');
        });

        Schema::table('user_inboxes', function (Blueprint $table) {
            $table->dropIndex('inboxes_send_at');
        });

        Schema::table('user_ratings', function (Blueprint $table) {
            $table->dropIndex('id_user');
            $table->dropIndex('id_transaction');
            $table->dropIndex('rating_value');
        });
    }
}
