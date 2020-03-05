<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateOvoFullNameToString extends Migration
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
        Schema::table('transaction_payment_ovos', function (Blueprint $table) {
            $table->string('full_name')->nullable()->change();
        });
        Schema::table('deals_payment_ovos', function (Blueprint $table) {
            $table->string('full_name')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('transaction_payment_ovos', function (Blueprint $table) {
            $table->unsignedInteger('full_name')->nullable()->change();
        });
        Schema::table('deals_payment_ovos', function (Blueprint $table) {
            $table->unsignedInteger('full_name')->nullable()->change();
        });
    }
}
