<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateTransactionPaymentType extends Migration
{
    public function __construct() {
        DB::getDoctrineSchemaManager()->getDatabasePlatform()->registerDoctrineTypeMapping('enum', 'string');
    }

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('transactions', function (Blueprint $table) {
            DB::statement('ALTER TABLE transactions CHANGE trasaction_payment_type trasaction_payment_type ENUM("Manual","Midtrans","Offline","Balance","Ovo") CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL');
        });
    }

    public function down()
    {
        Schema::table('transactions', function (Blueprint $table) {
            DB::statement('ALTER TABLE transactions CHANGE trasaction_payment_type trasaction_payment_type ENUM("Manual","Midtrans","Offline","Balance","Ovo") CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL');
        });
    }
}
