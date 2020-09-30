<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddResponseStatusToLogApiSmsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('log_api_sms', function (Blueprint $table) {
            $table->string('status')->after('phone')->nullable();
            $table->string('status_response')->after('status')->nullable();
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
            $table->dropColumn('status');
            $table->dropColumn('status_response');
        });
    }
}
