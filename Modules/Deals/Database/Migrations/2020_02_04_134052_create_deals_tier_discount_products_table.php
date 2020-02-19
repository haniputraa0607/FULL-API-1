<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateDealsTierDiscountProductsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('deals_tier_discount_products', function (Blueprint $table) {

            $table->increments('id_deals_tier_discount_products');
            $table->unsignedInteger('id_deals');
            $table->unsignedInteger('id_product')->nullable();
            $table->unsignedInteger('id_product_category')->nullable();
            $table->timestamps();

            $table->foreign('id_deals', 'fk_deals_tier_discount_products_deals')->references('id_deals')->on('deals')->onUpdate('CASCADE')->onDelete('CASCADE');
            $table->foreign('id_product', 'fk_deals_tier_discount_products_product')->references('id_product')->on('products')->onUpdate('CASCADE')->onDelete('CASCADE')->nullable();
            $table->foreign('id_product_category', 'fk_deals_tier_discount_products_product_category')->references('id_product_category')->on('product_categories')->onUpdate('CASCADE')->onDelete('CASCADE')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('deals_tier_discount_products');
    }
}
