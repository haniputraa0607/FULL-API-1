<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateColumnOutletCodeSyncTransactionFaileds extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::connection('mysql2')->statement("ALTER TABLE sync_transaction_faileds MODIFY outlet_code varchar(191) NULL");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::connection('mysql2')->statement("ALTER TABLE sync_transaction_faileds MODIFY outlet_code varchar(191) NULL");;
    }
}
