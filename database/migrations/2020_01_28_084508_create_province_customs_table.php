<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateProvinceCustomsTable extends Migration {

    public function up()
    {
        Schema::create('province_customs', function(Blueprint $table)
        {
            $table->increments('id_province_custom');
            $table->string('province_name', 100);
        });
    }

    public function down()
    {
        Schema::drop('province_customs');
    }

}
