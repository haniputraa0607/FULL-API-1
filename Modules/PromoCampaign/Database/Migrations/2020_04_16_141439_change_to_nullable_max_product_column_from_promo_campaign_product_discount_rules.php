<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ChangeToNullableMaxProductColumnFromPromoCampaignProductDiscountRules extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function __construct()
	{
	    DB::getDoctrineSchemaManager()->getDatabasePlatform()->registerDoctrineTypeMapping('enum', 'string');
	}

    public function up()
    {
        Schema::table('promo_campaign_product_discount_rules', function (Blueprint $table) {
			$table->integer('max_product')->nullable(true)->default(null)->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('promo_campaign_product_discount_rules', function (Blueprint $table) {
            $table->integer('max_product')->nullable(false)->change();
        });
    }
}
