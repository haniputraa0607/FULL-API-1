<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateNotificationExpiryPointSentUsers extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('notification_expiry_point_sent_users', function (Blueprint $table) {
            $table->bigIncrements('id_notification_expiry_point_sent_user');
            $table->unsignedInteger('id_notification_expiry_point_sent');
            $table->unsignedInteger('id_user');
            $table->integer('total_expired_point');
            $table->date('expired_date');
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
        Schema::dropIfExists('notification_expiry_point_sent_users');
    }
}
