<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateLogApiSmsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::connection('mysql2')->create('log_api_sms', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->text('request_body')->nullable();
            $table->text('request_header')->nullable();
            $table->text('request_url')->nullable();
            $table->string('request_method',10)->nullable();
            $table->longText('response')->nullable();
            $table->longText('more_info')->nullable();
            $table->string('phone',15)->nullable();
            $table->string('user_agent',100)->nullable();
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
        Schema::connection('mysql2')->dropIfExists('log_api_sms');
    }
}
