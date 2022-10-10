<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ChagneIdProductVariantInPromoCampaignProductcategoryCategoryRequirementsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        \DB::statement("ALTER TABLE `promo_campaign_productcategory_category_requirements` CHANGE COLUMN `id_product_variant` `id_product_variant` INT UNSIGNED NULL");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        \DB::statement("ALTER TABLE `promo_campaign_productcategory_category_requirements` CHANGE COLUMN `id_product_variant` `id_product_variant` INT UNSIGNED NOT NULL");

    }
}
