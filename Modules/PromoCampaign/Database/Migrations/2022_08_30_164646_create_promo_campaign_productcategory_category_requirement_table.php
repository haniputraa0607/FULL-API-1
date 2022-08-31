<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePromoCampaignProductcategoryCategoryRequirementTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('promo_campaign_productcategory_category_requirements', function (Blueprint $table) {
            $table->increments('id_promo_campaign_productcategory_category_requirement');
            $table->unsignedInteger('id_promo_campaign');
            $table->enum('product_type', ['single', 'group']);
            $table->unsignedInteger('id_product_category');
            $table->unsignedInteger('created_by');
            $table->unsignedInteger('updated_by');
            $table->timestamps();
        });

        Schema::create('promo_campaign_productcategory_rules', function (Blueprint $table) {
            $table->increments('id_promo_campaign_productcategory_rules');
            $table->unsignedInteger('id_promo_campaign');
            $table->integer('min_qty_requirement');
            $table->integer('benefit_qty');
            $table->enum('discount_type', ['percent', 'nominal']);
            $table->integer('discount_value');
            $table->integer('max_percent_discount')->nullable();
            $table->unsignedInteger('id_product_category');
            $table->unsignedInteger('created_by');
            $table->unsignedInteger('updated_by');
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
        Schema::dropIfExists('promo_campaign_productcategory_category_requirements');
        Schema::dropIfExists('promo_campaign_productcategory_rules');
    }
}
