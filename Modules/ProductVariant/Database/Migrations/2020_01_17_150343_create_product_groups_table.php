<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateProductGroupsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('product_groups', function (Blueprint $table) {
            $table->increments('id_product_group');
            $table->string('product_group_code')->unique();
            $table->string('product_group_name');
            $table->unsignedInteger('id_product_category')->nullable();
            $table->text('product_group_description')->nullable();
            $table->string('product_group_photo',150)->nullable();
            $table->timestamps();

            $table->foreign('id_product_category', 'fk_id_product_category_categories')->references('id_product_category')->on('product_categories');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('product_groups');
    }
}
