<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateProductVariantPromoCampaignProductcategoryCategoryRequirements extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('variant_promo_campaign_productcategory_category_requirements', function (Blueprint $table) {
            $table->increments('id_variant_promo_campaign_productcategory_category_requirement');
            $table->unsignedInteger('id_promo_campaign_productcategory_category_requirement');
            $table->integer('size');
            $table->integer('type');
            $table->timestamps();
        });

        Schema::table('promo_campaign_productcategory_category_requirements', function (Blueprint $table) {
            $table->dropColumn('id_product_variant');
        });


    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('variant_promo_campaign_productcategory_category_requirements');

        Schema::table('promo_campaign_productcategory_category_requirements', function (Blueprint $table) {
            $table->unsignedInteger('id_product_variant')->nullable()->after('product_type');
        });
    }
}
