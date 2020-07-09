<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\Http\Models\UsersDeviceLogin;
use Modules\SettingFraud\Entities\FraudDetectionLogDevice;
use Modules\SettingFraud\Entities\FraudDetectionLogTransactionDay;
use Modules\SettingFraud\Entities\FraudDetectionLogTransactionWeek;

class AddForeigKeyOnDelete extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        UsersDeviceLogin::leftJoin('users', 'users.id', 'users_device_login.id_user')->whereNull('users.id')->delete();
        FraudDetectionLogDevice::leftJoin('users', 'users.id', 'fraud_detection_log_device.id_user')->whereNull('users.id')->delete();
        FraudDetectionLogTransactionDay::leftJoin('users', 'users.id', 'fraud_detection_log_transaction_day.id_user')->whereNull('users.id')->delete();
        FraudDetectionLogTransactionWeek::leftJoin('users', 'users.id', 'fraud_detection_log_transaction_week.id_user')->whereNull('users.id')->delete();


        Schema::table('users_device_login', function (Blueprint $table) {
            $table->dropIndex('fk_users_device_login_users');
            $table->foreign('id_user', 'fk_users_device_login_users')->references('id')->on('users')->onUpdate('CASCADE')->onDelete('CASCADE');
        });

        Schema::table('fraud_detection_log_device', function (Blueprint $table) {
            $table->dropIndex('fk_fraud_detection_log_device_users');
            $table->foreign('id_user', 'fk_fraud_detection_log_device_users')->references('id')->on('users')->onUpdate('CASCADE')->onDelete('CASCADE');
        });

        Schema::table('fraud_detection_log_transaction_day', function (Blueprint $table) {
            $table->dropIndex('fk_fraud_detection_log_transaction_day_users');
            $table->foreign('id_user', 'fk_fraud_detection_log_transaction_day_users')->references('id')->on('users')->onUpdate('CASCADE')->onDelete('CASCADE');
        });

        Schema::table('fraud_detection_log_transaction_week', function (Blueprint $table) {
            $table->dropIndex('fk_fraud_detection_log_transaction_week_users');
            $table->foreign('id_user', 'fk_fraud_detection_log_transaction_week_users')->references('id')->on('users')->onUpdate('CASCADE')->onDelete('CASCADE');
        });
    }
}
