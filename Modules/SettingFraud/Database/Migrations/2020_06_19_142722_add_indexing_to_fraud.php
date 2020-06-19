<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddIndexingToFraud extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users_device_login', function (Blueprint $table) {
            $table->index(['device_id', 'status']);
        });

        Schema::table('fraud_detection_log_check_promo_code', function (Blueprint $table) {
            $table->index('status');
            $table->index('created_at');
        });

        Schema::table('fraud_detection_log_device', function (Blueprint $table) {
            $table->index('device_id');
            $table->index('created_at');
        });

        Schema::table('fraud_detection_log_referral', function (Blueprint $table) {
            $table->index('created_at');
            $table->foreign('id_user', 'fk_fraud_detection_log_referral_users')->references('id')->on('users')->onUpdate('CASCADE')->onDelete('CASCADE');
        });

        Schema::table('fraud_detection_log_referral_users', function (Blueprint $table) {
            $table->index('created_at');
            $table->foreign('id_user', 'fk_fraud_detection_log_referral_users_users')->references('id')->on('users')->onUpdate('CASCADE')->onDelete('CASCADE');
        });

        Schema::table('fraud_detection_log_transaction_day', function (Blueprint $table) {
            $table->index(['fraud_detection_date']);
            $table->index('status');
            $table->index('created_at');
        });

        Schema::table('fraud_detection_log_transaction_in_between', function (Blueprint $table) {
            $table->index('status');
            $table->index('created_at');
        });

        Schema::table('fraud_detection_log_transaction_point', function (Blueprint $table) {
            $table->index('status');
            $table->index('created_at');
        });

        Schema::table('fraud_detection_log_transaction_week', function (Blueprint $table) {
            $table->index(['fraud_detection_year', 'fraud_detection_week'], 'index_week_year');
            $table->index('status');
            $table->index('created_at');
        });

        Schema::table('fraud_settings', function (Blueprint $table) {
            $table->index('parameter');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users_device_login', function (Blueprint $table) {
            $table->dropIndex(['device_id', 'status']);
        });

        Schema::table('fraud_detection_log_check_promo_code', function (Blueprint $table) {
            $table->dropIndex('status');
            $table->dropIndex('created_at');
        });

        Schema::table('fraud_detection_log_device', function (Blueprint $table) {
            $table->dropIndex('device_id');
            $table->dropIndex('created_at');
        });

        Schema::table('fraud_detection_log_referral', function (Blueprint $table) {
            $table->dropIndex('created_at');
        });

        Schema::table('fraud_detection_log_referral_users', function (Blueprint $table) {
            $table->dropIndex('created_at');
        });

        Schema::table('fraud_detection_log_transaction_day', function (Blueprint $table) {
            $table->dropIndex(['fraud_detection_date', 'status']);
            $table->dropIndex('created_at');
        });

        Schema::table('fraud_detection_log_transaction_in_between', function (Blueprint $table) {
            $table->dropIndex('status');
            $table->dropIndex('created_at');
        });

        Schema::table('fraud_detection_log_transaction_point', function (Blueprint $table) {
            $table->dropIndex('status');
            $table->dropIndex('created_at');
        });

        Schema::table('fraud_detection_log_transaction_week', function (Blueprint $table) {
            $table->dropIndex('index_week_year');
            $table->dropIndex('status');
        });

        Schema::table('fraud_settings', function (Blueprint $table) {
            $table->dropIndex('parameter');
        });
    }
}
