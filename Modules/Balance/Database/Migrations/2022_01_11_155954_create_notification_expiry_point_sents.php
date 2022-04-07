<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateNotificationExpiryPointSents extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('notification_expiry_point_sents', function (Blueprint $table) {
            $table->bigIncrements('id_notification_expiry_point_sent');
            $table->dateTime('notification_expiry_point_date_sent');
            $table->integer('total_customer')->default(0)->nullable();
            $table->integer('email_count_sent')->default(0)->nullable();
            $table->integer('sms_count_sent')->default(0)->nullable();
            $table->integer('push_count_sent')->default(0)->nullable();
            $table->integer('inbox_count_sent')->default(0)->nullable();
            $table->integer('whatsapp_count_sent')->default(0)->nullable();
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
        Schema::dropIfExists('notification_expiry_point_sents');
    }
}
