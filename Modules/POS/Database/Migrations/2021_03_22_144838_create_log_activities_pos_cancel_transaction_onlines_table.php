<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateLogActivitiesPosCancelTransactionOnlinesTable extends Migration
{
    public $set_schema_table = 'log_activities_pos_cancel_transaction_onlines';
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable($this->set_schema_table)) return;
        Schema::connection('mysql2')->create($this->set_schema_table, function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id_log_activities_pos_cancel_transaction_online');
            $table->string('url', 200);
            $table->string('outlet_code', 191)->nullable();
            $table->text('user')->nullable();
            $table->longText('request')->charset('utf8mb4')->collation('utf8mb4_unicode_ci')->nullable();
            $table->string('response_status', 7)->nullable();
            $table->longText('response')->charset('utf8mb4')->collation('utf8mb4_unicode_ci')->nullable();
            $table->string('ip', 25)->nullable();
            $table->string('useragent', 200)->nullable();
            $table->nullableTimestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('log_activities_pos_cancel_transaction_onlines');
    }
}
