<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddProductTypeColumnToDealsProductDiscountTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('deals_product_discounts', function (Blueprint $table) {
        	$table->enum('product_type', ['single', 'group'])->after('id_deals');
        	$table->dropForeign('fk_deals_product_discounts_product');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('deals_product_discounts', function (Blueprint $table) {
        	$table->dropColumn('product_type');
        	$table->foreign('id_product', 'fk_deals_product_discounts_product')->references('id_product')->on('products')->onUpdate('CASCADE')->onDelete('CASCADE')->nullable();
        });
    }
}
