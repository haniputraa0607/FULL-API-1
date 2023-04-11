<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePromoCampaignDaysTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('promo_campaign_days', function (Blueprint $table) {
            $table->increments('id_promo_campaign_day');
            $table->unsignedInteger('id_promo_campaign');
            $table->string('day');
            $table->timestamps();
        });

        Schema::table('promo_campaigns', function (Blueprint $table) {
            $table->tinyInteger('is_all_days')->default(1)->after('step_complete');
        });

        Schema::table('promo_campaign_productcategory_category_requirements', function (Blueprint $table) {
            $table->tinyInteger('auto_apply')->default(0)->after('id_product_category');
        });

        DB::statement('ALTER TABLE `promo_campaigns` CHANGE `promo_type` `promo_type` ENUM("Product discount","Tier discount","Buy X Get Y","Referral","Discount bill","Discount delivery","Promo Product Category") NULL;');

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('promo_campaign_days');

        Schema::table('promo_campaigns', function (Blueprint $table) {
        	$table->dropColumn('is_all_days');
        });

        Schema::table('promo_campaign_productcategory_category_requirements', function (Blueprint $table) {
        	$table->dropColumn('auto_apply');
        });

        DB::statement('ALTER TABLE `promo_campaigns` CHANGE `promo_type` `promo_type` ENUM("Product discount","Tier discount","Buy X Get Y","Referral","Discount bill","Discount delivery") NULL;');

    }
}
