<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddColumnTypeToSyncTransactionQueuesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('sync_transaction_queues', function (Blueprint $table) {
            $table->enum('type',['Transaction','Transaction Refund'])->nullable()->after('id_sync_transaction_queues');
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
            $table->dropColumn('type');
        });
    }
}
