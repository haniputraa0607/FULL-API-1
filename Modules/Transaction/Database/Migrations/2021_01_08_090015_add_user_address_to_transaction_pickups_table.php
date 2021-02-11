<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddUserAddressToTransactionPickupsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('transaction_pickups', function (Blueprint $table) {
            $table->unsignedInteger('id_user_address')->nullable()->after('id_transaction');
        });
        Schema::table('user_addresses', function (Blueprint $table) {
            $table->dateTime('last_used')->nullable()->after('longitude');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('user_addresses', function (Blueprint $table) {
            $table->dropColumn('last_used');
        });
        Schema::table('transaction_pickups', function (Blueprint $table) {
            $table->dropColumn('id_user_address');
        });
    }
}
