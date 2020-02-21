<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddCimbTypeToTransactionsTable extends Migration
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
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('trasaction_payment_type');
        });
        Schema::table('transactions', function (Blueprint $table) {
            $table->enum('trasaction_payment_type', ['Manual', 'Midtrans', 'Offline', 'Balance', 'Ovo', 'Cimb'])->after('transaction_cashback_earned')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('transactions', function (Blueprint $table) {
        });
    }
}
