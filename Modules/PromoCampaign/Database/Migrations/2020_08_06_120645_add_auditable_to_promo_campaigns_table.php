<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddAuditableToPromoCampaignsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('promo_campaigns', function (Blueprint $table) {
        	// $table->unsignedBigInteger('updated_by')->nullable()->index();
        	$table->unsignedInteger('created_by')->nullable();
            $table->unsignedInteger('updated_by')->nullable();
        });

        Schema::table('promo_campaign_product_discounts', function (Blueprint $table) {
        	$table->unsignedInteger('created_by')->nullable();
            $table->unsignedInteger('updated_by')->nullable();
        });

        Schema::table('promo_campaign_product_discount_rules', function (Blueprint $table) {
        	$table->unsignedInteger('created_by')->nullable();
            $table->unsignedInteger('updated_by')->nullable();
        });

        Schema::table('promo_campaign_buyxgety_product_requirements', function (Blueprint $table) {
        	$table->unsignedInteger('created_by')->nullable();
            $table->unsignedInteger('updated_by')->nullable();
        });

        Schema::table('promo_campaign_buyxgety_rules', function (Blueprint $table) {
        	$table->unsignedInteger('created_by')->nullable();
            $table->unsignedInteger('updated_by')->nullable();
        });

        Schema::table('promo_campaign_have_tags', function (Blueprint $table) {
        	$table->unsignedInteger('created_by')->nullable();
            $table->unsignedInteger('updated_by')->nullable();
        });

        Schema::table('promo_campaign_outlets', function (Blueprint $table) {
        	$table->unsignedInteger('created_by')->nullable();
            $table->unsignedInteger('updated_by')->nullable();
        });

        Schema::table('promo_campaign_promo_codes', function (Blueprint $table) {
        	$table->unsignedInteger('created_by')->nullable();
            $table->unsignedInteger('updated_by')->nullable();
        });

        Schema::table('promo_campaign_referrals', function (Blueprint $table) {
        	$table->unsignedInteger('created_by')->nullable();
            $table->unsignedInteger('updated_by')->nullable();
        });

        Schema::table('promo_campaign_tags', function (Blueprint $table) {
        	$table->unsignedInteger('created_by')->nullable();
            $table->unsignedInteger('updated_by')->nullable();
        });

        Schema::table('promo_campaign_tier_discount_products', function (Blueprint $table) {
        	$table->unsignedInteger('created_by')->nullable();
            $table->unsignedInteger('updated_by')->nullable();
        });
    
        Schema::table('promo_campaign_tier_discount_rules', function (Blueprint $table) {
        	$table->unsignedInteger('created_by')->nullable();
            $table->unsignedInteger('updated_by')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('promo_campaigns', function (Blueprint $table) {
        	$table->dropColumn('created_by');
        	$table->dropColumn('updated_by');
        	// $table->dropColumn('updated_by');
        });

        Schema::table('promo_campaign_product_discounts', function (Blueprint $table) {
        	$table->dropColumn('created_by');
        	$table->dropColumn('updated_by');
        });

        Schema::table('promo_campaign_product_discount_rules', function (Blueprint $table) {
        	$table->dropColumn('created_by');
        	$table->dropColumn('updated_by');
        });
    
        Schema::table('promo_campaign_buyxgety_product_requirements', function (Blueprint $table) {
        	$table->dropColumn('created_by');
        	$table->dropColumn('updated_by');
        });
    
        Schema::table('promo_campaign_buyxgety_rules', function (Blueprint $table) {
        	$table->dropColumn('created_by');
        	$table->dropColumn('updated_by');
        });
    
        Schema::table('promo_campaign_have_tags', function (Blueprint $table) {
        	$table->dropColumn('created_by');
        	$table->dropColumn('updated_by');
        });
    
        Schema::table('promo_campaign_outlets', function (Blueprint $table) {
        	$table->dropColumn('created_by');
        	$table->dropColumn('updated_by');
        });
    
        Schema::table('promo_campaign_promo_codes', function (Blueprint $table) {
        	$table->dropColumn('created_by');
        	$table->dropColumn('updated_by');
        });
    
        Schema::table('promo_campaign_referrals', function (Blueprint $table) {
        	$table->dropColumn('created_by');
        	$table->dropColumn('updated_by');
        });
    
        Schema::table('promo_campaign_tags', function (Blueprint $table) {
        	$table->dropColumn('created_by');
        	$table->dropColumn('updated_by');
        });
    
        Schema::table('promo_campaign_tier_discount_products', function (Blueprint $table) {
        	$table->dropColumn('created_by');
        	$table->dropColumn('updated_by');
        });
        
        Schema::table('promo_campaign_tier_discount_rules', function (Blueprint $table) {
        	$table->dropColumn('created_by');
        	$table->dropColumn('updated_by');
        });
    }
}
