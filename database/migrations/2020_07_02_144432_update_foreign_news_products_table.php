<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateForeignNewsProductsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('news_products', function(Blueprint $table)
        {
            $table->dropForeign('fk_news_products_products');
            $table->foreign('id_product', 'fk_news_products_products')->references('id_product_group')->on('product_groups')->onUpdate('CASCADE')->onDelete('CASCADE');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('news_products', function(Blueprint $table)
        {
            $table->dropForeign('fk_news_products_products');
            $table->foreign('id_product', 'fk_news_products_products')->references('id_product')->on('products')->onUpdate('CASCADE')->onDelete('CASCADE');
        });
    }
}
