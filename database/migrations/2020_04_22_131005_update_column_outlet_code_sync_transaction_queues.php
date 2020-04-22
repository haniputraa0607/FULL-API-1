<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateColumnOutletCodeSyncTransactionQueues extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('sync_transaction_queues', function (Blueprint $table) {
            DB::statement("ALTER TABLE sync_transaction_queues MODIFY outlet_code varchar(191) NULL");
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('sync_transaction_queues', function (Blueprint $table) {
            DB::statement("ALTER TABLE sync_transaction_queues MODIFY outlet_code varchar(191) NOT NULL");
        });
    }
}
