<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateTimeZoneInOutletSchedulesTables extends Migration
{
    public function __construct()
    {
        DB::getDoctrineSchemaManager()->getDatabasePlatform()->registerDoctrineTypeMapping('enum', 'string');
    }

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('deals', function (Blueprint $table) {
            DB::statement("ALTER TABLE outlet_schedules CHANGE COLUMN time_zone time_zone ENUM('Asia/Jakarta', 'Asia/Makassar', 'Asia/Jayapura', 'Asia/Singapore') NOT NULL");
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('deals', function (Blueprint $table) {
            DB::statement("ALTER TABLE outlet_schedules CHANGE COLUMN time_zone time_zone ENUM('Asia/Jakarta', 'Asia/Makassar', 'Asia/Jayapura', 'Asia/Singapore') NOT NULL");
        });
    }
}
