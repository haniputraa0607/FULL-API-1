<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddTimeZoneToOutletSchedulesTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('outlet_schedules', function (Blueprint $table) {
            $table->enum('time_zone', ['Asia/Jakarta', 'Asia/Makassar', 'Asia/Jayapura'])->after('is_closed');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('outlet_schedules', function (Blueprint $table) {

        });
    }
}
