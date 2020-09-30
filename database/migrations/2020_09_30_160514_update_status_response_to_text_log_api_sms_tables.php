<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateStatusResponseToTextLogApiSmsTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('log_api_sms', function (Blueprint $table) {
            $table->text('status_response')->after('status')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('log_api_sms', function (Blueprint $table) {
            $table->string('status_response')->after('status')->nullable()->change();
        });
    }
}
