<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddIdNotificationExpiryPointSentUserToCrmLog extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('autocrm_email_logs', function (Blueprint $table) {
            $table->unsignedInteger('id_notification_expiry_point_sent_user')->nullable()->after('id_user');
        });
        Schema::table('autocrm_push_logs', function (Blueprint $table) {
            $table->unsignedInteger('id_notification_expiry_point_sent_user')->nullable()->after('id_user');
        });
        Schema::table('autocrm_sms_logs', function (Blueprint $table) {
            $table->unsignedInteger('id_notification_expiry_point_sent_user')->nullable()->after('id_user');
        });
        Schema::table('autocrm_whatsapp_logs', function (Blueprint $table) {
            $table->unsignedInteger('id_notification_expiry_point_sent_user')->nullable()->after('id_user');
        });
        Schema::table('user_inboxes', function (Blueprint $table) {
            $table->unsignedInteger('id_notification_expiry_point_sent_user')->nullable()->after('id_user');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('autocrm_email_logs', function (Blueprint $table) {
            $table->dropColumn('id_notification_expiry_point_sent_user');
        });
        Schema::table('autocrm_push_logs', function (Blueprint $table) {
            $table->dropColumn('id_notification_expiry_point_sent_user');
        });
        Schema::table('autocrm_sms_logs', function (Blueprint $table) {
            $table->dropColumn('id_notification_expiry_point_sent_user');
        });
        Schema::table('autocrm_whatsapp_logs', function (Blueprint $table) {
            $table->dropColumn('id_notification_expiry_point_sent_user');
        });
        Schema::table('user_inboxes', function (Blueprint $table) {
            $table->dropColumn('id_notification_expiry_point_sent_user');
        });
    }
}
