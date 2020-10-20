<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddFraudSettingParameterDetailTime extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('fraud_detection_log_transaction_point', function (Blueprint $table) {
            $table->string('fraud_setting_parameter_detail_time')->nullable()->after('fraud_setting_parameter_detail');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('fraud_detection_log_transaction_point', function (Blueprint $table) {
            $table->dropColumn('fraud_setting_parameter_detail_time');
        });
    }
}
