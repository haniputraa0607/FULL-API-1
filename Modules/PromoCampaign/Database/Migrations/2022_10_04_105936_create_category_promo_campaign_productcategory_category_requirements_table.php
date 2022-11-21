<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCategoryPromoCampaignProductcategoryCategoryRequirementsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('category_promo_campaign_productcategory_category_requirements', function (Blueprint $table) {
            $table->increments('id_category_promo_campaign_productcategory_category_requirement');
            $table->unsignedInteger('id_promo_campaign_productcategory_category_requirement');
            $table->integer('id_product_category');
            $table->timestamps();
        });

        Schema::table('promo_campaign_productcategory_category_requirements', function (Blueprint $table) {
        	$table->dropColumn('id_product_category');
            $table->unsignedInteger('id_product_variant')->after('product_type');
        });

        Schema::table('promo_campaign_productcategory_rules', function (Blueprint $table) {
        	$table->dropColumn('id_product_category');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('category_promo_campaign_productcategory_category_requirements');
        Schema::table('promo_campaign_productcategory_category_requirements', function (Blueprint $table) {
            $table->unsignedInteger('id_product_category')->after('product_type');
        	$table->dropColumn('id_product_variant');
        });
        Schema::table('promo_campaign_productcategory_rules', function (Blueprint $table) {
            $table->unsignedInteger('id_product_category')->after('max_percent_discount');
        });
    }
}
