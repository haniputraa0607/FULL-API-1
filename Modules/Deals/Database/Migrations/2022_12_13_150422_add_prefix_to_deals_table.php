<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddPrefixToDealsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('deals', function (Blueprint $table) {
            $table->string('prefix')->nullable()->after('is_all_days');
            $table->integer('digit_random')->nullable()->after('prefix');
        });
        \DB::statement("ALTER TABLE `deals` CHANGE COLUMN `deals_type` `deals_type` ENUM('Deals', 'Hidden', 'Point', 'Spin', 'Promotion', 'WelcomeVoucher', 'SecondDeals') NOT NULL default('Deals')");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('deals', function (Blueprint $table) {
            $table->dropColumn('prefix');
            $table->dropColumn('digit_random');
        });
        \DB::statement("ALTER TABLE `deals` CHANGE COLUMN `deals_type` `deals_type` ENUM('Deals', 'Hidden', 'Point', 'Spin', 'Promotion', 'WelcomeVoucher', 'Second Deals') NOT NULL default('Deals')");
    }
}
