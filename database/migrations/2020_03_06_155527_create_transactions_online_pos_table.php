<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTransactionsOnlinePosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('transactions_online_pos', function (Blueprint $table) {
            $table->increments('id_transaction_online_pos');
            $table->unsignedInteger('id_transaction');
            $table->longText('request')->charset('utf8mb4')->collation('utf8mb4_unicode_ci')->nullable();
            $table->longText('response')->charset('utf8mb4')->collation('utf8mb4_unicode_ci')->nullable();
            $table->integer('count_retry')->default(0);
            $table->tinyInteger('success_retry_status')->default(0);
            $table->tinyInteger('send_email_status')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('transactions_online_pos');
    }
}
