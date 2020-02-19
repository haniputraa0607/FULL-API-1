<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddProductTypeColumnToPromoCampaignsTierDiscountProductsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('promo_campaign_tier_discount_products', function (Blueprint $table) {
        	$table->enum('product_type', ['single', 'group'])->after('id_promo_campaign');
        	$table->dropForeign('fk_promo_campaign_tier_discount_products_product');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('promo_campaign_tier_discount_products', function (Blueprint $table) {
        	$table->dropColumn('product_type');
        	$table->foreign('id_product', 'fk_promo_campaign_tier_discount_products_product')->references('id_product')->on('products')->onUpdate('CASCADE')->onDelete('CASCADE')->nullable();
        });
    }
}
